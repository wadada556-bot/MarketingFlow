<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\BulkDeleteProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $categories = Category::select('id', 'name')->get();

        $products = Product::select('id', 'parent_sku', 'category_id')
            ->with('category:id,name')
            ->latest('id')
            ->paginate(10);

        return view('products.index', compact('products', 'categories'));
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

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function bulkDestroy(BulkDeleteProductRequest $request)
    {
        $validated = $request->validated();

        Product::whereIn('id', $validated['ids'])->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Selected products deleted successfully.');
    }
}
