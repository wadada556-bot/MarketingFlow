<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JubelioApiService
{
    private const LOGIN_URL = 'https://api2.jubelio.com/login';
    private const INV_URL   = 'https://open.jubelio.com/core-api/inventory/v2/';
    private const PO_URL    = 'https://open.jubelio.com/core-api/inventory/v2/inbound-purchase-not-fulfilled/';
    private const PAGE_SIZE = 100;

    // Endpoint Orders (pesanan masuk — dipakai fitur tabel `orders`)
    private const ORDERS_LIST_URL  = 'https://open.jubelio.com/core-api/sales/v2/orders/';
    private const ORDER_DETAIL_URL = 'https://open.jubelio.com/core-api/sales/orders/'; // catatan: TANPA /v2/

    // Rate limit Jubelio = 1000 req/menit. Pakai 950 sebagai plafon (sisakan margin).
    private const RATE_MAX_PER_MIN = 950;
    private const DETAIL_CONCURRENCY = 12;

    /** @var array<float> timestamp (epoch detik) tiap request, untuk sliding-window limiter */
    private array $reqLog = [];

    /**
     * Sliding-window limiter: tahan eksekusi sampai aman mengirim $n request lagi
     * tanpa melewati RATE_MAX_PER_MIN dalam 60 detik terakhir.
     */
    private function rateGate(int $n): void
    {
        while (true) {
            $now = microtime(true);
            $this->reqLog = array_values(array_filter($this->reqLog, fn ($t) => $t > $now - 60));
            if (count($this->reqLog) + $n <= self::RATE_MAX_PER_MIN) {
                return;
            }
            // Tunggu sampai request terlama keluar dari jendela 60 detik
            $waitUntil = $this->reqLog[0] + 60;
            $sleep     = max(0.2, $waitUntil - $now);
            usleep((int) ($sleep * 1_000_000));
        }
    }

    private function logReqs(int $n): void
    {
        $now = microtime(true);
        for ($i = 0; $i < $n; $i++) {
            $this->reqLog[] = $now;
        }
    }

    /**
     * Login sekali, lalu pakai token-nya untuk banyak panggilan (list + detail).
     */
    public function getToken(): string
    {
        return $this->login();
    }

    /**
     * Ambil satu halaman daftar ORDER (pesanan masuk) dengan filter channel + tanggal.
     * List ini hanya header — SKU/item ada di detail (getOrderDetailsBatch).
     * Tiap record mengandung: salesorder_id, channel_id, channel_name, store_id,
     * store_name, transaction_date, is_canceled.
     *
     * @param  array<int>  $channelIds
     * @param  string      $fromIsoUtc  ISO UTC, mis. '2026-06-30T17:00:00.000Z'
     * @param  string      $toIsoUtc    ISO UTC
     * @return array{data: array, totalCount: int}
     */
    public function getSalesOrdersPage(string $token, array $channelIds, string $fromIsoUtc, string $toIsoUtc, int $page, int $pageSize = self::PAGE_SIZE): array
    {
        $query = [
            'q'                     => '',
            'page'                  => $page,
            'page_size'             => $pageSize,
            'channel_ids'           => array_values($channelIds),
            'sku_filter'            => 'false',
            'transaction_date_from' => $fromIsoUtc,
            'transaction_date_to'   => $toIsoUtc,
            'sort_by'               => 'transaction_date',
            'sort_direction'        => 'DESC',
        ];

        $body = $this->getWithRetry($token, self::ORDERS_LIST_URL, $query);

        return [
            'data'       => $body['data'] ?? [],
            'totalCount' => (int) ($body['totalCount'] ?? 0),
        ];
    }

    /**
     * Ambil detail BANYAK order secara paralel (Http::pool) menghormati rate-limit,
     * pakai endpoint order detail (sales/orders/{id}, TANPA /v2/). Tiap detail punya
     * array 'items' berisi SKU.
     *
     * Order yang tetap gagal setelah beberapa ronde retry TIDAK melempar exception —
     * cukup di-skip (tidak ada di hasil), biar caller bisa menyimpan yang berhasil dan
     * mencoba lagi sisanya di run berikutnya (sync incremental self-healing).
     *
     * @param  array<int>  $ids  salesorder_id dari getSalesOrdersPage
     * @return array<int, array>  [salesorder_id => detail]  (hanya yang berhasil)
     */
    public function getOrderDetailsBatch(string $token, array $ids, int $concurrency = self::DETAIL_CONCURRENCY): array
    {
        $results = [];
        $pending = array_values(array_unique($ids));

        for ($round = 0; $round < 4 && ! empty($pending); $round++) {
            if ($round > 0) {
                Log::warning('[Jubelio] batch order-detail retry, sisa ' . count($pending));
                sleep(20);
            }

            $failed = [];

            foreach (array_chunk($pending, $concurrency) as $chunk) {
                $this->rateGate(count($chunk));

                $responses = Http::pool(fn ($pool) => array_map(
                    fn ($id) => $pool->as((string) $id)
                        ->withoutVerifying()->timeout(90)
                        ->withHeaders(['Authorization' => $token, 'accept' => 'application/json'])
                        ->get(self::ORDER_DETAIL_URL . $id),
                    $chunk
                ));

                $this->logReqs(count($chunk));

                foreach ($chunk as $id) {
                    $resp = $responses[(string) $id] ?? null;
                    if ($resp instanceof \Illuminate\Http\Client\Response && $resp->successful()) {
                        $results[$id] = $resp->json() ?? [];
                    } else {
                        $failed[] = $id; // 429 / error -> coba lagi di ronde berikutnya
                    }
                }
            }

            $pending = $failed;
        }

        if (! empty($pending)) {
            Log::warning('[Jubelio] ' . count($pending) . ' order detail di-skip (gagal setelah retry), akan dicoba lagi run berikutnya.');
        }

        return $results;
    }

    /**
     * GET dengan retry + backoff. Khusus HTTP 429 (rate-limit Jubelio yang ketat)
     * pakai jeda lebih panjang & bertingkat.
     *
     * @param  array<string, mixed>  $query
     */
    private function getWithRetry(string $token, string $url, array $query = []): array
    {
        $attempts      = 5;
        $lastException = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $this->rateGate(1);
                $response = Http::withoutVerifying()->timeout(90)
                    ->withHeaders([
                        'Authorization' => $token,
                        'accept'        => 'application/json',
                    ])
                    ->get($url, $query);
                $this->logReqs(1);

                if ($response->status() === 429) {
                    $wait = 15 * ($i + 1); // 15s, 30s, 45s, ...
                    Log::warning("[Jubelio] 429 rate-limit di {$url}, tunggu {$wait}s (percobaan {$i})");
                    sleep($wait);
                    continue;
                }

                $response->throw();

                return $response->json() ?? [];
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::warning("[Jubelio] Retry {$i}/{$attempts} GET {$url}: {$e->getMessage()}");
                sleep(5 * ($i + 1)); // backoff non-429: 5s, 10s, ...
            }
        }

        throw $lastException ?? new \RuntimeException("[Jubelio] Gagal GET {$url} setelah {$attempts} percobaan (429 terus).");
    }

    private function login(): string
    {
        $response = Http::withoutVerifying()->timeout(30)->post(self::LOGIN_URL, [
            'email'    => config('services.jubelio.email'),
            'password' => config('services.jubelio.password'),
        ]);

        $response->throw();

        return $response->json('token');
    }

    /**
     * Fetch all HPP (last_cogs / average_cost) from Jubelio inventory.
     *
     * @return array<string, int>  [item_code => hpp_in_rupiah]
     * @throws \Exception
     */
    public function fetchAllHpp(): array
    {
        $token  = $this->login();
        $result = [];
        $page   = 1;
        $total  = null;

        while (true) {
            $body  = $this->fetchPage($token, $page);
            $items = $body['data'] ?? [];
            $total ??= (int) ($body['totalCount'] ?? 0);

            foreach ($items as $item) {
                $code = trim((string) ($item['item_code'] ?? ''));
                $hpp  = (int) round((float) ($item['last_cogs'] ?? $item['average_cost'] ?? 0));
                if ($code !== '') {
                    $result[$code] = $hpp;
                }
            }

            Log::info('[Jubelio] HPP sync progress', ['page' => $page, 'fetched' => count($result), 'total' => $total]);

            if (count($result) >= $total || empty($items)) {
                break;
            }

            $page++;
            sleep(1); // hindari rate-limit
        }

        Log::info('[Jubelio] HPP sync selesai', ['total_sku' => count($result)]);

        return $result;
    }

    /**
     * Fetch stok (available) + HPP untuk banyak parent SKU sekaligus, 1 request per
     * parent SKU (param `q` Jubelio tidak mendukung batch) dijalankan paralel via
     * Http::pool dan menghormati rate limiter yang sama dengan Sales/HPP sync.
     *
     * @param  string[]  $parentSkus
     * @return array{stock: array<string, list<array{sku:string, qty:int}>>, hpp: array<string, int>}
     */
    public function getInventoryByParentSkus(array $parentSkus): array
    {
        if (empty($parentSkus)) {
            return ['stock' => [], 'hpp' => []];
        }

        $token = $this->login();
        $skus  = array_values(array_unique($parentSkus));

        $stock = [];
        $hpp   = [];

        foreach (array_chunk($skus, self::DETAIL_CONCURRENCY) as $chunk) {
            $this->rateGate(count($chunk));

            $responses = Http::pool(fn ($pool) => array_map(
                fn ($sku) => $pool->as($sku)
                    ->withoutVerifying()->timeout(30)
                    ->withHeaders(['Authorization' => $token, 'accept' => 'application/json'])
                    ->get(self::INV_URL, [
                        'page'           => 1,
                        'page_size'      => self::PAGE_SIZE,
                        'sort_direction' => 'NONE',
                        'q'              => $sku,
                    ]),
                $chunk
            ));

            $this->logReqs(count($chunk));

            foreach ($chunk as $parentSku) {
                $resp = $responses[$parentSku] ?? null;
                if (! $resp instanceof \Illuminate\Http\Client\Response || ! $resp->successful()) {
                    Log::warning("[Jubelio] gagal ambil inventory untuk parent SKU {$parentSku}");
                    continue;
                }

                foreach ($resp->json('data') ?? [] as $item) {
                    $code = trim((string) ($item['item_code'] ?? ''));
                    if ($code === '' || ! str_contains(strtoupper($code), strtoupper($parentSku))) {
                        continue; // hasil pencarian `q` bisa nyasar ke SKU lain yang mirip
                    }

                    $available = (int) ($item['total_stocks']['available'] ?? 0);
                    $stock[$parentSku][] = ['sku' => $code, 'qty' => $available];
                    $hpp[$code] = (int) round((float) ($item['last_cogs'] ?? $item['average_cost'] ?? 0));
                }
            }
        }

        return ['stock' => $stock, 'hpp' => $hpp];
    }

    /**
     * Fetch PO inbound yang belum fully-fulfilled untuk banyak parent SKU sekaligus,
     * 1 request per parent SKU (sama seperti inventory — `q` tidak mendukung batch)
     * dijalankan paralel via Http::pool.
     *
     * @param  string[]  $parentSkus
     * @return array<string, array<string, int>>  [parentSku => [variantSku => qty_outstanding]]
     */
    public function getPoByParentSkus(array $parentSkus): array
    {
        if (empty($parentSkus)) {
            return [];
        }

        $token = $this->login();
        $skus  = array_values(array_unique($parentSkus));

        $po = [];

        foreach (array_chunk($skus, self::DETAIL_CONCURRENCY) as $chunk) {
            $this->rateGate(count($chunk));

            $responses = Http::pool(fn ($pool) => array_map(
                fn ($sku) => $pool->as($sku)
                    ->withoutVerifying()->timeout(30)
                    ->withHeaders(['Authorization' => $token, 'accept' => 'application/json'])
                    ->get(self::PO_URL, [
                        'page'            => 1,
                        'page_size'       => self::PAGE_SIZE,
                        'sort_by'         => 'item_name',
                        'sort_direction'  => 'ASC',
                        'q'               => $sku,
                        'brand'           => '',
                        'otherBrand'      => '',
                        'categoryId'      => '',
                    ]),
                $chunk
            ));

            $this->logReqs(count($chunk));

            foreach ($chunk as $parentSku) {
                $resp = $responses[$parentSku] ?? null;
                if (! $resp instanceof \Illuminate\Http\Client\Response || ! $resp->successful()) {
                    Log::warning("[Jubelio] gagal ambil PO untuk parent SKU {$parentSku}");
                    continue;
                }

                foreach ($resp->json('data') ?? [] as $item) {
                    $code = trim((string) ($item['item_code'] ?? ''));
                    if ($code === '' || ! str_contains(strtoupper($code), strtoupper($parentSku))) {
                        continue; // hasil pencarian `q` bisa nyasar ke SKU lain yang mirip
                    }

                    $ordered   = (float) ($item['qty_in_base']   ?? 0);
                    $fulfilled = (float) ($item['qty_fulfilled'] ?? 0);
                    $outstanding = max(0, (int) round($ordered - $fulfilled));

                    $po[$parentSku][$code] = ($po[$parentSku][$code] ?? 0) + $outstanding;
                }
            }
        }

        return $po;
    }

    /**
     * Fetch stok (available) + HPP + grouping produk untuk SELURUH katalog Jubelio,
     * dipaginasi (dipakai job sync terjadwal — lihat SyncJubelioInventory). Jauh lebih
     * efisien daripada getInventoryByParentSkus() per-SKU untuk sync massal.
     *
     * parent_sku diturunkan dari `item_group_id` Jubelio (grouping otoritatif dari
     * mereka, bukan tebakan regex "-N" per SKU) — ambil longest common prefix dari
     * semua item_code dalam 1 grup. Ini juga menangkap label variasi (mis. "Merah")
     * dari field `variation_values` untuk keperluan tampilan detail SKU.
     *
     * @return array<string, array{stok: int, hpp: int, parent_sku: string, item_group_id: int, variation_label: ?string}>
     *         keyed by variant SKU
     */
    public function fetchAllInventory(): array
    {
        $token  = $this->login();
        $result = [];

        $collect = function (array $items) use (&$result): void {
            foreach ($items as $item) {
                $code = trim((string) ($item['item_code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $result[$code] = [
                    'stok'            => (int) ($item['total_stocks']['available'] ?? 0),
                    'hpp'             => (int) round((float) ($item['last_cogs'] ?? $item['average_cost'] ?? 0)),
                    'item_group_id'   => (int) ($item['item_group_id'] ?? 0),
                    'variation_label' => $item['variation_values'][0]['value'] ?? null,
                ];
            }
        };

        $this->fetchAllPagesPooled(
            fetchOne: fn (int $p) => $this->fetchPage($token, $p),
            buildPooled: fn ($pool, int $p) => $pool->as((string) $p)
                ->withoutVerifying()->timeout(90)
                ->withHeaders(['Authorization' => $token])
                ->get(self::INV_URL, [
                    'page'           => $p,
                    'page_size'      => self::PAGE_SIZE,
                    'sort_direction' => 'NONE',
                ]),
            collect: $collect,
            pageSize: self::PAGE_SIZE,
            label: 'inventory',
        );

        return $this->attachParentSku($result);
    }

    /**
     * Paginator paralel untuk endpoint katalog Jubelio. Ambil halaman 1 dulu
     * (baca totalCount), lalu sisa halaman di-pool per DETAIL_CONCURRENCY sambil
     * menghormati rate limiter yang sama dgn sync orders — jauh lebih cepat dari
     * loop sekuensial + sleep(2). Halaman yg gagal di pool di-fallback ke
     * $fetchOne (yang punya retry/backoff sendiri).
     *
     * @param  callable(int): array                 $fetchOne     ambil 1 halaman (dgn retry) -> body JSON
     * @param  callable(mixed, int): mixed          $buildPooled  bangun request pool utk 1 halaman
     * @param  callable(array): void                $collect      akumulasi item dari body['data']
     */
    private function fetchAllPagesPooled(
        callable $fetchOne,
        callable $buildPooled,
        callable $collect,
        int $pageSize,
        string $label,
    ): void {
        $first = $fetchOne(1);
        if (! is_array($first)) {
            throw new \RuntimeException("[Jubelio] Respons tidak valid di halaman {$label} 1.");
        }
        $collect($first['data'] ?? []);

        $total      = (int) ($first['totalCount'] ?? 0);
        $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1;
        if ($totalPages < 2) {
            return;
        }

        foreach (array_chunk(range(2, $totalPages), self::DETAIL_CONCURRENCY) as $chunk) {
            $this->rateGate(count($chunk));

            $responses = Http::pool(fn ($pool) => array_map(
                fn ($p) => $buildPooled($pool, $p),
                $chunk
            ));

            $this->logReqs(count($chunk));

            foreach ($chunk as $p) {
                $resp = $responses[(string) $p] ?? null;
                $body = ($resp instanceof \Illuminate\Http\Client\Response && $resp->successful())
                    ? $resp->json()
                    : $this->fetchOneFallback($fetchOne, $p, $label);
                $collect($body['data'] ?? []);
            }
        }
    }

    /**
     * Fallback saat 1 halaman gagal di pool: ambil ulang sekuensial (retry/backoff).
     */
    private function fetchOneFallback(callable $fetchOne, int $page, string $label): array
    {
        $body = $fetchOne($page);
        if (! is_array($body)) {
            throw new \RuntimeException("[Jubelio] Respons tidak valid di halaman {$label} {$page}.");
        }
        return $body;
    }

    /**
     * Turunkan parent_sku dari grouping item_group_id (otoritatif dari Jubelio):
     * longest common prefix dari semua item_code dalam 1 grup. Fallback ke regex
     * "-N" per SKU kalau item_group_id-nya 0/kosong (jarang terjadi).
     *
     * @param  array<string, array{item_group_id: int}>  $items
     * @return array<string, array{item_group_id: int}>  $items dengan tambahan key 'parent_sku'
     */
    private function attachParentSku(array $items): array
    {
        // PHP mengubah key array yang seluruhnya numerik (mis. item_code "12345") jadi
        // int, bukan string — cast eksplisit di sini supaya longestCommonSkuPrefix()
        // selalu menerima string.
        $codesByGroup = [];
        foreach ($items as $code => $data) {
            $codesByGroup[$data['item_group_id']][] = (string) $code;
        }

        $parentSkuByGroup = [];
        foreach ($codesByGroup as $groupId => $codes) {
            $parentSkuByGroup[$groupId] = $this->longestCommonSkuPrefix($codes);
        }

        foreach ($items as $code => &$data) {
            $codeStr = (string) $code;
            $data['parent_sku'] = ($data['item_group_id'] > 0 && isset($parentSkuByGroup[$data['item_group_id']]))
                ? $parentSkuByGroup[$data['item_group_id']]
                : strtoupper(preg_replace('/-\d+$/', '', $codeStr) ?? $codeStr);
        }
        unset($data);

        return $items;
    }

    /**
     * Longest common prefix dari beberapa item_code (mis. T01-BSCW-1..8 -> T01-BSCW),
     * trim trailing "-". Kalau cuma 1 SKU dalam grup (tanpa suffix), pakai kode itu sendiri.
     *
     * @param  string[]  $codes
     */
    private function longestCommonSkuPrefix(array $codes): string
    {
        $codes = array_map('strval', $codes);
        sort($codes);
        $first = reset($codes);
        $last  = end($codes);
        $len   = min(strlen($first), strlen($last));

        $i = 0;
        while ($i < $len && $first[$i] === $last[$i]) {
            $i++;
        }
        $prefix = rtrim(substr($first, 0, $i), '-');

        return strtoupper($prefix !== '' ? $prefix : $first);
    }

    /**
     * Fetch PO inbound outstanding (qty_in_base - qty_fulfilled) untuk SELURUH katalog,
     * dipaginasi. Dipakai job sync terjadwal — lihat SyncJubelioInventory.
     *
     * @return array<string, int>  [variantSku => qty_outstanding]
     */
    public function fetchAllPo(): array
    {
        $token      = $this->login();
        $result     = [];
        $poPageSize = self::PAGE_SIZE * 2;

        $collect = function (array $items) use (&$result): void {
            foreach ($items as $item) {
                $code = trim((string) ($item['item_code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $ordered     = (float) ($item['qty_in_base']   ?? 0);
                $fulfilled   = (float) ($item['qty_fulfilled'] ?? 0);
                $outstanding = max(0, (int) round($ordered - $fulfilled));

                $result[$code] = ($result[$code] ?? 0) + $outstanding;
            }
        };

        $this->fetchAllPagesPooled(
            fetchOne: fn (int $p) => $this->fetchPoPage($token, $p, $poPageSize),
            buildPooled: fn ($pool, int $p) => $pool->as((string) $p)
                ->withoutVerifying()->timeout(90)
                ->withHeaders(['Authorization' => $token, 'accept' => 'application/json'])
                ->get(self::PO_URL, [
                    'page'           => $p,
                    'page_size'      => $poPageSize,
                    'sort_by'        => 'item_name',
                    'sort_direction' => 'ASC',
                    'q'              => '',
                    'brand'          => '',
                    'otherBrand'     => '',
                    'categoryId'     => '',
                ]),
            collect: $collect,
            pageSize: $poPageSize,
            label: 'PO',
        );

        return $result;
    }

    /**
     * Fetch satu halaman PO inbound-not-fulfilled dengan retry otomatis.
     */
    private function fetchPoPage(string $token, int $page, int $pageSize): array
    {
        $attempts       = 5;
        $lastException  = null;

        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0) {
                sleep(10 * $i); // 10s, 20s, 30s, 40s
            }
            try {
                $response = Http::withoutVerifying()->timeout(90)
                    ->withHeaders(['Authorization' => $token, 'accept' => 'application/json'])
                    ->get(self::PO_URL, [
                        'page'           => $page,
                        'page_size'      => $pageSize,
                        'sort_by'        => 'item_name',
                        'sort_direction' => 'ASC',
                        'q'              => '',
                        'brand'          => '',
                        'otherBrand'     => '',
                        'categoryId'     => '',
                    ]);

                $response->throw();
                return $response->json();
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::warning("[Jubelio] Retry {$i}/{$attempts} PO page {$page}: {$e->getMessage()}");
            }
        }

        throw $lastException;
    }

    /**
     * Fetch satu halaman inventory dengan retry otomatis (5x, backoff bertingkat).
     */
    private function fetchPage(string $token, int $page): array
    {
        $attempts = 5;
        $lastException = null;

        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0) {
                sleep(10 * $i); // backoff: 10s, 20s, 30s, 40s
            }
            try {
                $response = Http::withoutVerifying()->timeout(90)
                    ->withHeaders(['Authorization' => $token])
                    ->get(self::INV_URL, [
                        'page'           => $page,
                        'page_size'      => self::PAGE_SIZE,
                        'sort_direction' => 'NONE',
                    ]);

                $response->throw();
                return $response->json();
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::warning("[Jubelio] Retry {$i}/{$attempts} page {$page}: {$e->getMessage()}");
            }
        }

        throw $lastException;
    }
}
