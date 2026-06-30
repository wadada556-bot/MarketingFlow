<?php

namespace App\Services;

use App\Models\DailySkuSales;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesSyncService
{
    /** Channel yang disync: 128 = Tokopedia, 131076 = TikTok */
    public const CHANNELS = [128, 131076];

    private const TZ        = 'Asia/Jakarta';
    private const PAGE_SIZE = 100;

    /** Jumlah detail order yang diambil paralel per batch (dibatasi rate-limit 560/menit). */
    private int $concurrency = 8;

    public function __construct(private JubelioApiService $jubelio)
    {
    }

    public function setConcurrency(int $n): self
    {
        $this->concurrency = max(1, $n);
        return $this;
    }

    /**
     * Sync penjualan (COMPLETED) untuk rentang tanggal WIB [$from .. $to] (inklusif),
     * lalu tulis ulang baris pada rentang itu di daily_sku_sales agar selalu akurat.
     *
     * @param  array<int>  $channelIds
     * @param  callable|null  $progress  fn(int $page, int $collected, int $total): void
     * @return array{orders: int, rows: int, total: int}
     */
    public function syncRange(Carbon $from, Carbon $to, array $channelIds = self::CHANNELS, ?callable $progress = null): array
    {
        $fromDate = $from->copy()->toDateString();
        $toDate   = $to->copy()->toDateString();

        // Jendela UTC yang menutupi seluruh hari WIB [from 00:00 .. to 23:59:59]
        $fromIso = $from->copy()->setTimezone(self::TZ)->startOfDay()->utc()->format('Y-m-d\TH:i:s.v\Z');
        $toIso   = $to->copy()->setTimezone(self::TZ)->endOfDay()->utc()->format('Y-m-d\TH:i:s.v\Z');

        $token = $this->jubelio->getToken();

        $agg            = [];   // key => baris agregasi
        $page           = 1;
        $collected      = 0;
        $total          = null;
        $ordersHandled  = 0;
        $now            = now();

        while (true) {
            $resp   = $this->jubelio->getSalesInvoicesPage($token, $channelIds, $fromIso, $toIso, $page, self::PAGE_SIZE);
            $orders = $resp['data'];
            $total ??= $resp['totalCount'];

            // Saring order valid (bukan Batal, dalam rentang WIB), kumpulkan id-nya
            $valid = [];
            foreach ($orders as $o) {
                $collected++;

                if (! empty($o['is_return'])) {
                    continue;
                }

                // Tanggal transaksi dalam WIB; buang yang jatuh di luar rentang (spillover batas zona waktu)
                $txDate = Carbon::parse($o['transaction_date'])->setTimezone(self::TZ)->toDateString();
                if ($txDate < $fromDate || $txDate > $toDate) {
                    continue;
                }

                $valid[] = ['order' => $o, 'txDate' => $txDate];
            }

            // Ambil detail semua order valid di halaman ini secara paralel
            if (! empty($valid)) {
                $ids     = array_map(fn ($v) => (int) $v['order']['doc_id'], $valid);
                $details = $this->jubelio->getInvoiceDetailsBatch($token, $ids, $this->concurrency);

                foreach ($valid as $v) {
                    $detail = $details[(int) $v['order']['doc_id']] ?? [];
                    $this->accumulate($agg, $v['order'], $detail, $v['txDate'], $now);
                    $ordersHandled++;
                }
            }

            if ($progress) {
                $progress($page, $collected, (int) $total);
            }

            if ($collected >= (int) $total || empty($orders)) {
                break;
            }

            $page++;
        }

        // Finalisasi angka tiap baris
        $rows = [];
        foreach ($agg as $row) {
            $row['order_count'] = count($row['_orders']);
            $row['qty_terjual'] = (int) round($row['qty_terjual']);
            $row['omzet']       = (int) round($row['omzet']);
            unset($row['_orders']);
            $rows[] = $row;
        }

        // Tulis ulang rentang ini (hapus lalu insert) di dalam transaksi
        DB::transaction(function () use ($rows, $fromDate, $toDate, $channelIds) {
            DailySkuSales::whereBetween('sales_date', [$fromDate, $toDate])
                ->whereIn('channel_id', $channelIds)
                ->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DailySkuSales::insert($chunk);
            }
        });

        Log::info('[SalesSync] selesai', [
            'from' => $fromDate, 'to' => $toDate,
            'orders' => $ordersHandled, 'rows' => count($rows), 'total' => (int) $total,
        ]);

        return ['orders' => $ordersHandled, 'rows' => count($rows), 'total' => (int) $total];
    }

    /**
     * Tambahkan item-item satu order ke akumulator, dikelompokkan per (tanggal, toko, sku).
     */
    private function accumulate(array &$agg, array $order, array $detail, string $txDate, Carbon $now): void
    {
        $storeId     = (int) ($order['store_id'] ?? 0);
        $channelId   = (int) ($order['source'] ?? 0);  // invoices pakai 'source', bukan 'channel_id'
        $channelName = match ($channelId) {
            128    => 'Tokopedia',
            131076 => 'TikTok',
            default => (string) $channelId,
        };
        $storeName   = $order['store_name'] ?? null;
        $orderId     = $order['doc_id'] ?? null;

        foreach (($detail['items'] ?? []) as $it) {
            if (! empty($it['is_canceled_item'])) {
                continue;
            }

            $sku = strtoupper(trim((string) ($it['item_code'] ?? '')));
            if ($sku === '') {
                continue;
            }

            $key = $txDate . '|' . $storeId . '|' . $sku;

            if (! isset($agg[$key])) {
                $agg[$key] = [
                    'sales_date'   => $txDate,
                    'channel_id'   => $channelId,
                    'channel_name' => $channelName ? mb_substr($channelName, 0, 50) : null,
                    'store_id'     => $storeId,
                    'store_name'   => $storeName ? mb_substr($storeName, 0, 150) : null,
                    'sku'          => $sku,
                    'parent_sku'   => $this->parentSku($sku),
                    'product_name' => null,
                    'qty_terjual'  => 0,
                    'omzet'        => 0,
                    'order_count'  => 0,
                    '_orders'      => [],
                    'synced_at'    => $now,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }

            $agg[$key]['qty_terjual'] += (float) ($it['qty'] ?? 0);
            $agg[$key]['omzet']       += (float) ($it['amount'] ?? 0);

            if ($orderId !== null) {
                $agg[$key]['_orders'][$orderId] = true;
            }

            if (empty($agg[$key]['product_name'])) {
                $name = $it['item_name'] ?? $it['description'] ?? null;
                if ($name) {
                    $agg[$key]['product_name'] = mb_substr($name, 0, 255);
                }
            }
        }
    }

    /** SKU induk = SKU tanpa suffix variasi "-<angka>" di akhir. */
    private function parentSku(string $sku): string
    {
        return strtoupper(preg_replace('/-\d+$/', '', $sku) ?? $sku);
    }
}
