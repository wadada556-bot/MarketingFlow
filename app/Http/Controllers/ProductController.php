<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\BulkDeleteProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreSkuPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function suggest(Request $request)
    {
        $q = trim($request->input('q', ''));

        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $results = Product::select('id', 'parent_sku', 'category_id')
            ->with('category:id,name')
            ->where('parent_sku', 'like', "%{$q}%")
            ->orWhereHas('category', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn ($p) => [
                'sku'      => $p->parent_sku,
                'category' => $p->category->name ?? 'Uncategorized',
            ]);

        return response()->json($results);
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $categories = Category::select('id', 'name')->get();

        // Toko untuk pemilih harga (harga jual berbeda per toko)
        $stores  = Store::select('id', 'name')->orderBy('name')->get();
        $storeId = $request->integer('store_id') ?: optional($stores->first())->id;

        $products = Product::select('id', 'parent_sku', 'category_id')
            ->with('category:id,name')
            ->when($search, function ($query, $search) {
                $query->where('parent_sku', 'like', "%{$search}%")
                      ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(10);

        // Rentang harga (min–max) per induk untuk toko terpilih — hanya produk di halaman ini.
        // NULLIF(...,0) → abaikan nilai 0 (promotion_price 0 = tidak ada campaign).
        $prices = collect();
        if ($storeId) {
            $skus = $products->pluck('parent_sku')->map(fn ($s) => strtoupper($s))->unique()->all();
            if (! empty($skus)) {
                $prices = StoreSkuPrice::query()
                    ->where('store_id', $storeId)
                    ->whereIn('match_sku', $skus)
                    ->selectRaw('match_sku,
                        MIN(NULLIF(retail_price, 0))    as retail_min,
                        MAX(NULLIF(retail_price, 0))    as retail_max,
                        MIN(NULLIF(promotion_price, 0)) as promo_min,
                        MAX(NULLIF(promotion_price, 0)) as promo_max')
                    ->groupBy('match_sku')
                    ->get()
                    ->keyBy('match_sku');
            }
        }

        return view('products.index', compact('products', 'categories', 'search', 'stores', 'storeId', 'prices'));
    }

    public function create()
    {
        //
    }

    public function store(StoreProductRequest $request)
    {
        $validatedData = $request->validated();

        Product::create([
            'category_id' => $validatedData['category_id'],
            'parent_sku' => $validatedData['parent_sku']
        ]);

        Cache::forget('product_dropdown_list');

        return redirect()
            ->route('products.index')
            ->with('success', 'Product created successfully.');
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $validatedData = $request->validated();
        $product->update($validatedData);

        Cache::forget('product_dropdown_list');

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        Cache::forget('product_dropdown_list');

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function bulkDestroy(BulkDeleteProductRequest $request)
    {
        $validated = $request->validated();

        Product::whereIn('id', $validated['ids'])->delete();

        Cache::forget('product_dropdown_list');

        return redirect()
            ->route('products.index')
            ->with('success', 'Selected products deleted successfully.');
    }
}
