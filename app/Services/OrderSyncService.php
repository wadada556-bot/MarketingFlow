<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sync PESANAN MASUK dari endpoint Jubelio /orders/ ke tabel lokal `orders`,
 * satu baris per item order (bukan agregasi seperti daily_sku_sales).
 * Semua order diambil apa adanya (tanpa filter status) — tujuannya menghitung
 * banyaknya pesanan masuk per produk.
 *
 * INCREMENTAL: detail sebuah order hanya di-fetch kalau order itu baru ATAU
 * `last_modified`-nya di list berubah dari yang tersimpan. Penulisan dilakukan
 * PER-SLICE (bukan sekali di akhir) supaya progres tahan putus & resumable;
 * detail yang gagal di-skip dan otomatis dicoba lagi run berikutnya.
 */
class OrderSyncService
{
    /** Channel yang disync: 131076 (sesuai kebutuhan tabel `orders`). */
    public const CHANNELS = [131076];

    private const TZ         = 'Asia/Jakarta';
    private const PAGE_SIZE  = 200;   // list header murah -> page besar, request list lebih sedikit
    private const WRITE_SLICE = 300;  // banyak detail di-fetch+tulis per checkpoint

    /** Jumlah detail order yang diambil paralel per batch (dibatasi rate-limit). */
    private int $concurrency = 12;

    public function __construct(private JubelioApiService $jubelio)
    {
    }

    public function setConcurrency(int $n): self
    {
        $this->concurrency = max(1, $n);
        return $this;
    }

    /**
     * Sync order untuk rentang tanggal WIB [$from .. $to] (inklusif).
     *
     * @param  array<int>     $channelIds
     * @param  callable|null  $progress    fn(string $msg): void
     * @return array{orders: int, to_fetch: int, fetched: int, skipped: int, failed: int, rows: int}
     */
    public function syncRange(Carbon $from, Carbon $to, array $channelIds = self::CHANNELS, ?callable $progress = null): array
    {
        $fromDate = $from->copy()->toDateString();
        $toDate   = $to->copy()->toDateString();

        // Jendela UTC yang menutupi seluruh hari WIB [from 00:00 .. to 23:59:59]
        $fromIso = $from->copy()->setTimezone(self::TZ)->startOfDay()->utc()->format('Y-m-d\TH:i:s.v\Z');
        $toIso   = $to->copy()->setTimezone(self::TZ)->endOfDay()->utc()->format('Y-m-d\TH:i:s.v\Z');

        $token = $this->jubelio->getToken();

        // 1) Kumpulkan SEMUA header order dalam rentang (murah, tanpa detail).
        $headers = $this->collectHeaders($token, $channelIds, $fromIso, $toIso, $fromDate, $toDate, $progress);

        // 2) Peta last_modified yang SUDAH ada di DB untuk rentang+channel ini.
        $existing = $this->existingLastModified($fromDate, $toDate, $channelIds);

        // 3) Tentukan order yang perlu di-fetch: baru, atau last_modified berubah.
        $toFetch = [];
        foreach ($headers as $id => $h) {
            $dbMod = $existing[$id] ?? null;
            if ($dbMod === null || $h['listMod'] === null || $h['listMod']->gt($dbMod)) {
                $toFetch[] = $id;
            }
        }

        $skipped = count($headers) - count($toFetch);
        if ($progress) {
            $progress(count($headers) . " order dalam rentang, {$skipped} tak berubah, " . count($toFetch) . ' perlu di-fetch');
        }

        // 4) Fetch detail per-slice; tulis tiap slice (checkpoint) sambil jalan.
        $now     = now();
        $fetched = 0;
        $failed  = 0;
        $rowsAll = 0;

        foreach (array_chunk($toFetch, self::WRITE_SLICE) as $i => $sliceIds) {
            $details = $this->jubelio->getOrderDetailsBatch($token, $sliceIds, $this->concurrency);

            $sliceRows = [];
            $doneIds   = [];
            foreach ($sliceIds as $id) {
                if (! isset($details[$id])) {
                    $failed++;
                    continue; // di-skip, dicoba lagi run berikutnya
                }
                $doneIds[] = $id;
                foreach ($this->buildRows($headers[$id], $details[$id], $now) as $row) {
                    $sliceRows[] = $row;
                }
            }

            DB::transaction(function () use ($doneIds, $sliceRows) {
                if (! empty($doneIds)) {
                    Order::whereIn('salesorder_id', $doneIds)->delete();
                }
                foreach (array_chunk($sliceRows, 500) as $chunk) {
                    Order::insert($chunk);
                }
            });

            $fetched += count($doneIds);
            $rowsAll += count($sliceRows);

            if ($progress) {
                $progress('  slice ' . ($i + 1) . ': +' . count($doneIds) . ' order, ' . count($sliceRows) . " baris (total {$fetched}/" . count($toFetch) . ')');
            }
        }

        Log::info('[OrderSync] selesai', [
            'from' => $fromDate, 'to' => $toDate,
            'orders' => count($headers), 'to_fetch' => count($toFetch),
            'fetched' => $fetched, 'skipped' => $skipped, 'failed' => $failed, 'rows' => $rowsAll,
        ]);

        return [
            'orders'   => count($headers),
            'to_fetch' => count($toFetch),
            'fetched'  => $fetched,
            'skipped'  => $skipped,
            'failed'   => $failed,
            'rows'     => $rowsAll,
        ];
    }

