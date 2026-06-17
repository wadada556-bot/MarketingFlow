<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::select('id', 'name')
            ->latest('id')
            ->paginate(8);

        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        //
    }

    public function store(StoreCategoryRequest $request)
    {
        $validatedData = $request->validated();
        Category::create($validatedData);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $validatedData = $request->validated();
        $category->update($validatedData);

        return back()->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        
        return redirect()
            ->route('categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
