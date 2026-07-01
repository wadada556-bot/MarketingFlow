<?php

namespace App\Services;

use App\Models\SkuHpp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErpApiService
{
    private string $baseUrl = 'https://erp-server-1.python-featherback.ts.net';

    /**
     * Fetch stock quantities for a list of parent SKUs in a single batch ERP call.
     *
     * @param  string[]  $parentSkus
     * @return array<string, list<array{sku: string, qty: int}>>
     *         Keyed by parent SKU, each value is an array of variant rows.
     */
    public function getStockByParentSkus(array $parentSkus): array
    {
        if (empty($parentSkus)) {
            return [];
        }

        $skus = collect($parentSkus)->unique()->filter()->values();
        $rows = $this->query($this->buildStockSql($skus));

        return $this->mapStockRows($rows, $skus);
    }

    /**
     * Fetch PO inbound qty for a list of parent SKUs (tanpa stock/sales — dipakai
     * setelah stock+HPP pindah ke Jubelio dan sales pindah ke daily_sku_sales lokal).
     *
     * @param  string[]  $parentSkus
     * @return array<string, array<string, int>>  [parentSku => [variantSku => qty_ordered]]
     */
    public function getPoByParentSkus(array $parentSkus): array
    {
        if (empty($parentSkus)) {
            return [];
        }

        $skus = collect($parentSkus)->unique()->filter()->values();
        $rows = $this->query($this->buildPoSql($skus));

        return $this->mapPoRows($rows, $skus);
    }

    /**
     * Fetch stock + per-variant sales + PO qty in ONE concurrent batch.
     *
     * Fires the stock query, PO query, and all per-SKU sales requests together via a
     * single Http::pool so their network time overlaps instead of running sequentially.
     *
     * @param  string[]  $parentSkus
     * @return array{stock: array<string, list<array{sku:string, qty:int}>>, sales: array<string, array{today:array,yesterday:array,7d:array,30d:array}>, po: array<string, array<string, int>>}
     */
    public function getStockAndSalesByParentSkus(array $parentSkus): array
    {
        if (empty($parentSkus)) {
            return ['stock' => [], 'sales' => [], 'po' => []];
        }

        $skus     = collect($parentSkus)->unique()->filter()->values();
        $stockSql = $this->buildStockSql($skus);
        $poSql    = $this->buildPoSql($skus);

        try {
            $responses = Http::pool(function ($pool) use ($skus, $stockSql, $poSql) {
                $requests = [];
                $requests[] = $pool->as('__stock__')
                    ->withoutVerifying()
                    ->timeout(20)
                    ->post("{$this->baseUrl}/api/db/query", ['query' => $stockSql]);

                $requests[] = $pool->as('__po__')
                    ->withoutVerifying()
                    ->timeout(20)
                    ->post("{$this->baseUrl}/api/db/query", ['query' => $poSql]);

                foreach ($skus as $sku) {
                    $requests[] = $pool->as((string) $sku)
                        ->withoutVerifying()
                        ->timeout(15)
                        ->post("{$this->baseUrl}/api/tiktok/sku-qty", ['sku' => $sku]);
                }

                return $requests;
            });
        } catch (\Throwable $e) {
            Log::error('ERP combined API exception', ['message' => $e->getMessage()]);
            return ['stock' => [], 'sales' => [], 'po' => []];
        }

        // ── Stock ──
        $stockResp = $responses['__stock__'] ?? null;
        $stockRows = (!$stockResp || $stockResp instanceof \Throwable || $stockResp->failed())
            ? []
            : $this->extractRows($stockResp->json());
        $stock = $this->mapStockRows($stockRows, $skus);

        // ── PO ──
        $poResp = $responses['__po__'] ?? null;
        $poRows = (!$poResp || $poResp instanceof \Throwable || $poResp->failed())
            ? []
            : $this->extractRows($poResp->json());
        $po = $this->mapPoRows($poRows, $skus);

        // ── Sales ──
        $sales = [];
        foreach ($skus as $sku) {
            $resp = $responses[(string) $sku] ?? null;
            $sales[$sku] = (!$resp || $resp instanceof \Throwable || $resp->failed())
                ? ['today' => [], 'yesterday' => [], '7d' => [], '30d' => []]
                : $this->parseVariantSalesResponse($resp->json());
        }

        return ['stock' => $stock, 'sales' => $sales, 'po' => $po];
    }

    /**
     * Build the batch stock-lookup SQL for a set of parent SKUs.
     */
    private function buildStockSql(Collection $skus): string
    {
        $conditions = $skus->map(fn($sku) => "sku LIKE '%" . $this->sanitizeSku($sku) . "%'")->implode(' OR ');

        return "SELECT sku, qty_on_hand FROM purchase.stock_current WHERE {$conditions} ORDER BY sku";
    }

    /**
     * Build the PO inbound SQL for a set of parent SKUs.
     * Groups by sku and sums qty_ordered across open PO lines.
     */
    private function buildPoSql(Collection $skus): string
    {
        $conditions = $skus->map(fn($sku) => "sku LIKE '%" . $this->sanitizeSku($sku) . "%'")->implode(' OR ');

        return "SELECT sku, SUM(qty_ordered) as qty_ordered FROM purchase.po_inbound_current WHERE {$conditions} GROUP BY sku ORDER BY sku";
    }

    /** Strip characters outside the valid SKU alphabet before SQL interpolation. */
    private function sanitizeSku(string $sku): string
    {
        return preg_replace('/[^A-Za-z0-9\-_]/', '', $sku);
    }

    /**
     * Group raw PO rows under their matching parent SKU.
     *
     * @return array<string, array<string, int>>  [parentSku => [variantSku => qty_ordered]]
     */
    private function mapPoRows(iterable $rows, Collection $skus): array
    {
        $sortedSkus = $skus->sortByDesc(fn ($s) => strlen($s))->values();
        $result = [];
        foreach ($rows as $row) {
            $variantSku = $row['sku'] ?? '';
            foreach ($sortedSkus as $parentSku) {
                if (str_contains(strtoupper($variantSku), strtoupper($parentSku))) {
                    $result[$parentSku][$variantSku] = (int) ($row['qty_ordered'] ?? 0);
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Group raw stock rows under their matching parent SKU, summing duplicate
     * variant SKUs (ERP may return the same SKU across multiple warehouses).
     *
     * @return array<string, list<array{sku:string, qty:int}>>
     */
    private function mapStockRows(iterable $rows, Collection $skus): array
    {
        $accumulated = []; // ['PARENT_SKU']['VARIANT_SKU'] = total_qty
        foreach ($rows as $row) {
            $variantSku = $row['sku'] ?? '';
            foreach ($skus as $parentSku) {
                if (str_contains(strtoupper($variantSku), strtoupper($parentSku))) {
                    $accumulated[$parentSku][$variantSku] = ($accumulated[$parentSku][$variantSku] ?? 0)
                        + (int) ($row['qty_on_hand'] ?? 0);
                    break;
                }
            }
        }

        $result = [];
        foreach ($accumulated as $parentSku => $variants) {
            foreach ($variants as $sku => $qty) {
                $result[$parentSku][] = ['sku' => $sku, 'qty' => $qty];
            }
        }

        return $result;
    }

    /**
     * Fetch 30-day TikTok sales totals for a list of parent SKUs.
     * Fires concurrent HTTP requests via Http::pool().
     *
     * @param  string[]  $parentSkus
     * @return array<string, int>  Keyed by parent SKU, value = total qty sold.
     */
    public function getTiktokSalesByParentSkus(array $parentSkus): array
    {
        if (empty($parentSkus)) {
            return [];
        }

        $skus = collect($parentSkus)->unique()->filter()->values();

        try {
            $responses = Http::pool(fn ($pool) => $skus->map(
                fn ($sku) => $pool->as($sku)
                    ->withoutVerifying()
                    ->timeout(15)
                    ->post("{$this->baseUrl}/api/tiktok/sku-qty", ['sku' => $sku])
            )->all());
        } catch (\Throwable $e) {
            Log::error('TikTok sales API exception', ['message' => $e->getMessage()]);
            return [];
        }

        $result = [];
        foreach ($skus as $sku) {
            $resp = $responses[$sku] ?? null;
            if (!$resp || $resp instanceof \Throwable || $resp->failed()) {
                $result[$sku] = 0;
                continue;
            }
            $result[$sku] = $this->sumQtyResponse($resp->json());
        }

        return $result;
    }

    /**
     * Fetch per-variant TikTok sales (7-day and 30-day) for a list of parent SKUs.
     * One concurrent request per SKU; the API returns qty_7d and qty_30d in one response.
     *
     * @param  string[]  $parentSkus
     * @return array<string, array{7d: array<string,int>, 30d: array<string,int>}>
     */
    public function getVariantSalesByParentSkus(array $parentSkus): array
    {
        if (empty($parentSkus)) {
            return [];
        }

        $skus = collect($parentSkus)->unique()->filter()->values();

        try {
            $responses = Http::pool(fn ($pool) => $skus->map(
                fn ($sku) => $pool->as($sku)
                    ->withoutVerifying()
                    ->timeout(15)
                    ->post("{$this->baseUrl}/api/tiktok/sku-qty", ['sku' => $sku])
            )->all());
        } catch (\Throwable $e) {
            Log::error('TikTok variant sales API exception', ['message' => $e->getMessage()]);
            return [];
        }

        $result = [];
        foreach ($skus as $sku) {
            $resp = $responses[$sku] ?? null;
            if (!$resp || $resp instanceof \Throwable || $resp->failed()) {
                $result[$sku] = ['7d' => [], '30d' => []];
                continue;
            }
            $result[$sku] = $this->parseVariantSalesResponse($resp->json());
        }

        return $result;
    }

    /**
     * Parse API response: [{"sku":"...","qty_7d":N,"qty_30d":N,...}, ...]
     *
     * @return array{7d: array<string,int>, 30d: array<string,int>}
     */
    private function parseVariantSalesResponse(mixed $json): array
    {
        $result = ['today' => [], 'yesterday' => [], '7d' => [], '30d' => [], '90d' => []];

        if (!is_array($json) || !array_is_list($json)) {
            return $result;
        }

        foreach ($json as $row) {
            if (!isset($row['sku'])) {
                continue;
            }
            $sku = $row['sku'];
            $result['today'][$sku]     = (int) ($row['qty_today']     ?? 0);
            $result['yesterday'][$sku] = (int) ($row['qty_yesterday'] ?? 0);
            $result['7d'][$sku]        = (int) ($row['qty_7d']        ?? 0);
            $result['30d'][$sku]       = (int) ($row['qty_30d']       ?? 0);
            $result['90d'][$sku]       = (int) ($row['qty_90d']       ?? 0);
        }

        return $result;
    }

    private function sumQtyResponse(mixed $json): int
    {
        if (is_int($json) || is_float($json)) {
            return (int) $json;
        }
        if (!is_array($json)) {
            return 0;
        }
        // Shape: {"PTAA-S": 45, "PTAA-M": 78}
        if (!array_is_list($json)) {
            // Single-value keys like {"total": 150} or {"qty": 150}
            if (count($json) === 1) {
                return (int) array_values($json)[0];
            }
            return (int) array_sum(array_values($json));
        }
        // Shape: [{"sku": "PTAA-S", "qty": 45}, ...]
        return (int) array_sum(array_column($json, 'qty'));
    }

    public function query(string $sql): Collection
    {
        try {
            $response = Http::withoutVerifying()->timeout(20)
                ->post("{$this->baseUrl}/api/db/query", ['query' => $sql]);

            if ($response->failed()) {
                Log::error('ERP API error', ['status' => $response->status(), 'body' => $response->body()]);
                return collect();
            }

            return collect($this->extractRows($response->json()));
        } catch (\Throwable $e) {
            Log::error('ERP API exception', ['message' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Lookup HPP dari tabel lokal sku_hpp berdasarkan variant SKU.
     *
     * @param  string[]  $variantSkus
     * @return array<string, int>  [variantSku => hpp]
     */
    public static function getHppMap(array $variantSkus): array
    {
        if (empty($variantSkus)) {
            return [];
        }

        return SkuHpp::whereIn('sku_code', array_unique($variantSkus))
            ->pluck('hpp', 'sku_code')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }

    /**
     * Normalize common ERP response shapes into a list of rows:
     * a bare list, {data: [...]}, or {rows: [...]}.
     *
     * @return list<array<string, mixed>>
     */
    private function extractRows(mixed $json): array
    {
        if (is_array($json) && array_is_list($json)) {
            return $json;
        }
        if (isset($json['data']) && is_array($json['data'])) {
            return $json['data'];
        }
        if (isset($json['rows']) && is_array($json['rows'])) {
            return $json['rows'];
        }

        return [];
    }
}
