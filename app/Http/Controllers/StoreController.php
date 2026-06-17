<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Models\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class StoreController extends Controller
{
    public function index()
    {
        $stores = Store::select('id', 'name')
            ->latest('id')
            ->paginate(8);

        return view('stores.index', compact('stores'));
    }

    public function create()
    {
        //
    }

    public function store(StoreStoreRequest $request)
    {
        $validatedData = $request->validated();
        Store::create($validatedData);

        return redirect()
            ->route('stores.index')
            ->with('success', 'Store created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }


    public function update(UpdateStoreRequest $request, Store $store)
    {
        try {
            $validatedData = $request->validated();
            $store->update($validatedData);

            return redirect()
                ->route('stores.index')
                ->with('success', 'Store updated successfully.');
        } catch (Throwable $th) {
            Log::error('Error when updating store: ' . $th->getMessage());

            return redirect()
                ->route('stores.index')
                ->with('error', 'Failed to update the store. Please contact administrator.');
        }

    }

    public function destroy(Store $store)
    {
        try {
            $store->delete();

            return redirect()
                ->route('stores.index')
                ->with('success', 'Store deleted successfully.');
        } catch (Exception $e) {
            Log::error('General error when deleting store: ' . $e->getMessage());

            return redirect()
                ->route('stores.index')
                ->with('error', 'Failed to delete the store. Please contact administrator.');
        }

    }
}
