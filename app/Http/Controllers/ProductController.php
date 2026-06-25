<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\BulkDeleteProductRequest;
use App\Models\Category;
use App\Models\Product;
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

        $products = Product::select('id', 'parent_sku', 'category_id')
            ->with('category:id,name')
            ->when($search, function ($query, $search) {
                $query->where('parent_sku', 'like', "%{$search}%")
                      ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(10);

        return view('products.index', compact('products', 'categories', 'search'));
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
