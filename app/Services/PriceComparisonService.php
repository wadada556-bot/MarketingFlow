<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PriceComparisonService
{
    /**
     * Baris pivot: satu baris per sku_code, satu nilai harga per store_id.
     *
     * Struktur tiap baris:
     * [
     *   'sku_code' => string,
     *   'variasi' => string|null,
     *   'hpp' => int,
     *   'prices' => [store_id => int|null],   // MIN promotion_price > 0 antar listing
     *   'diverges' => [store_id => bool],      // true bila listing-listing
     *                                           // (product_id) SKU itu di toko
     *                                           // itu punya harga tak seragam
     *   'min' => int|null,
     *   'max' => int|null,
     *   'selisih' => int,
     *   'mode' => int|null,
     *   'has_diff' => bool,
     * ]
     */
    public function buildRows(?string $search = null, bool $diffOnly = false, string $sortKey = 'selisih', string $sortDir = 'desc'): Collection
    {
        $search = trim((string) $search);

        // Satu baris per LISTING (store, product_id, sku) + harga promo per
        // listing dari tiktok_listing_prices. Nilai sel per toko = MIN harga>0
        // antar listing SKU itu; indikator `diverges` = listing-listing SKU itu
        // di toko itu punya harga tidak seragam.
        $raw = DB::table('tiktok_listing_skus as ts')
            ->join('tiktok_listings as tl', 'tl.id', '=', 'ts.listing_id')
            ->join('products as pr', 'pr.id', '=', 'ts.product_id')
            ->leftJoin('tiktok_listing_prices as p', function ($q) {
                $q->on('p.listing_id', '=', 'tl.id')
                    ->on('p.product_id', '=', 'ts.product_id');
            })
            ->select('tl.store_id', 'pr.sku_code', 'pr.variation_label', 'pr.hpp', 'p.promotion_price')
            ->when($search !== '', fn ($q) => $q->where('pr.sku_code', 'like', "%{$search}%"))
            ->get();

        $rows = $raw->groupBy('sku_code')->map(function (Collection $group) {
            $first = $group->first();

            $prices = [];
            $rowDiverges = [];
            foreach ($group->groupBy('store_id') as $sid => $listingRows) {
                $sid = (int) $sid;
                $values = $listingRows->pluck('promotion_price')
                    ->map(fn ($v) => $v !== null ? (int) $v : null);
                $positive = $values->filter(fn ($v) => $v !== null && $v > 0);

                $prices[$sid] = $positive->isNotEmpty() ? $positive->min() : null;
                $rowDiverges[$sid] = $values->unique()->count() > 1;
            }

            $nonNull = array_filter($prices, fn ($v) => $v !== null);

            $min = $nonNull === [] ? null : min($nonNull);
            $max = $nonNull === [] ? null : max($nonNull);
            $selisih = ($min !== null && $max !== null) ? $max - $min : 0;

            $mode = null;
            if ($nonNull !== []) {
                $counts = array_count_values($nonNull);
                arsort($counts);
                $mode = (int) array_key_first($counts);
            }

            return [
                'sku_code' => $first->sku_code,
                'variasi' => $first->variation_label,
                'hpp' => (int) $first->hpp,
                'prices' => $prices,
                'diverges' => $rowDiverges,
                'min' => $min,
                'max' => $max,
                'selisih' => $selisih,
                'mode' => $mode,
                'has_diff' => count(array_unique($nonNull)) > 1,
            ];
        })->values();

        if ($diffOnly) {
            $rows = $rows->filter(fn ($row) => $row['has_diff'])->values();
        }

        return $rows->sort(function (array $a, array $b) use ($sortKey, $sortDir) {
            $cmp = $this->sortValue($a, $sortKey) <=> $this->sortValue($b, $sortKey);

            return $sortDir === 'asc' ? $cmp : -$cmp;
        })->values();
    }

    public function stores(): Collection
    {
        return Store::select('id', 'name')->orderBy('name')->get();
    }

    /** Total SKU seller yang punya listing TikTok, tanpa filter search/diffOnly. */
    public function totalSkuCount(): int
    {
        return DB::table('tiktok_listing_skus')
            ->distinct()
            ->count('product_id');
    }

    private function sortValue(array $row, string $sortKey): string|int
    {
        if ($sortKey === 'sku') {
            return $row['sku_code'];
        }
        if ($sortKey === 'variasi') {
            return $row['variasi'] ?? '';
        }
        if ($sortKey === 'hpp') {
            return $row['hpp'];
        }
        if ($sortKey === 'min') {
            return $row['min'] ?? -PHP_INT_MAX;
        }
        if ($sortKey === 'max') {
            return $row['max'] ?? -PHP_INT_MAX;
        }
        if (str_starts_with($sortKey, 'store:')) {
            $storeId = (int) substr($sortKey, 6);

            return $row['prices'][$storeId] ?? -PHP_INT_MAX;
        }

        return $row['selisih'];
    }
}
