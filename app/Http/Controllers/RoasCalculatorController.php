<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoasCalculatorController extends Controller
{
    public function index()
    {
        return view('roas-calculator.index');
    }

    public function lookup(Request $request)
    {
        $productId = $request->input('product_id');

        if (!$productId) {
            return response()->json(['found' => false, 'message' => 'Product ID diperlukan'], 400);
        }

        // 1. Resolve store and product name from tiktok_listings
        $listing = DB::table('tiktok_listings as tl')
            ->join('stores as s', 's.id', '=', 'tl.store_id')
            ->where('tl.product_id', $productId)
            ->select('tl.id as listing_id', 'tl.store_id', 's.name as store_name', 'tl.product_name')
            ->first();

        if (!$listing) {
            return response()->json(['found' => false, 'message' => 'Product ID tidak ditemukan'], 404);
        }

        // 2. Ambil semua varian
        $variants = DB::table('tiktok_listing_skus as ts')
            ->join('jubelio_inventory as j', 'j.sku_code', '=', 'ts.sku_code')
            ->leftJoin('store_sku_prices as p', function ($join) use ($listing) {
                $join->on('p.sku_code', '=', 'j.sku_code')
                     ->where('p.store_id', '=', $listing->store_id);
            })
            ->where('ts.listing_id', $listing->listing_id)
            ->select(
                'ts.sku_code',
                'j.variation_label',
                'j.hpp',
                'j.stok',
                'p.retail_price',
                'p.promotion_price'
            )
            ->get();

        if ($variants->isEmpty()) {
            return response()->json(['found' => false, 'message' => 'Varian produk tidak ditemukan'], 404);
        }

        // Format data agar propertiproperti bertipe int
        $formattedVariants = $variants->map(function ($v) {
            return [
                'sku_code' => $v->sku_code,
                'variation_label' => $v->variation_label ?: 'N/A',
                'hpp' => (int) $v->hpp,
                'stok' => (int) $v->stok,
                'retail_price' => (int) $v->retail_price,
                'promotion_price' => (int) $v->promotion_price,
            ];
        });

        // 3. Pilih varian dengan HPP tertinggi
        $selectedVariant = $formattedVariants->sortByDesc('hpp')->first();

        // 4. Harga jual = promotion_price jika > 0, else retail_price
        $hargaJual = ($selectedVariant['promotion_price'] > 0)
            ? $selectedVariant['promotion_price']
            : $selectedVariant['retail_price'];
        
        $selectedVariant['harga_jual'] = $hargaJual;

        // 5. Cek ROI terbaru dari ad_weekly_performances
        $latestAd = DB::table('ads')
            ->join('ad_weekly_performances as awp', 'awp.ad_id', '=', 'ads.id')
            ->where('ads.product_id', $productId)
            ->where('ads.store_id', $listing->store_id)
            ->orderBy('awp.period_start', 'desc')
            ->select('awp.roi')
            ->first();

        $latestRoi = $latestAd ? (float) $latestAd->roi : null;

        return response()->json([
            'found' => true,
            'store_name' => $listing->store_name,
            'product_name' => $listing->product_name,
            'selected_variant' => $selectedVariant,
            'all_variants' => $formattedVariants->values()->all(),
            'variant_count' => $formattedVariants->count(),
            'latest_roi' => $latestRoi,
        ]);
    }
}
