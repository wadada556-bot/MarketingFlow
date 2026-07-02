<?php

namespace App\Services;

use App\Support\SkuMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Baca penjualan dari tabel lokal `orders` (sync pesanan masuk dari Jubelio,
 * lihat OrderSyncService) untuk kebutuhan Product Ads — gantikan endpoint
 * ERP lama `/api/tiktok/sku-qty`.
 */
class DailySalesQueryService
{
    private const PERIODS = ['today', 'yesterday', '7d', '30d', '90d'];

    /**
     * @param  string[]  $parentSkus
     * @return array{
     *     total: array<string, array<string, array<string, int>>>,
     *     stores: array<string, array<int, array{name: string, sales: array<string, array<string, int>>}>>
     * }
     *     total  : [parentSku][period][variantSku] => qty (semua toko dijumlah)
     *     stores : [parentSku][store_id] => ['name' => ..., 'sales' => [period][variantSku] => qty]
     */
    public function getForParentSkus(array $parentSkus, int $channelId = 131076): array
    {
        if (empty($parentSkus)) {
            return ['total' => [], 'stores' => []];
        }

        $today   = Carbon::today();
        $ranges  = [
            'today'     => [$today, $today],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            '7d'        => [$today->copy()->subDays(6), $today],
            '30d'       => [$today->copy()->subDays(29), $today],
            '90d'       => [$today->copy()->subDays(89), $today],
        ];

        // `orders` row-level (1 baris = 1 unit); agregasi di SQL. Cocokkan varian
        // ke produk berbasis PREFIX kode (sku_variant diawali parent_sku) — konvensi
        // sku_parter lama (strip "-\d+$") tak menangkap SKU spt HTT1/BM-LCAZD01.
        // Lihat App\Support\SkuMatch.
        $query = DB::table('orders')
            ->where('channel_id', $channelId)
            ->where('sales_date', '>=', $today->copy()->subDays(89)->toDateString())
            ->selectRaw('sku_variant as sku, store_id, store_name, sales_date, SUM(qty) as qty_terjual')
            ->groupBy('sku_variant', 'store_id', 'store_name', 'sales_date');
        SkuMatch::wherePrefix($query, 'sku_variant', $parentSkus);
        $rows = $query->get();

        $total  = [];
        $stores = [];

        foreach ($rows as $row) {
            // Tentukan produk pemilik varian ini (prefix terpanjang yang cocok).
            $parent = SkuMatch::owner($row->sku, $parentSkus);
            if ($parent === null) {
                continue;
            }

            $date = Carbon::parse($row->sales_date);

            foreach ($ranges as $period => [$from, $to]) {
                if (! $date->betweenIncluded($from, $to)) {
                    continue;
                }

                $total[$parent][$period][$row->sku] =
                    ($total[$parent][$period][$row->sku] ?? 0) + (int) $row->qty_terjual;

                $stores[$parent][$row->store_id]['name'] ??= $this->cleanStore($row->store_name);
                $stores[$parent][$row->store_id]['sales'][$period][$row->sku] =
                    ($stores[$parent][$row->store_id]['sales'][$period][$row->sku] ?? 0) + (int) $row->qty_terjual;
            }
        }

        // Pastikan semua period ada (walau kosong) supaya konsumen tidak perlu null-check tiap key
        foreach ($parentSkus as $sku) {
            foreach (self::PERIODS as $period) {
                $total[$sku][$period] ??= [];
            }
        }

        return ['total' => $total, 'stores' => $stores];
    }

    /**
     * Bersihkan nama toko dari prefix channel & suffix (TTS) — sama dengan
     * regex di resources/views/sales-history/index.blade.php.
     */
    private function cleanStore(?string $name): string
    {
        $clean = trim((string) preg_replace([
            '/^(?:Shop\s*\|\s*)?(?:tokopedia|tiktok)\s*-\s*/i',
            '/\s*\(TTS\)\s*$/i',
        ], '', $name ?? ''));

        return $clean !== '' ? $clean : ($name ?? '—');
    }
}
