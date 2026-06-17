<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductAdLogRequest;
use App\Http\Requests\UpdateProductAdLogRequest;
use App\Models\ProductAd;
use App\Models\ProductAdLog;
use Carbon\Carbon;

class ProductAdLogController extends Controller
{
    public function store(StoreProductAdLogRequest $request)
    {
        $validatedData = $request->validated();

        ProductAdLog::create([
            'product_ad_id' => $validatedData['product_ad_id'],
            'description'   => $validatedData['description'],
            'action_date'   => $validatedData['action_date'],
        ]);

        return back()->with('success', 'New activity log created successfully.');
    }

    public function update(UpdateProductAdLogRequest $request, ProductAdLog $productAdLog)
    {
        $validatedData = $request->validated();

        $productAdLog->update([
            'description'   => $validatedData['description'],
            'action_date'   => $validatedData['action_date'],
        ]);

        return back()->with('success', 'Activity log updated successfully.');
    }

    public function destroy(ProductAdLog $productAdLog)
    {
        $productAdLog->delete();
        return back()->with('success', 'Activity log deleted successfully.');
    }
}
