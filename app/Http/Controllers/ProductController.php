<?php

namespace App\Http\Controllers;

use App\Exports\StoreProductsExport;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search   = trim((string) $request->input('search'));
        $hppEmpty = $request->boolean('hpp_empty');
        $perPage  = (int) $request->input('per_page', 20);
        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 20;
        }

        // Toko untuk pemilih harga (harga jual berbeda per toko)
        $stores  = Store::select('id', 'name', 'jubelio_store_id')->orderBy('name')->get();
        $storeId = $request->integer('store_id') ?: optional($stores->first())->id;

        // Katalog DI-GROUP per LISTING TikTok untuk toko terpilih. Satu listing =
        // satu baris. variant_count & total_stok = varian pada listing itu.
        // Search cocok bila tiktok_product_id cocok ATAU salah satu varian
        // (parent_sku/sku_code di master products) cocok — agregat tetap penuh.
        // Alias `product_id` (= tiktok_product_id) dipertahankan supaya Blade/JS
        // tidak berubah.
        $catalog = DB::table('tiktok_listings as tl')
            ->join('tiktok_listing_skus as ts', 'ts.listing_id', '=', 'tl.id')
            ->join('products as pr', 'pr.id', '=', 'ts.product_id')
            ->where('tl.store_id', $storeId)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($outer) use ($search) {
                    $outer->where('tl.tiktok_product_id', 'like', "%{$search}%")
                        ->orWhere('tl.model_id', 'like', "%{$search}%")
                        ->orWhereExists(function ($sub) use ($search) {
                            $sub->from('tiktok_listing_skus as ts2')
                                ->join('products as pr2', 'pr2.id', '=', 'ts2.product_id')
                                ->whereColumn('ts2.listing_id', 'tl.id')
                                ->where(function ($w) use ($search) {
                                    $w->where('pr2.parent_sku', 'like', "%{$search}%")
                                        ->orWhere('pr2.sku_code', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($hppEmpty, function ($q) {
                // Hanya listing yang punya ≥1 varian dengan HPP belum terisi (=0).
                $q->whereExists(function ($sub) {
                    $sub->from('tiktok_listing_skus as tse')
                        ->join('products as pre', 'pre.id', '=', 'tse.product_id')
                        ->whereColumn('tse.listing_id', 'tl.id')
                        ->where('pre.hpp', '=', 0);
                });
            })
            ->select(
                'tl.tiktok_product_id as product_id',
                // Saat filter "HPP belum terisi" aktif, hitung hanya varian yg
                // benar-benar tampil di tabel detail (hpp=0) — bukan total varian
                // listing — supaya angka "N SKU" konsisten dgn isi saat dibuka.
                DB::raw($hppEmpty
                    ? 'SUM(CASE WHEN pr.hpp = 0 THEN 1 ELSE 0 END) as variant_count'
                    : 'COUNT(*) as variant_count'),
                DB::raw('SUM(pr.stok) as total_stok'),
                DB::raw('SUM(pr.po_qty) as total_po'),
                DB::raw('MAX(tl.model_id) as model_id'),
            )
            ->groupBy('tl.tiktok_product_id')
            ->orderByRaw('SUM(pr.stok) DESC')
            ->orderBy('tl.tiktok_product_id')
            ->paginate($perPage)
            ->withQueryString();

        // Meta per listing (hanya halaman ini): label SKU induk + rentang harga.
        // SKU induk = sku bernomor terkecil per base (parent_sku); bila 1 listing
        // campur base → gabung "BASE1-1 + BASE2-1" urut kemunculan (sku_id naik).
        $meta = collect();
        if ($storeId) {
            $pids = collect($catalog->items())->pluck('product_id')->all();
            if (! empty($pids)) {
                $vrows = DB::table('tiktok_listings as tl')
                    ->join('tiktok_listing_skus as ts', 'ts.listing_id', '=', 'tl.id')
                    ->join('products as pr', 'pr.id', '=', 'ts.product_id')
                    ->leftJoin('tiktok_listing_prices as p', function ($x) {
                        $x->on('p.listing_id', '=', 'tl.id')
                            ->on('p.product_id', '=', 'ts.product_id');
                    })
                    ->where('tl.store_id', $storeId)
                    ->whereIn('tl.tiktok_product_id', $pids)
                    ->orderBy('ts.tiktok_sku_id')
                    ->get([
                        'tl.tiktok_product_id as product_id', 'pr.sku_code',
                        'p.retail_price', 'pr.hpp', 'p.promotion_price',
                    ]);

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
                    $hpp    = $rows->pluck('hpp')->filter(fn ($v) => $v > 0);

                    return (object) [
                        'induk'          => implode(' + ', array_map(fn ($x) => $x[0], array_values($perBase))),
                        'primary_parent' => array_key_first($perBase),
                        // ID Model bawaan (turunan sku_code) — dipakai bila model_id manual kosong.
                        'model_default'  => implode(' + ', array_keys($perBase)),
                        'retail_min'     => $retail->min(),
                        'retail_max'     => $retail->max(),
                        'promo_min'      => $promo->min(),
                        'promo_max'      => $promo->max(),
                        'hpp_min'        => $hpp->min(),
                        'hpp_max'        => $hpp->max(),
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
            ['label' => 'Stok, PO & HPP', 'hint' => 'dari Jubelio'] + $freshFmt(
                DB::table('products')->max(DB::raw('COALESCE(synced_at, updated_at)'))
            ),
            ['label' => 'Harga Promo', 'hint' => 'update manual'] + $freshFmt(
                // Patokan "kapan diperbarui" = perubahan harga NYATA terakhir
                // (histories.changed_at, diisi trigger hanya saat nilai berubah).
                // Fallback ke updated_at harga bila toko ini belum pernah punya
                // histori (trigger cuma jalan di UPDATE).
                DB::table('tiktok_listing_price_histories')->where('store_id', $storeId)->max('changed_at')
                    ?? DB::table('tiktok_listing_prices as p')
                        ->join('tiktok_listings as tl', 'tl.id', '=', 'p.listing_id')
                        ->where('tl.store_id', $storeId)->max('p.updated_at')
            ),
            ['label' => 'Product ID TikTok', 'hint' => 'import TikTok Seller Center'] + $freshFmt(
                DB::table('tiktok_listings')->where('store_id', $storeId)->max('created_at')
            ),
            ['label' => 'SKU ID TikTok', 'hint' => 'import TikTok Seller Center'] + $freshFmt(
                DB::table('tiktok_listing_skus as ts')
                    ->join('tiktok_listings as tl', 'tl.id', '=', 'ts.listing_id')
                    ->where('tl.store_id', $storeId)->max('ts.created_at')
            ),
        ];

        return view('products.index', compact('catalog', 'search', 'hppEmpty', 'stores', 'storeId', 'meta', 'freshness', 'perPage'));
    }

    /**
     * Detail varian satu produk induk (untuk expand baris di menu katalog),
     * di-load lazy via AJAX. Stok/PO/HPP dari master products + harga per
     * varian dari tiktok_listing_prices (per listing pada toko terpilih,
     * left join → null bila belum ada harga).
     */
    public function variants(Request $request)
    {
        $productId = trim((string) $request->input('product_id', ''));
        $storeId   = $request->integer('store_id');
        $hppEmpty  = $request->boolean('hpp_empty');

        if ($productId === '') {
            return response()->json(['variants' => []]);
        }

        // DI-SCOPE ke satu LISTING pada toko terpilih. Varian = baris sku pada
        // listing itu. Alias product_id/sku_id (= id milik TikTok) dipertahankan
        // supaya JSON ke JS tidak berubah.
        $rows = DB::table('tiktok_listings as tl')
            ->join('tiktok_listing_skus as ts', 'ts.listing_id', '=', 'tl.id')
            ->join('products as pr', 'pr.id', '=', 'ts.product_id')
            ->leftJoin('tiktok_listing_prices as p', function ($x) {
                $x->on('p.listing_id', '=', 'tl.id')
                    ->on('p.product_id', '=', 'ts.product_id');
            })
            ->where('tl.store_id', $storeId)
            ->where('tl.tiktok_product_id', $productId)
            ->when($hppEmpty, fn ($q) => $q->where('pr.hpp', '=', 0))
            ->orderBy('ts.tiktok_sku_id')
            ->get([
                'pr.sku_code', 'pr.variation_label', 'pr.stok', 'pr.po_qty', 'pr.hpp',
                'p.retail_price', 'tl.tiktok_product_id as product_id',
                'ts.tiktok_sku_id as sku_id', 'p.promotion_price',
            ]);

        return response()->json([
            'variants' => $rows->map(fn ($r) => [
                // bigint TikTok (~19 digit) → kirim sbg string agar presisi tak
                // hilang di JS (Number.MAX_SAFE_INTEGER = 2^53).
                'product_id'  => $r->product_id !== null ? (string) $r->product_id : null,
                'sku_id'      => $r->sku_id !== null ? (string) $r->sku_id : null,
                'sku'         => $r->sku_code,
                'label'       => $r->variation_label,
                'stok'        => (int) $r->stok,
                'po'          => (int) $r->po_qty,
                'hpp'         => (int) $r->hpp,
                'retail'      => $r->retail_price !== null ? (int) $r->retail_price : null,
                'promo'       => $r->promotion_price !== null ? (int) $r->promotion_price : null,
            ])->all(),
        ]);
    }

    /**
     * Riwayat perubahan harga satu varian (SKU) pada SATU LISTING (product_id)
     * di toko terpilih, untuk aksi "Lihat histori harga" di detail varian.
     * Data dari tiktok_listing_price_histories (diisi trigger DB tiap harga
     * berubah). Urut terbaru dulu.
     */
    public function priceHistory(Request $request)
    {
        $skuCode   = trim((string) $request->input('sku_code', ''));
        $productId = trim((string) $request->input('product_id', ''));
        $storeId   = $request->integer('store_id');

        if ($skuCode === '' || ! $storeId) {
            return response()->json(['changes' => []]);
        }

        $rows = DB::table('tiktok_listing_price_histories')
            ->where('store_id', $storeId)
            ->where('sku_code', $skuCode)
            ->when($productId !== '', fn ($q) => $q->where('tiktok_product_id', $productId))
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
     * Daftar arsip snapshot Excel harian (dibuat otomatis via `products:snapshot-export`,
     * lihat routes/console.php), terbaru dulu. Tiap tanggal berisi 1 file per toko.
     */
    public function snapshots()
    {
        $baseDir = 'product-snapshots';
        $dates   = collect(Storage::disk('local')->directories($baseDir))
            ->map(fn ($path) => basename($path))
            ->filter(fn ($date) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
            ->sortDesc()
            ->values();

        $snapshots = $dates->map(function ($date) use ($baseDir) {
            $dir = "{$baseDir}/{$date}";

            // manifest.json (ditulis saat export) memberi jumlah baris per file tanpa
            // membuka .xlsx. Folder lama tanpa manifest → rows null (UI tampilkan "—").
            $manifest = [];
            if (Storage::disk('local')->exists("{$dir}/manifest.json")) {
                $manifest = json_decode(Storage::disk('local')->get("{$dir}/manifest.json"), true) ?: [];
            }

            $files = collect(Storage::disk('local')->files($dir))
                ->map(fn ($path) => basename($path))
                ->reject(fn ($name) => $name === 'manifest.json') // manifest bukan file unduhan
                ->sort()
                ->values()
                ->map(fn ($name) => [
                    'name' => $name,
                    'size' => $this->formatBytes(Storage::disk('local')->size("{$dir}/{$name}")),
                    'rows' => $manifest[$name]['rows'] ?? null,
                ]);

            return ['date' => $date, 'files' => $files];
        });

        return view('products.snapshots', compact('snapshots'));
    }

    /** Format byte jadi string ringkas (KB/MB) untuk tampilan arsip. */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return max(1, (int) round($bytes / 1024)) . ' KB';
    }

    /**
     * Download satu file snapshot. $date & $file divalidasi ketat (whitelist regex)
     * sebelum dipakai sebagai path, supaya tidak bisa dipakai untuk path traversal.
     */
    public function downloadSnapshot(string $date, string $file)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date), 404);
        abort_unless(preg_match('/^[A-Za-z0-9_\-]+\.xlsx$/', $file), 404);

        $path = "product-snapshots/{$date}/{$file}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    /**
     * Download semua .xlsx satu tanggal sebagai satu .zip. $date divalidasi ketat
     * (whitelist regex) sebelum dipakai sbg path — sama seperti downloadSnapshot().
     * Zip dibuat ke file temp lalu dihapus setelah terkirim.
     */
    public function downloadAllSnapshots(string $date)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date), 404);

        $dir   = "product-snapshots/{$date}";
        $files = collect(Storage::disk('local')->files($dir))
            ->filter(fn ($path) => str_ends_with($path, '.xlsx'))
            ->values();
        abort_if($files->isEmpty(), 404);

        $zipPath = tempnam(sys_get_temp_dir(), 'snap') . '.zip';
        $zip     = new \ZipArchive;
        abort_unless($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 500);

        foreach ($files as $path) {
            $zip->addFile(Storage::disk('local')->path($path), basename($path));
        }
        $zip->close();

        return response()->download($zipPath, "snapshot_{$date}.zip")->deleteFileAfterSend();
    }

    /**
     * Isi/ubah HPP satu SKU secara manual (dipakai untuk produk bundling yang
     * HPP-nya tidak tersedia di Jubelio). Nilai ini tak akan tertimpa saat sync
     * selama Jubelio mengirim 0 (lihat SyncJubelioInventory). hpp = 0 berarti "belum terisi".
     */
    public function updateHpp(Request $request)
    {
        $data = $request->validate([
            'sku_code' => ['required', 'string', 'exists:products,sku_code'],
            'hpp'      => ['required', 'integer', 'min:0'],
        ]);

        DB::table('products')
            ->where('sku_code', $data['sku_code'])
            ->update(['hpp' => $data['hpp'], 'updated_at' => now()]);

        return response()->json([
            'ok'       => true,
            'sku_code' => $data['sku_code'],
            'hpp'      => (int) $data['hpp'],
        ]);
    }

    /**
     * Isi/ubah ID Model satu LISTING (product_id TikTok) pada toko terpilih.
     * Kosong = hapus override (kembali ke nilai turunan dari sku_code).
     */
    public function updateModelId(Request $request)
    {
        $data = $request->validate([
            'store_id'   => ['required', 'integer', 'exists:stores,id'],
            'product_id' => ['required', 'string'],
            'model_id'   => ['nullable', 'string', 'max:100'],
        ]);

        $modelId = trim((string) ($data['model_id'] ?? ''));

        $updated = DB::table('tiktok_listings')
            ->where('store_id', $data['store_id'])
            ->where('tiktok_product_id', $data['product_id'])
            ->update(['model_id' => $modelId !== '' ? $modelId : null, 'updated_at' => now()]);

        abort_if(! $updated && ! DB::table('tiktok_listings')
            ->where('store_id', $data['store_id'])
            ->where('tiktok_product_id', $data['product_id'])
            ->exists(), 404, 'Listing tidak ditemukan.');

        return response()->json([
            'ok'         => true,
            'product_id' => $data['product_id'],
            'model_id'   => $modelId !== '' ? $modelId : null,
        ]);
    }

    /**
     * Ubah harga promo satu SKU pada SATU LISTING (product_id) tertentu. Harga
     * tersimpan per (store_id, product_id, sku_code) di tiktok_listing_prices,
     * jadi listing lain yang kebetulan berbagi sku_code sama di toko yang sama
     * tidak ikut berubah.
     */
    public function updatePrice(Request $request)
    {
        $data = $request->validate([
            'sku_code'         => ['required', 'string', 'exists:products,sku_code'],
            'store_id'         => ['required', 'integer', 'exists:stores,id'],
            'product_id'       => ['required', 'string'],
            'promotion_price'  => ['required', 'integer', 'min:0'],
        ]);

        $this->upsertListingPrice($data['store_id'], $data['product_id'], $data['sku_code'], $data['promotion_price']);

        return response()->json([
            'ok'               => true,
            'sku_code'         => $data['sku_code'],
            'promotion_price'  => (int) $data['promotion_price'],
        ]);
    }

    /**
     * Ubah harga promo SEMUA varian (sku_code) pada satu listing (product_id)
     * untuk toko terpilih sekaligus. Dipakai dari baris produk di katalog.
     */
    public function bulkUpdatePrice(Request $request)
    {
        $data = $request->validate([
            'product_id'       => ['required', 'string'],
            'store_id'         => ['required', 'integer', 'exists:stores,id'],
            'promotion_price'  => ['required', 'integer', 'min:0'],
        ]);

        $skuCodes = DB::table('tiktok_listings as tl')
            ->join('tiktok_listing_skus as ts', 'ts.listing_id', '=', 'tl.id')
            ->join('products as pr', 'pr.id', '=', 'ts.product_id')
            ->where('tl.store_id', $data['store_id'])
            ->where('tl.tiktok_product_id', $data['product_id'])
            ->distinct()
            ->pluck('pr.sku_code');

        if ($skuCodes->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Tidak ada varian ditemukan untuk product_id ini.'], 404);
        }

        foreach ($skuCodes as $skuCode) {
            $this->upsertListingPrice($data['store_id'], $data['product_id'], $skuCode, $data['promotion_price']);
        }

        return response()->json([
            'ok'               => true,
            'product_id'       => $data['product_id'],
            'count'            => $skuCodes->count(),
            'promotion_price'  => (int) $data['promotion_price'],
        ]);
    }

    /**
     * Cari varian (sku_code) berdasar potongan seller SKU (bisa lengkap/tidak),
     * lintas SEMUA toko sekaligus — dipakai oleh popup "Update Harga Massal".
     * Satu baris hasil = satu (store, product_id, sku_code) beserta harga promo
     * saat ini, supaya UI bisa kelompokkan per toko/product_id dan tampilkan
     * pratinjau sebelum commit.
     */
    public function bulkPriceSearch(Request $request)
    {
        $data = $request->validate([
            'keyword' => ['required', 'string', 'min:2'],
        ]);
        $keyword = trim($data['keyword']);

        $rows = DB::table('tiktok_listing_skus as ts')
            ->join('tiktok_listings as tl', 'tl.id', '=', 'ts.listing_id')
            ->join('products as pr', 'pr.id', '=', 'ts.product_id')
            ->join('stores as s', 's.id', '=', 'tl.store_id')
            ->leftJoin('tiktok_listing_prices as p', function ($x) {
                $x->on('p.listing_id', '=', 'tl.id')
                    ->on('p.product_id', '=', 'ts.product_id');
            })
            ->where(function ($w) use ($keyword) {
                $w->where('pr.sku_code', 'like', "%{$keyword}%")
                    ->orWhere('pr.parent_sku', 'like', "%{$keyword}%");
            })
            ->orderBy('s.name')
            ->orderBy('tl.tiktok_product_id')
            ->orderBy('ts.tiktok_sku_id')
            ->get([
                's.id as store_id', 's.name as store_name',
                'tl.tiktok_product_id as product_id', 'pr.sku_code', 'pr.variation_label',
                'p.promotion_price',
            ]);

        return response()->json([
            'results' => $rows->map(fn ($r) => [
                'store_id'        => (int) $r->store_id,
                'store_name'      => $r->store_name,
                'product_id'      => (string) $r->product_id,
                'sku_code'        => $r->sku_code,
                'label'           => $r->variation_label,
                'promotion_price' => $r->promotion_price !== null ? (int) $r->promotion_price : null,
            ])->all(),
        ]);
    }

    /**
     * Terapkan harga promo ke sekumpulan listing (store_id, product_id, sku_code)
     * yang dipilih user di popup "Update Harga Massal" (bisa lintas toko/produk).
     * Ditulis ke tiktok_listing_prices — lihat upsertListingPrice().
     * Mendukung 2 mode harga:
     * - satu harga global (`promotion_price` di root) untuk mode "Tempel SKU",
     * - harga per-item (`items.*.promotion_price`) untuk mode "Upload Excel"
     *   yang tiap SKU-nya bisa punya harga baru berbeda.
     */
    public function bulkPriceApply(Request $request)
    {
        $data = $request->validate([
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.store_id'             => ['required', 'integer', 'exists:stores,id'],
            'items.*.product_id'           => ['required', 'string'],
            'items.*.sku_code'             => ['required', 'string', 'exists:products,sku_code'],
            'items.*.promotion_price'      => ['nullable', 'integer', 'min:0'],
            'promotion_price'              => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($data['items'] as $item) {
            $price = $item['promotion_price'] ?? $data['promotion_price'] ?? null;
            abort_if($price === null, 422, 'Setiap item harus punya harga promo (baik per-item maupun global).');
            $this->upsertListingPrice((int) $item['store_id'], $item['product_id'], $item['sku_code'], (int) $price);
        }

        return response()->json([
            'ok'    => true,
            'count' => count($data['items']),
        ]);
    }

    /**
     * Parse + validasi file Excel untuk mode "Upload Excel" pada popup "Update
     * Harga Massal". Format wajib: baris 1 header "seller_sku" (kolom A) &
     * "new_promotion_price" (kolom B), baris berikutnya data. Baris kosong penuh
     * diabaikan; SKU kosong/duplikat atau harga tidak valid → TOLAK SELURUH FILE
     * dgn daftar error per baris (bukan skip diam-diam), supaya user tahu persis
     * apa yang harus diperbaiki sebelum upload ulang.
     */
    public function bulkPriceParseExcel(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return response()->json(['errors' => ['File tidak bisa dibaca. Pastikan file .xlsx valid.']], 422);
        }

        $sheet  = $spreadsheet->getActiveSheet();
        $header = [
            strtolower(trim((string) $sheet->getCell('A1')->getValue())),
            strtolower(trim((string) $sheet->getCell('B1')->getValue())),
        ];
        if ($header !== ['seller_sku', 'new_promotion_price']) {
            return response()->json([
                'errors' => ['Header kolom tidak sesuai. Baris 1 harus "seller_sku" (kolom A) dan "new_promotion_price" (kolom B) — gunakan template.'],
            ], 422);
        }

        $items  = [];
        $errors = [];
        $seen   = []; // SKU (uppercase) => nomor baris pertama kemunculan

        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $skuRaw   = trim((string) $sheet->getCell("A{$row}")->getValue());
            $priceRaw = $sheet->getCell("B{$row}")->getValue();
            $priceStr = trim((string) $priceRaw);

            if ($skuRaw === '' && $priceStr === '') {
                continue; // baris kosong penuh, abaikan
            }
            if ($skuRaw === '') {
                $errors[] = "Baris {$row}: seller_sku kosong.";

                continue;
            }

            $skuKey = strtoupper($skuRaw);
            if (isset($seen[$skuKey])) {
                $errors[] = "Baris {$row}: seller_sku \"{$skuRaw}\" duplikat dengan baris {$seen[$skuKey]}.";

                continue;
            }
            if ($priceStr === '' || ! is_numeric($priceStr) || (float) $priceStr < 0) {
                $errors[] = "Baris {$row}: new_promotion_price harus angka ≥ 0 (ditemukan: \"{$priceStr}\").";

                continue;
            }

            $seen[$skuKey] = $row;
            $items[] = [
                'seller_sku'          => $skuRaw,
                'new_promotion_price' => (int) round((float) $priceStr),
            ];
        }

        if (! empty($errors)) {
            return response()->json(['errors' => $errors], 422);
        }
        if (empty($items)) {
            return response()->json(['errors' => ['File tidak berisi data. Isi minimal 1 baris seller_sku + harga.']], 422);
        }

        return response()->json(['items' => $items]);
    }

    /**
     * Cocokkan seller_sku (dari hasil parse Excel) ke toko-toko yang dipilih user
     * — match EXACT ke tiktok_listing_skus.sku_code (bukan LIKE, beda dgn
     * bulkPriceSearch yang untuk pencarian bebas). Toko yang tidak menjual SKU
     * tsb otomatis tidak muncul di hasil untuk SKU itu (di-skip). Bila ada SKU
     * yang sama sekali tak ditemukan di toko manapun yang dipilih → TOLAK, minta
     * user perbaiki file/pilihan toko dulu (bukan lanjut sebagian).
     *
     * Template Excel tetap per seller_sku saja (tanpa kolom product_id) — bila 1
     * seller_sku ternyata terdaftar di >1 listing (product_id) pada toko yang
     * sama, harga diterapkan ke SEMUA listing itu: satu baris preview per
     * listing (bukan di-distinct jadi 1 baris), supaya user melihat eksplisit
     * semua listing yang akan kena sebelum apply.
     */
    public function bulkPriceMatchExcel(Request $request)
    {
        $data = $request->validate([
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.seller_sku'          => ['required', 'string'],
            'items.*.new_promotion_price' => ['required', 'integer', 'min:0'],
            'store_ids'                   => ['required', 'array', 'min:1'],
            'store_ids.*'                 => ['integer', 'exists:stores,id'],
        ]);

        $storeIds     = array_map('intval', $data['store_ids']);
        $priceBySku   = collect($data['items'])->keyBy(fn ($i) => strtoupper($i['seller_sku']));
        $rawSkuCodes  = collect($data['items'])->pluck('seller_sku')->all();

        $rows = DB::table('tiktok_listing_skus as ts')
            ->join('tiktok_listings as tl', 'tl.id', '=', 'ts.listing_id')
            ->join('products as pr', 'pr.id', '=', 'ts.product_id')
            ->join('stores as s', 's.id', '=', 'tl.store_id')
            ->leftJoin('tiktok_listing_prices as p', function ($x) {
                $x->on('p.listing_id', '=', 'tl.id')
                    ->on('p.product_id', '=', 'ts.product_id');
            })
            ->whereIn('tl.store_id', $storeIds)
            ->whereIn('pr.sku_code', $rawSkuCodes)
            ->distinct()
            ->get([
                'tl.store_id', 's.name as store_name',
                'tl.tiktok_product_id as product_id', 'pr.sku_code',
                'p.promotion_price as old_price',
            ]);

        $matchedKeys = [];
        $preview = $rows->map(function ($r) use ($priceBySku, &$matchedKeys) {
            $skuKey = strtoupper($r->sku_code);
            $matchedKeys[$skuKey] = true;

            return [
                'store_id'   => (int) $r->store_id,
                'store_name' => $r->store_name,
                'product_id' => (string) $r->product_id,
                'sku_code'   => $r->sku_code,
                'old_price'  => $r->old_price !== null ? (int) $r->old_price : null,
                'new_price'  => (int) $priceBySku[$skuKey]['new_promotion_price'],
            ];
        })->sortBy([['store_name', 'asc'], ['sku_code', 'asc'], ['product_id', 'asc']])->values();

        $notFound = $priceBySku->keys()
            ->reject(fn ($k) => isset($matchedKeys[$k]))
            ->map(fn ($k) => $priceBySku[$k]['seller_sku'])
            ->values();

        if ($notFound->isNotEmpty()) {
            return response()->json(['not_found' => $notFound->all()], 422);
        }

        return response()->json(['preview' => $preview->all()]);
    }

    /**
     * Unduh template .xlsx kosong (header + 1 baris contoh) untuk mode
     * "Upload Excel" pada popup "Update Harga Massal".
     */
    public function bulkPriceExcelTemplate()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'seller_sku');
        $sheet->setCellValue('B1', 'new_promotion_price');
        $sheet->setCellValueExplicit('A2', 'CONTOH-SKU-1', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('B2', 15000);
        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(22);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template_update_harga_massal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Set promotion_price satu listing di tiktok_listing_prices. Payload klien
     * tetap (store_id, product_id TikTok, sku_code) — di sini di-resolve ke
     * FK internal (listing_id, products.id). UPDATE dulu (memicu trigger
     * history), INSERT bila baris belum ada.
     */
    private function upsertListingPrice(int $storeId, string $tiktokProductId, string $skuCode, int $promotionPrice): void
    {
        $listingId = DB::table('tiktok_listings')
            ->where('store_id', $storeId)
            ->where('tiktok_product_id', $tiktokProductId)
            ->value('id');
        $productId = DB::table('products')->where('sku_code', $skuCode)->value('id');

        abort_if(! $listingId || ! $productId, 422, "Listing atau SKU tidak ditemukan ({$tiktokProductId} / {$skuCode}).");

        $now = now();

        $updated = DB::table('tiktok_listing_prices')
            ->where('listing_id', $listingId)
            ->where('product_id', $productId)
            ->update(['promotion_price' => $promotionPrice, 'updated_at' => $now]);

        if (! $updated) {
            DB::table('tiktok_listing_prices')->insert([
                'listing_id'       => $listingId,
                'product_id'       => $productId,
                'promotion_price'  => $promotionPrice,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    /**
     * Kunci varian dari sku_code (BUKAN products.parent_sku yg tak konsisten).
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
