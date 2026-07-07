<?php

namespace App\Http\Controllers;

use App\Exports\StoreProductsExport;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search   = trim((string) $request->input('search'));
        $hppEmpty = $request->boolean('hpp_empty');

        // Toko untuk pemilih harga (harga jual berbeda per toko)
        $stores  = Store::select('id', 'name', 'jubelio_store_id')->orderBy('name')->get();
        $storeId = $request->integer('store_id') ?: optional($stores->first())->id;

        // Katalog DI-GROUP per LISTING TikTok (tiktok_listings.product_id) untuk
        // toko terpilih. Satu product_id = satu baris. variant_count & total_stok
        // = varian (sku_id) pada listing itu. Search cocok bila product_id cocok
        // ATAU salah satu varian (parent_sku/sku_code) cocok — agregat tetap penuh.
        $catalog = DB::table('tiktok_listings as tl')
            ->join('tiktok_listing_skus as ts', function ($x) use ($storeId) {
                $x->on('ts.listing_id', '=', 'tl.id')->where('ts.store_id', $storeId);
            })
            ->join('jubelio_inventory as j', 'j.sku_code', '=', 'ts.sku_code')
            ->where('tl.store_id', $storeId)
            ->when($search !== '', function ($q) use ($search, $storeId) {
                $q->where(function ($outer) use ($search, $storeId) {
                    $outer->where('tl.product_id', 'like', "%{$search}%")
                        ->orWhereExists(function ($sub) use ($search, $storeId) {
                            $sub->from('tiktok_listing_skus as ts2')
                                ->join('jubelio_inventory as j2', 'j2.sku_code', '=', 'ts2.sku_code')
                                ->whereColumn('ts2.listing_id', 'tl.id')
                                ->where('ts2.store_id', $storeId)
                                ->where(function ($w) use ($search) {
                                    $w->where('j2.parent_sku', 'like', "%{$search}%")
                                        ->orWhere('ts2.sku_code', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($hppEmpty, function ($q) use ($storeId) {
                // Hanya listing yang punya ≥1 varian dengan HPP belum terisi (=0).
                $q->whereExists(function ($sub) use ($storeId) {
                    $sub->from('tiktok_listing_skus as tse')
                        ->join('jubelio_inventory as je', 'je.sku_code', '=', 'tse.sku_code')
                        ->whereColumn('tse.listing_id', 'tl.id')
                        ->where('tse.store_id', $storeId)
                        ->where('je.hpp', '=', 0);
                });
            })
            ->select(
                'tl.product_id',
                DB::raw('COUNT(*) as variant_count'),
                DB::raw('SUM(j.stok) as total_stok'),
            )
            ->groupBy('tl.product_id')
            ->orderByRaw('SUM(j.stok) DESC')
            ->orderBy('tl.product_id')
            ->paginate(15)
            ->withQueryString();

        // Meta per listing (hanya halaman ini): label SKU induk + rentang harga.
        // SKU induk = sku bernomor terkecil per base (parent_sku); bila 1 listing
        // campur base → gabung "BASE1-1 + BASE2-1" urut kemunculan (sku_id naik).
        $meta = collect();
        if ($storeId) {
            $pids = collect($catalog->items())->pluck('product_id')->all();
            if (! empty($pids)) {
                $vrows = DB::table('tiktok_listings as tl')
                    ->join('tiktok_listing_skus as ts', function ($x) use ($storeId) {
                        $x->on('ts.listing_id', '=', 'tl.id')->where('ts.store_id', $storeId);
                    })
                    ->join('jubelio_inventory as j', 'j.sku_code', '=', 'ts.sku_code')
                    ->leftJoin('store_sku_prices as p', function ($x) use ($storeId) {
                        $x->on('p.sku_code', '=', 'j.sku_code')->where('p.store_id', $storeId);
                    })
                    ->where('tl.store_id', $storeId)
                    ->whereIn('tl.product_id', $pids)
                    ->orderBy('ts.sku_id')
                    ->get(['tl.product_id', 'ts.sku_code', 'p.retail_price', 'p.promotion_price']);

                $meta = $vrows->groupBy('product_id')->map(function ($rows) {
                    $perBase = [];   // base => [sku_code, num] (urut kemunculan)
                    foreach ($rows as $r) {
                        [$base, $num] = self::variantKey($r->sku_code);
                        if (! isset($perBase[$base]) || $num < $perBase[$base][1]) {
                            $perBase[$base] = [$r->sku_code, $num];
                        }
                    }
                    $retail = $rows->pluck('retail_price')->filter(fn ($v) => $v > 0);
                    $promo  = $rows->pluck('promotion_price')->filter(fn ($v) => $v > 0);

                    return (object) [
                        'induk'          => implode(' + ', array_map(fn ($x) => $x[0], array_values($perBase))),
                        'primary_parent' => array_key_first($perBase),
                        'retail_min'     => $retail->min(),
                        'retail_max'     => $retail->max(),
                        'promo_min'      => $promo->min(),
                        'promo_max'      => $promo->max(),
                    ];
                });
            }
        }

        // Kebaruan data per sumber (untuk toko terpilih), agar user tahu seberapa
        // baru data sebelum export. synced_at diisi importer; fallback updated_at.
        // Stok/HPP global (master SKU); harga & listing per toko.
        $freshFmt = function ($ts) {
            if (! $ts) {
                return ['rel' => 'belum ada', 'exact' => null];
            }
            $c = Carbon::parse($ts)->locale('id');

            return ['rel' => $c->diffForHumans(), 'exact' => $c->isoFormat('D MMM YYYY, HH:mm')];
        };
        $freshness = [
            ['label' => 'Stok & HPP', 'hint' => 'dari Jubelio'] + $freshFmt(
                DB::table('jubelio_inventory')->max(DB::raw('COALESCE(synced_at, updated_at)'))
            ),
            ['label' => 'Harga diskon', 'hint' => 'scrape Tokopedia'] + $freshFmt(
                DB::table('store_sku_prices')->where('store_id', $storeId)->max(DB::raw('COALESCE(synced_at, updated_at)'))
            ),
            ['label' => 'ID Produk/SKU TikTok', 'hint' => 'import TikTok Seller Center'] + $freshFmt(
                DB::table('tiktok_listings')->where('store_id', $storeId)->max(DB::raw('COALESCE(synced_at, updated_at)'))
            ),
        ];

        return view('products.index', compact('catalog', 'search', 'hppEmpty', 'stores', 'storeId', 'meta', 'freshness'));
    }

    /**
     * Detail varian satu produk induk (untuk expand baris di menu katalog),
     * di-load lazy via AJAX. Stok/PO/HPP dari jubelio_inventory + harga per
     * varian dari store_sku_prices (toko terpilih, left join → null bila tak ada).
     */
    public function variants(Request $request)
    {
        $productId = trim((string) $request->input('product_id', ''));
        $storeId   = $request->integer('store_id');
        $hppEmpty  = $request->boolean('hpp_empty');

        if ($productId === '') {
            return response()->json(['variants' => []]);
        }

        // DI-SCOPE ke satu LISTING (product_id) pada toko terpilih. Varian = sku_id
        // pada listing itu. product_id/sku_id selalu terisi (tak ada '-').
        $rows = DB::table('tiktok_listings as tl')
            ->join('tiktok_listing_skus as ts', function ($x) use ($storeId) {
                $x->on('ts.listing_id', '=', 'tl.id')->where('ts.store_id', $storeId);
            })
            ->join('jubelio_inventory as j', 'j.sku_code', '=', 'ts.sku_code')
            ->leftJoin('store_sku_prices as p', function ($x) use ($storeId) {
                $x->on('p.sku_code', '=', 'j.sku_code')->where('p.store_id', $storeId);
            })
            ->where('tl.store_id', $storeId)
            ->where('tl.product_id', $productId)
            ->when($hppEmpty, fn ($q) => $q->where('j.hpp', '=', 0))
            ->orderBy('ts.sku_id')
            ->get([
                'j.sku_code', 'j.variation_label', 'j.stok', 'j.po_qty', 'j.hpp',
                'p.retail_price', 'p.promotion_price',
                'tl.product_id', 'ts.sku_id',
            ]);

        return response()->json([
            'variants' => $rows->map(fn ($r) => [
                // bigint TikTok (~19 digit) → kirim sbg string agar presisi tak
                // hilang di JS (Number.MAX_SAFE_INTEGER = 2^53).
                'product_id' => $r->product_id !== null ? (string) $r->product_id : null,
                'sku_id'     => $r->sku_id !== null ? (string) $r->sku_id : null,
                'sku'    => $r->sku_code,
                'label'  => $r->variation_label,
                'stok'   => (int) $r->stok,
                'po'     => (int) $r->po_qty,
                'hpp'    => (int) $r->hpp,
                'retail' => $r->retail_price !== null ? (int) $r->retail_price : null,
                'promo'  => $r->promotion_price !== null ? (int) $r->promotion_price : null,
            ])->all(),
        ]);
    }

    /**
     * Riwayat perubahan harga satu varian (SKU) pada toko terpilih, untuk aksi
     * "Lihat histori harga" di detail varian. Data dari store_sku_price_histories
     * (diisi trigger DB tiap harga berubah saat sync). Urut terbaru dulu.
     */
    public function priceHistory(Request $request)
    {
        $skuCode = trim((string) $request->input('sku_code', ''));
        $storeId = $request->integer('store_id');

        if ($skuCode === '' || ! $storeId) {
            return response()->json(['changes' => []]);
        }

        $rows = DB::table('store_sku_price_histories')
            ->where('store_id', $storeId)
            ->where('sku_code', $skuCode)
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->get(['price_type', 'old_price', 'new_price', 'changed_at']);

        return response()->json([
            'sku_code' => $skuCode,
            'changes'  => $rows->map(fn ($r) => [
                'type'       => $r->price_type, // 'retail' | 'promotion'
                'old'        => (int) $r->old_price,
                'new'        => (int) $r->new_price,
                'changed_at' => Carbon::parse($r->changed_at)->format('d M Y H:i'),
            ])->all(),
        ]);
    }

    /**
     * Export .xlsx katalog SELURUH toko terpilih (semua listing+varian), parent_sku
     * (label induk) di tiap baris. Nama file: products_{toko}_{tgl}_{jam}.xlsx.
     */
    public function export(Request $request)
    {
        $storeId = $request->integer('store_id') ?: optional(Store::orderBy('name')->first())->id;
        $store   = Store::find($storeId);
        abort_unless($store, 404);

        $filename = 'products_' . Str::slug($store->name, '_') . '_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new StoreProductsExport($storeId), $filename);
    }

    /**
     * Isi/ubah HPP satu SKU secara manual (dipakai untuk produk bundling yang
     * HPP-nya tidak tersedia di Jubelio). Nilai ini tak akan tertimpa saat sync
     * selama Jubelio mengirim 0 (lihat SyncJubelioInventory). hpp = 0 berarti
     * "belum terisi".
     */
    public function updateHpp(Request $request)
    {
        $data = $request->validate([
            'sku_code' => ['required', 'string', 'exists:jubelio_inventory,sku_code'],
            'hpp'      => ['required', 'integer', 'min:0'],
        ]);

        DB::table('jubelio_inventory')
            ->where('sku_code', $data['sku_code'])
            ->update(['hpp' => $data['hpp'], 'updated_at' => now()]);

        return response()->json([
            'ok'       => true,
            'sku_code' => $data['sku_code'],
            'hpp'      => (int) $data['hpp'],
        ]);
    }

    /**
     * Kunci varian dari sku_code (BUKAN jubelio_inventory.parent_sku yg tak konsisten).
     * Pisahkan RUN DIGIT TERAKHIR sbg nomor: base = sisa sebelum+sesudah nomor (huruf
     * setelah nomor dipertahankan → ZQ-1 vs ZQ-1A beda base). Tanpa nomor (ZL-RANDOM)
     * → base = sebelum '-' terakhir (segrup dgn ZL-1). Kembalikan [base, nomor].
     */
    public static function variantKey(string $sku): array
    {
        $sku = trim($sku);
        // (.*[^\d])? memastikan \d+ menangkap run digit TERAKHIR penuh (T01-PTAA-10 → 10).
        if (preg_match('/^(.*[^\d])?(\d+)(\D*)$/', $sku, $m)) {
            $base = $m[3] === '' ? rtrim($m[1], '-') : $m[1] . $m[3];

            return [strtoupper($base), (int) $m[2]];
        }
        $pos = strrpos($sku, '-');

        return [strtoupper($pos !== false ? substr($sku, 0, $pos) : $sku), PHP_INT_MAX];
    }

    /**
     * Label "SKU induk" satu listing: wakil bernomor terkecil per base, digabung
     * " + " urut kemunculan. Mis. [T01-PTAA-10, T01-PTAA-1, TL003, TL002] → "T01-PTAA-1 + TL002".
     */
    public static function indukLabel(iterable $skuCodes): string
    {
        $perBase = [];
        foreach ($skuCodes as $sku) {
            [$base, $num] = self::variantKey($sku);
            if (! isset($perBase[$base]) || $num < $perBase[$base][1]) {
                $perBase[$base] = [$sku, $num];
            }
        }

        return implode(' + ', array_map(fn ($x) => $x[0], array_values($perBase)));
    }
}
