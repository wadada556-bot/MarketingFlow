<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductAdRequest;
use App\Http\Requests\UpdateProductAdRequest;
use App\Http\Requests\BulkDeleteProductAdRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAd;
use App\Models\Store;
use App\Services\DailySalesQueryService;
use App\Services\JubelioApiService;
use App\Services\ProductAdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductAdController extends Controller
{
    private const ERP_CACHE_TTL = 900; // 15 menit

    public function __construct(
        private readonly ProductAdService $productAdService,
        private readonly JubelioApiService $jubelioApiService,
        private readonly DailySalesQueryService $dailySalesQueryService,
    ) {}

    public function index(Request $request)
    {
        $categories = Category::select('id', 'name')->orderBy('name')->get();
        $products = collect(
            Cache::remember('product_dropdown_list', 600,
                fn() => Product::select('id', 'parent_sku')->orderBy('parent_sku')
                    ->get()->map(fn($p) => $p->getAttributes())->all()
            )
        )->map(fn($a) => (object) $a);
        $stores = Store::select('id', 'name')->orderBy('name')->get();

        $currentStatus = $request->testing_status ?? 'semua';
        $tabCounts = $this->productAdService->getTabCounts();

        $filters = $request->only(['product', 'status', 'category', 'store', 'testing_status']);
        $productAds = $this->productAdService->getPaginatedAds($filters, $currentStatus);

        // Data stok/penjualan ERP TIDAK di-fetch di sini — halaman render instan.
        // Diisi lewat AJAX ke product-ads.erp-data (lihat partials/table.blade.php).
        return view('product-ads.index', compact(
            'productAds',
            'products',
            'stores',
            'categories',
            'tabCounts',
            'currentStatus',
        ));
    }

    /**
     * Endpoint AJAX: stok + penjualan ERP untuk product-ads di halaman/filter saat ini.
     *
     * SKU diturunkan dari DB (bukan input client) agar aman dari SQL injection ke ERP.
     * Mengembalikan HTML sel (di-render dari Blade partial) per id iklan.
     */
    public function erpData(Request $request)
    {
        $currentStatus = $request->testing_status ?? 'semua';
        $filters       = $request->only(['product', 'status', 'category', 'store', 'testing_status']);

        // Query ringan: hanya id + parent_sku — tidak perlu eager-load category/stores untuk ERP fetch
        $productAds = ProductAd::select('id', 'product_id')
            ->with('product:id,parent_sku')
            ->filter($filters)
            ->when(
                $currentStatus === 'perlu_dicek',
                fn($q) => $q->orderBy('testing_completed_at', 'asc'),
                fn($q) => $q->latest('id')
            )
            ->paginate(6);

        $items = $productAds->items();
        $erp   = $this->fetchErpData($items);

        $out = [];
        foreach ($items as $ad) {
            $sku      = $ad->product->parent_sku ?? null;
            $variants = $sku ? ($erp['stock'][$sku] ?? []) : [];
            $skuSales = $sku
                ? ($erp['sales'][$sku] ?? ['today' => [], 'yesterday' => [], '7d' => [], '30d' => [], '90d' => []])
                : ['today' => [], 'yesterday' => [], '7d' => [], '30d' => [], '90d' => []];
            $skuPo    = $sku ? ($erp['po'][$sku] ?? []) : [];
            $skuHpp   = $sku ? ($erp['hpp'][$sku] ?? []) : [];
            $skuStoreSales = $sku ? ($erp['storeSales'][$sku] ?? []) : [];

            // Tambahkan hpp ke setiap variant agar tersedia di JS renderer
            $variants = array_map(
                fn ($v) => [...$v, 'hpp' => $skuHpp[$v['sku']] ?? 0],
                $variants,
            );

            $out[$ad->id] = [
                'variants'   => $variants,
                'sales'      => $skuSales,
                'storeSales' => $skuStoreSales,
                'po'         => $skuPo,
            ];
        }

        return response()->json($out);
    }

    /**
     * Fetch stock + sales for the listed ads, cached PER SKU.
     *
     * Hanya SKU yang belum ter-cache yang dipanggil ke ERP (dalam satu batch
     * paralel). Jadi menambah 1 iklan hanya mem-fetch 1 SKU baru, bukan seluruh
     * SKU di halaman. Cache ini dibagi-pakai dengan halaman detail (show).
     *
     * @return array{stock: array, sales: array}
     */
    private function fetchErpData(array $items): array
    {
        $skus = collect($items)
            ->map(fn($ad) => $ad->product->parent_sku ?? null)
            ->unique()->filter()->values()->all();

        return $this->fetchErpForSkus($skus);
    }

    /**
     * Per-SKU cached fetch of stock+HPP (Jubelio) + sales (daily_sku_sales lokal,
     * total & per toko) + PO (ERP lama, satu-satunya yang masih dari sana).
     *
     * @param  string[]  $skus
     * @return array{stock: array, sales: array, storeSales: array, po: array, hpp: array}
     */
    private function fetchErpForSkus(array $skus): array
    {
        $stock      = [];
        $sales      = [];
        $storeSales = [];
        $po         = [];
        $hpp        = [];
        $missing    = [];

        foreach ($skus as $sku) {
            $cached = Cache::get('pa_erp_sku_' . md5($sku));
            if ($cached !== null && array_key_exists('storeSales', $cached)) {
                $stock[$sku]      = $cached['stock'];
                $sales[$sku]      = $cached['sales'];
                $storeSales[$sku] = $cached['storeSales'];
                $po[$sku]         = $cached['po'];
                $hpp[$sku]        = $cached['hpp'];
            } else {
                $missing[] = $sku;
            }
        }

        if (!empty($missing)) {
            $fetchedJubelio = $this->jubelioApiService->getInventoryByParentSkus($missing);
            $fetchedPo      = $this->jubelioApiService->getPoByParentSkus($missing);
            $fetchedSales   = $this->dailySalesQueryService->getForParentSkus($missing);

            foreach ($missing as $sku) {
                $skuStock = $fetchedJubelio['stock'][$sku] ?? [];
                $skuHpp   = collect($skuStock)
                    ->mapWithKeys(fn ($v) => [$v['sku'] => $fetchedJubelio['hpp'][$v['sku']] ?? 0])
                    ->all();

                $entry = [
                    'stock'      => $skuStock,
                    'sales'      => $fetchedSales['total'][$sku] ?? ['today' => [], 'yesterday' => [], '7d' => [], '30d' => [], '90d' => []],
                    'storeSales' => $fetchedSales['stores'][$sku] ?? [],
                    'po'         => $fetchedPo[$sku] ?? [],
                    'hpp'        => $skuHpp,
                ];
                $ttl = empty($entry['stock']) ? 60 : self::ERP_CACHE_TTL;
                Cache::put('pa_erp_sku_' . md5($sku), $entry, $ttl);
                $stock[$sku]      = $entry['stock'];
                $sales[$sku]      = $entry['sales'];
                $storeSales[$sku] = $entry['storeSales'];
                $po[$sku]         = $entry['po'];
                $hpp[$sku]        = $entry['hpp'];
            }
        }

        return ['stock' => $stock, 'sales' => $sales, 'storeSales' => $storeSales, 'po' => $po, 'hpp' => $hpp];
    }

    public function create()
    {
        //
    }

    public function checkDuplicate(Request $request): \Illuminate\Http\JsonResponse
    {
        $productId = $request->integer('product_id');
        $storeIds  = $request->array('store_ids');
        $excludeId = $request->integer('exclude_id');

        if (!$productId || empty($storeIds)) {
            return response()->json(['duplicates' => []]);
        }

        $query = ProductAd::where('product_id', $productId)
            ->where('status', 'active')
            ->whereHas('stores', fn ($q) => $q->whereIn('stores.id', $storeIds));

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $duplicates = $query->with('stores:id,name')
            ->limit(200)
            ->get()
            ->flatMap->stores
            ->whereIn('id', $storeIds)
            ->unique('id')
            ->pluck('name')
            ->values();

        return response()->json(['duplicates' => $duplicates]);
    }

    public function store(StoreProductAdRequest $request)
    {
        $this->productAdService->createProductAd($request->validated());

        return redirect()
            ->route('product-ads.index')
            ->with('success', 'Product Ad created successfully.');
    }

    public function show(ProductAd $productAd)
    {
        $productAd->load([
            'product:id,parent_sku,category_id',
            'product.category:id,name',
            'stores:id,name',
            'productAdLogs' => fn($query) => $query
                ->select('id', 'product_ad_id', 'action_date', 'description')
                ->orderBy('action_date')
        ]);

        // Stok & penjualan ERP di-load lazy via product-ads.stock-detail (AJAX).
        return view('product-ads.show', compact('productAd'));
    }

    /**
     * Endpoint AJAX: stok + penjualan varian untuk satu product ad (halaman detail).
     */
    public function stockDetail(ProductAd $productAd)
    {
        $productAd->loadMissing('product:id,parent_sku');
        $parentSku = $productAd->product->parent_sku ?? null;

        $erp           = $parentSku ? $this->fetchErpForSkus([$parentSku]) : ['stock' => [], 'sales' => [], 'po' => [], 'hpp' => []];
        $stockVariants = $parentSku ? ($erp['stock'][$parentSku] ?? []) : [];
        $variantSales  = $parentSku
            ? ($erp['sales'][$parentSku] ?? ['today' => [], 'yesterday' => [], '7d' => [], '30d' => []])
            : ['today' => [], 'yesterday' => [], '7d' => [], '30d' => []];
        $variantPo     = $parentSku ? ($erp['po'][$parentSku] ?? []) : [];
        $hppMap        = $parentSku ? ($erp['hpp'][$parentSku] ?? []) : [];

        return response()->json([
            'html' => view('product-ads.partials._variant-stock-table', compact('stockVariants', 'variantSales', 'variantPo', 'hppMap'))->render(),
        ]);
    }

    public function edit(ProductAd $productAd)
    {
        $products = collect(
            Cache::remember('product_dropdown_list', 600,
                fn() => Product::select(['id', 'parent_sku'])->orderBy('parent_sku')
                    ->get()->map(fn($p) => $p->getAttributes())->all()
            )
        )->map(fn($a) => (object) $a);
        $stores = Store::select(['id', 'name'])->orderBy('name')->get();
        $productAd->load('stores');

        return view('product-ads.edit', compact('productAd', 'products', 'stores'));
    }

    public function update(UpdateProductAdRequest $request, ProductAd $productAd)
    {
        $this->productAdService->updateProductAd($productAd, $request->validated());

        return redirect()
            ->route('product-ads.index')
            ->with('success', 'Product Ad updated successfully.');
    }

    public function destroy(ProductAd $productAd)
    {
        $productAd->stores()->detach();
        $productAd->delete();

        return redirect()
            ->route('product-ads.index')
            ->with('success', 'Product Ad deleted successfully');
    }

    public function bulkDestroy(BulkDeleteProductAdRequest $request)
    {
        $this->productAdService->bulkDeleteProductAds($request->validated()['ids']);

        return redirect()
            ->route('product-ads.index')
            ->with('success', 'Data iklan produk terpilih berhasil dihapus.');
    }

    public function markSuccess(ProductAd $productAd)
    {
        $productAd->update([
            'testing_status' => 'success',
            'testing_completed_at' => now(),
        ]);

        return back()->with('success', 'Status iklan berhasil diubah menjadi Success.');
    }

    public function markFail(ProductAd $productAd)
    {
        $productAd->update([
            'testing_status' => 'fail',
            'testing_completed_at' => now(),
        ]);

        return back()->with('success', 'Status iklan berhasil diubah menjadi Fail.');
    }

    public function extend(Request $request, ProductAd $productAd)
    {
        $request->validate([
            'days' => 'required',
            'custom_days' => 'required_if:days,custom|numeric|min:1|nullable'
        ]);

        $daysToAdd = $request->days === 'custom' ? (int) $request->custom_days : (int) $request->days;
        $currentDeadline = $productAd->testing_completed_at ? \Carbon\Carbon::parse($productAd->testing_completed_at) : now();

        $newDeadline = $currentDeadline->isPast()
            ? now()->addDays($daysToAdd)
            : $currentDeadline->addDays($daysToAdd);

        $productAd->update([
            'testing_status' => 'testing',
            'testing_completed_at' => $newDeadline,
        ]);

        return back()->with('success', "Masa testing iklan berhasil diperpanjang {$daysToAdd} hari.");
    }
}