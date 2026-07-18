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
     *   'prices' => [store_id => int|null],   // hanya promotion_price > 0
     *   'min' => int|null,
     *   'max' => int|null,
     *   'selisih' => int,
     *   'mode' => int|null,
     *   'has_diff' => bool,
     * ]
     */
    public function buildRows(?string $search = null, bool $diffOnly = false): Collection
    {
        $search = trim((string) $search);

        $raw = DB::table('tiktok_listing_skus as ts')
            ->join('jubelio_inventory as j', 'j.sku_code', '=', 'ts.sku_code')
            ->leftJoin('store_sku_prices as p', function ($q) {
                $q->on('p.sku_code', '=', 'ts.sku_code')
                    ->on('p.store_id', '=', 'ts.store_id');
            })
            ->select('ts.store_id', 'ts.sku_code', 'j.variation_label', 'j.hpp', 'p.promotion_price')
            ->distinct()
            ->when($search !== '', fn ($q) => $q->where('ts.sku_code', 'like', "%{$search}%"))
            ->get();

        $rows = $raw->groupBy('sku_code')->map(function (Collection $group) {
            $first = $group->first();

            $prices = [];
            foreach ($group as $r) {
                $price = (int) $r->promotion_price;
                $prices[(int) $r->store_id] = $price > 0 ? $price : null;
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

        return $rows->sortBy([
            ['selisih', 'desc'],
            ['sku_code', 'asc'],
        ])->values();
    }

    public function stores(): Collection
    {
        return Store::select('id', 'name')->orderBy('name')->get();
    }
}
