<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\DailySalesQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request, DailySalesQueryService $salesService)
    {
        $search = trim((string) $request->input('search'));

        // Toko untuk pemilih harga+penjualan (berbeda per toko)
        $stores  = Store::select('id', 'name', 'jubelio_store_id')->orderBy('name')->get();
        $storeId = $request->integer('store_id') ?: optional($stores->first())->id;
        $jubelioStoreId = optional($stores->firstWhere('id', $storeId))->jubelio_store_id;

        // Katalog produk induk dari jubelio_inventory (DISTINCT parent_sku),
        // dengan jumlah varian + total stok. Menggantikan daftar dari tabel
        // products yang tipis; products tetap dipakai sbg anchor iklan.
        $catalog = DB::table('jubelio_inventory')
            ->select(
                'parent_sku',
                DB::raw('COUNT(*) as variant_count'),
                DB::raw('SUM(stok) as total_stok'),
            )
            ->when($search !== '', fn ($q) => $q->where('parent_sku', 'like', "%{$search}%"))
            ->groupBy('parent_sku')
            ->orderByRaw('SUM(stok) DESC')
            ->orderBy('parent_sku')
            ->paginate(15)
            ->withQueryString();

        // Rentang harga (min–max) per induk utk toko terpilih — hanya induk di
        // halaman ini. Harga per varian: join store_sku_prices by sku_code.
        // NULLIF(...,0) → abaikan 0 (promotion_price 0 = tidak ada campaign).
        $prices = collect();
        if ($storeId) {
            $parents = collect($catalog->items())->pluck('parent_sku')->all();
            if (! empty($parents)) {
                $prices = DB::table('jubelio_inventory as j')
                    ->join('store_sku_prices as p', function ($x) use ($storeId) {
                        $x->on('p.sku_code', '=', 'j.sku_code')->where('p.store_id', $storeId);
                    })
                    ->whereIn('j.parent_sku', $parents)
                    ->groupBy('j.parent_sku')
                    ->selectRaw('j.parent_sku,
                        MIN(NULLIF(p.retail_price, 0))    as retail_min,
                        MAX(NULLIF(p.retail_price, 0))    as retail_max,
                        MIN(NULLIF(p.promotion_price, 0)) as promo_min,
                        MAX(NULLIF(p.promotion_price, 0)) as promo_max')
                    ->get()
                    ->keyBy('parent_sku');
            }
        }

        // Penjualan 30 hari (toko terpilih) per induk — untuk kolom ringkas.
        $sales = collect();
        if ($jubelioStoreId && ! empty($parents)) {
            $salesData = $salesService->getForParentSkus($parents);
            foreach ($parents as $pk) {
                $byVariant = $salesData['stores'][$pk][$jubelioStoreId]['sales']['30d'] ?? [];
                $sales[$pk] = array_sum($byVariant);
            }
        }

        return view('products.index', compact('catalog', 'search', 'stores', 'storeId', 'prices', 'sales'));
    }

    /**
     * Detail varian satu produk induk (untuk expand baris di menu katalog),
     * di-load lazy via AJAX. Stok/PO/HPP dari jubelio_inventory + harga per
     * varian dari store_sku_prices (toko terpilih, left join → null bila tak ada).
     */
    public function variants(Request $request, DailySalesQueryService $salesService)
    {
        $parent  = trim((string) $request->input('parent', ''));
        $storeId = $request->integer('store_id');

        if ($parent === '') {
            return response()->json(['variants' => [], 'sales_by_period' => []]);
        }

        $rows = DB::table('jubelio_inventory as j')
            ->leftJoin('store_sku_prices as p', function ($x) use ($storeId) {
                $x->on('p.sku_code', '=', 'j.sku_code')->where('p.store_id', $storeId);
            })
            ->where('j.parent_sku', $parent)
            ->orderBy('j.sku_code')
            ->get([
                'j.sku_code', 'j.variation_label', 'j.stok', 'j.po_qty', 'j.hpp',
                'p.retail_price', 'p.promotion_price',
            ]);

        // Penjualan (toko terpilih) per periode + per varian.
        $jubelioStoreId = Store::where('id', $storeId)->value('jubelio_store_id');
        $periods        = ['today', 'yesterday', '7d', '30d', '90d'];
        $salesByPeriod  = array_fill_keys($periods, 0);
        $soldPerVariant = [];  // [sku_variant][period] => qty
        if ($jubelioStoreId) {
            $data      = $salesService->getForParentSkus([$parent]);
            $byStore   = $data['stores'][$parent][$jubelioStoreId]['sales'] ?? [];
            foreach ($periods as $per) {
                $bySku = $byStore[$per] ?? [];
                $salesByPeriod[$per] = array_sum($bySku);
                foreach ($bySku as $sku => $qty) {
                    $soldPerVariant[$sku][$per] = (int) $qty;
                }
            }
        }

        return response()->json([
            'sales_by_period' => $salesByPeriod,
            'variants' => $rows->map(fn ($r) => [
                'sku'    => $r->sku_code,
                'label'  => $r->variation_label,
                'stok'   => (int) $r->stok,
                'po'     => (int) $r->po_qty,
                'hpp'    => (int) $r->hpp,
                'retail' => $r->retail_price !== null ? (int) $r->retail_price : null,
                'promo'  => $r->promotion_price !== null ? (int) $r->promotion_price : null,
                'sold30' => $soldPerVariant[$r->sku_code]['30d'] ?? 0,
            ])->all(),
        ]);
    }
}
