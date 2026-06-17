<?php

namespace App\Services;

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
        $conditions = $skus->map(fn($sku) => "sku LIKE '%{$sku}%'")->implode(' OR ');
        $sql = "SELECT sku, qty_on_hand FROM purchase.stock_current WHERE {$conditions} ORDER BY sku";

        $rows = $this->query($sql);

        // Accumulate qty per exact SKU first (ERP may return same SKU across multiple warehouses)
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

        // Flatten to indexed arrays
        $result = [];
        foreach ($accumulated as $parentSku => $variants) {
            foreach ($variants as $sku => $qty) {
                $result[$parentSku][] = ['sku' => $sku, 'qty' => $qty];
            }
        }

        return $result;
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

            $json = $response->json();

            // handle common response shapes: array, {data:[...]}, {rows:[...]}
            if (is_array($json) && array_is_list($json)) {
                return collect($json);
            }
            if (isset($json['data']) && is_array($json['data'])) {
                return collect($json['data']);
            }
            if (isset($json['rows']) && is_array($json['rows'])) {
                return collect($json['rows']);
            }

            return collect();
        } catch (\Throwable $e) {
            Log::error('ERP API exception', ['message' => $e->getMessage()]);
            return collect();
        }
    }
}
