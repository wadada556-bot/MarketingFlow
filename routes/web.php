<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductAdController;
use App\Http\Controllers\ProductAdLogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::patch('product-ads/{product_ad}/mark-success', [ProductAdController::class, 'markSuccess'])
    ->name('product-ads.mark-success');
Route::patch('product-ads/{product_ad}/mark-fail', [ProductAdController::class, 'markFail'])
    ->name('product-ads.mark-fail');
Route::patch('product-ads/{product_ad}/extend', [ProductAdController::class, 'extend'])
    ->name('product-ads.extend');
Route::delete('product-ads/bulk-destroy', [ProductAdController::class, 'bulkDestroy'])
    ->name('product-ads.bulk-destroy');
Route::resource('product-ads', ProductAdController::class);
Route::resource('product-ad-logs', ProductAdLogController::class);
Route::delete('/products/bulk-destroy', [ProductController::class, 'bulkDestroy'])
    ->name('products.bulk-destroy');
Route::resource('products', ProductController::class);
Route::resource('categories', CategoryController::class);
Route::resource('stores', StoreController::class);