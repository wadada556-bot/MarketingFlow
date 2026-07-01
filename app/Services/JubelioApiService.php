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

    // Endpoint Sales (dipakai fitur History Penjualan)
    private const SALES_LIST_URL   = 'https://open.jubelio.com/core-api/sales/v2/invoices/';
    private const SALES_DETAIL_URL = 'https://open.jubelio.com/core-api/sales/v2/invoices/';

    // Rate limit Jubelio = 600 req/menit. Pakai 560 sebagai plafon (sisakan margin).
    private const RATE_MAX_PER_MIN = 560;
    private const DETAIL_CONCURRENCY = 8;

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
     * Ambil detail BANYAK invoice secara paralel (Http::pool) dengan menghormati
     * rate-limit 560/menit. Jauh lebih cepat dari ambil satu per satu.
     *
     * @param  array<int>  $ids  doc_id dari endpoint invoices
     * @return array<int, array>  [doc_id => detail]
     */
    public function getInvoiceDetailsBatch(string $token, array $ids, int $concurrency = self::DETAIL_CONCURRENCY): array
    {
        $results = [];
        $pending = array_values(array_unique($ids));

        for ($round = 0; $round < 4 && ! empty($pending); $round++) {
            if ($round > 0) {
                Log::warning('[Jubelio] batch detail retry, sisa ' . count($pending));
                sleep(20);
            }

            $failed = [];

            foreach (array_chunk($pending, $concurrency) as $chunk) {
                $this->rateGate(count($chunk));

                $responses = Http::pool(fn ($pool) => array_map(
                    fn ($id) => $pool->as((string) $id)
                        ->withoutVerifying()->timeout(90)
                        ->withHeaders(['Authorization' => $token, 'accept' => 'application/json'])
                        ->get(self::SALES_DETAIL_URL . $id),
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
            throw new \RuntimeException('[Jubelio] gagal ambil detail untuk ' . count($pending) . ' invoice.');
        }

        return $results;
    }

    /**
     * Login sekali, lalu pakai token-nya untuk banyak panggilan (list + detail).
     */
    public function getToken(): string
    {
        return $this->login();
    }

    /**
     * Ambil satu halaman daftar invoice (header saja) dengan filter channel + tanggal.
     * Semua status diambil; invoice retur disaring di SalesSyncService lewat flag is_return.
     * Tiap record mengandung: doc_id, source (=channel_id), store_id, store_name, transaction_date, is_return.
     *
     * @param  array<int>  $channelIds        mis. [128, 131076]
     * @param  string      $fromIsoUtc        ISO UTC, mis. '2025-12-31T17:00:00.000Z'
     * @param  string      $toIsoUtc          ISO UTC
     * @return array{data: array, totalCount: int}
     */
    public function getSalesInvoicesPage(string $token, array $channelIds, string $fromIsoUtc, string $toIsoUtc, int $page, int $pageSize = self::PAGE_SIZE): array
    {
        $query = [
            'q'                     => '',
            'page'                  => $page,
            'page_size'             => $pageSize,
            'channel_ids'           => array_values($channelIds),
            'transaction_date_from' => $fromIsoUtc,
            'transaction_date_to'   => $toIsoUtc,
            'sort_by'               => 'transaction_date',
            'sort_direction'        => 'DESC',
        ];

        $body = $this->getWithRetry($token, self::SALES_LIST_URL, $query);

        return [
            'data'       => $body['data'] ?? [],
            'totalCount' => (int) ($body['totalCount'] ?? 0),
        ];
    }

    /**
     * Ambil detail satu invoice (termasuk array items / SKU).
     *
     * @return array  full invoice; items ada di key 'items'
     */
    public function getInvoiceDetail(string $token, int $docId): array
    {
        return $this->getWithRetry($token, self::SALES_DETAIL_URL . $docId);
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
     * Fetch stok (available) + HPP untuk SELURUH katalog Jubelio, dipaginasi (dipakai
     * oleh job sync terjadwal — lihat SyncJubelioInventory). Jauh lebih efisien daripada
     * getInventoryByParentSkus() per-SKU untuk sync massal.
     *
     * @return array<string, array{stok: int, hpp: int}>  keyed by variant SKU
     */
    public function fetchAllInventory(): array
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
                if ($code === '') {
                    continue;
                }
                $result[$code] = [
                    'stok' => (int) ($item['total_stocks']['available'] ?? 0),
                    'hpp'  => (int) round((float) ($item['last_cogs'] ?? $item['average_cost'] ?? 0)),
                ];
            }

            if (count($result) >= $total || empty($items)) {
                break;
            }

            $page++;
            sleep(1); // hindari rate-limit
        }

        return $result;
    }

    /**
     * Fetch PO inbound outstanding (qty_in_base - qty_fulfilled) untuk SELURUH katalog,
     * dipaginasi. Dipakai job sync terjadwal — lihat SyncJubelioInventory.
     *
     * @return array<string, int>  [variantSku => qty_outstanding]
     */
    public function fetchAllPo(): array
    {
        $token       = $this->login();
        $result      = [];
        $page        = 1;
        $total       = null;
        $fetchedRows = 0;
        $poPageSize  = self::PAGE_SIZE * 2;

        while (true) {
            $body  = $this->fetchPoPage($token, $page, $poPageSize);
            $items = $body['data'] ?? [];
            $total ??= (int) ($body['totalCount'] ?? 0);

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

            $fetchedRows += count($items);

            if ($fetchedRows >= $total || empty($items)) {
                break;
            }

            $page++;
            sleep(1); // hindari rate-limit
        }

        return $result;
    }

    /**
     * Fetch satu halaman PO inbound-not-fulfilled dengan retry otomatis.
     */
    private function fetchPoPage(string $token, int $page, int $pageSize): array
    {
        $attempts       = 3;
        $lastException  = null;

        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0) {
                sleep(5 * $i);
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
     * Fetch satu halaman inventory dengan retry otomatis (3x, backoff 5 detik).
     */
    private function fetchPage(string $token, int $page): array
    {
        $attempts = 3;
        $lastException = null;

        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0) {
                sleep(5 * $i); // backoff: 5s, 10s
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
