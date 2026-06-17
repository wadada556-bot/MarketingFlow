<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductAdRequest;
use App\Http\Requests\UpdateProductAdRequest;
use App\Http\Requests\BulkDeleteProductAdRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAd;
use App\Models\Store;
use App\Services\ErpApiService;
use App\Services\ProductAdService;
use Illuminate\Http\Request;

class ProductAdController extends Controller
{
    public function __construct(
        private readonly ProductAdService $productAdService,
        private readonly ErpApiService $erpApiService,
    ) {}

    public function index(Request $request)
    {
        $categories = Category::select('id', 'name')->orderBy('name')->get();
        $products = Product::select('id', 'parent_sku')->orderBy('parent_sku')->get();
        $stores = Store::select('id', 'name')->orderBy('name')->get();

        $currentStatus = $request->testing_status ?? 'semua';
        $tabCounts = $this->productAdService->getTabCounts();

        $filters = $request->only(['product', 'status', 'category', 'store', 'testing_status']);
        $productAds = $this->productAdService->getPaginatedAds($filters, $currentStatus);

        $stockData = $this->fetchVariantStock($productAds->items());

        return view('product-ads.index', compact(
            'productAds',
            'products',
            'stores',
            'categories',
            'tabCounts',
            'currentStatus',
            'stockData',
        ));
    }

    private function fetchVariantStock(array $items): array
    {
        $skus = collect($items)
            ->map(fn($ad) => $ad->product->parent_sku ?? null)
            ->unique()->filter()->values()->all();

        return $this->erpApiService->getStockByParentSkus($skus);
    }

    public function create()
    {
        //
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

        $parentSku     = $productAd->product->parent_sku;
        $stockData     = $this->erpApiService->getStockByParentSkus([$parentSku]);
        $stockVariants = $stockData[$parentSku] ?? [];

        return view('product-ads.show', compact('productAd', 'stockVariants'));
    }

    public function edit(ProductAd $productAd)
    {
        $products = Product::select(['id', 'parent_sku'])->orderBy('parent_sku')->get();
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