    /**
     * Loop semua halaman list, kumpulkan header order dalam rentang WIB.
     *
     * @return array<int, array{order: array, txDate: string, listMod: ?Carbon}>  keyed by salesorder_id
     */
    private function collectHeaders(string $token, array $channelIds, string $fromIso, string $toIso, string $fromDate, string $toDate, ?callable $progress): array
    {
        $headers   = [];
        $page      = 1;
        $collected = 0;
        $total     = null;

        while (true) {
            $resp   = $this->jubelio->getSalesOrdersPage($token, $channelIds, $fromIso, $toIso, $page, self::PAGE_SIZE);
            $orders = $resp['data'];
            $total ??= $resp['totalCount'];

            foreach ($orders as $o) {
                $collected++;

                // Tanggal transaksi WIB; buang yang di luar rentang (spillover batas zona waktu)
                $txDate = Carbon::parse($o['transaction_date'])->setTimezone(self::TZ)->toDateString();
                if ($txDate < $fromDate || $txDate > $toDate) {
                    continue;
                }

                $id = (int) ($o['salesorder_id'] ?? 0);
                if ($id === 0) {
                    continue;
                }

                $headers[$id] = [
                    'order'   => $o,
                    'txDate'  => $txDate,
                    'listMod' => isset($o['last_modified'])
                        ? Carbon::parse($o['last_modified'])->utc()->startOfSecond()
                        : null,
                ];
            }

            if ($progress) {
                $progress("list halaman {$page}: {$collected}/{$total} order");
            }

            if ($collected >= (int) $total || empty($orders)) {
                break;
            }

            $page++;
        }

        return $headers;
    }

    /**
     * Peta salesorder_id => last_modified (Carbon UTC) yang sudah tersimpan.
     *
     * @return array<int, Carbon>
     */
    private function existingLastModified(string $fromDate, string $toDate, array $channelIds): array
    {
        $rows = Order::whereBetween('sales_date', [$fromDate, $toDate])
            ->whereIn('channel_id', $channelIds)
            ->selectRaw('salesorder_id, MAX(last_modified) as lm')
            ->groupBy('salesorder_id')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            if ($r->lm !== null) {
                $map[(int) $r->salesorder_id] = Carbon::parse($r->lm, 'UTC')->startOfSecond();
            }
        }

        return $map;
    }

    /**
     * Bangun baris `orders` (satu per item) dari satu order + detail-nya.
     * Ambil semua item apa adanya (tanpa filter batal/status).
     *
     * @param  array{order: array, txDate: string, listMod: ?Carbon}  $header
     * @return list<array<string, mixed>>
     */
    private function buildRows(array $header, array $detail, Carbon $now): array
    {
        $order       = $header['order'];
        $txDate      = $header['txDate'];
        $lastModStr  = $header['listMod']?->format('Y-m-d H:i:s');

        $channelId   = (int) ($order['channel_id'] ?? 0);
        $channelName = $order['channel_name'] ?? null;
        $storeId     = (int) ($order['store_id'] ?? 0);
        $storeName   = $order['store_name'] ?? null;
        $orderId     = (int) ($order['salesorder_id'] ?? 0);

        $rows = [];
        foreach (($detail['items'] ?? []) as $it) {
            $detailId = (int) ($it['salesorder_detail_id'] ?? 0);
            $sku      = strtoupper(trim((string) ($it['item_code'] ?? '')));
            if ($detailId === 0 || $sku === '') {
                continue;
            }

            $rows[] = [
                'sales_date'           => $txDate,
                'channel_id'           => $channelId,
                'channel_name'         => $channelName ? mb_substr($channelName, 0, 50) : null,
                'store_id'             => $storeId,
                'store_name'           => $storeName ? mb_substr($storeName, 0, 150) : null,
                'sku_parent'           => $this->parentSku($sku),
                'sku_variant'          => mb_substr($sku, 0, 100),
                'qty'                  => (int) round((float) ($it['qty_in_base'] ?? $it['qty'] ?? 0)),
                'salesorder_id'        => $orderId,
                'salesorder_detail_id' => $detailId,
                'last_modified'        => $lastModStr,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        return $rows;
    }

    /** SKU induk = SKU tanpa suffix variasi "-<angka>" di akhir. */
    private function parentSku(string $sku): string
    {
        return strtoupper(preg_replace('/-\d+$/', '', $sku) ?? $sku);
    }
}
