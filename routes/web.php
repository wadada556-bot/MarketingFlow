<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductAdController;
use App\Http\Controllers\ProductAdLogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesHistoryController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/stock-alerts', [DashboardController::class, 'stockAlerts'])
    ->name('dashboard.stock-alerts');

Route::patch('product-ads/{product_ad}/mark-success', [ProductAdController::class, 'markSuccess'])
    ->name('product-ads.mark-success');
Route::patch('product-ads/{product_ad}/mark-fail', [ProductAdController::class, 'markFail'])
    ->name('product-ads.mark-fail');
Route::patch('product-ads/{product_ad}/extend', [ProductAdController::class, 'extend'])
    ->name('product-ads.extend');
Route::delete('product-ads/bulk-destroy', [ProductAdController::class, 'bulkDestroy'])
    ->name('product-ads.bulk-destroy');
Route::get('product-ads/erp-data', [ProductAdController::class, 'erpData'])
    ->name('product-ads.erp-data');
Route::get('product-ads/check-duplicate', [ProductAdController::class, 'checkDuplicate'])
    ->name('product-ads.check-duplicate');
Route::get('product-ads/{product_ad}/stock-detail', [ProductAdController::class, 'stockDetail'])
    ->name('product-ads.stock-detail');
Route::resource('product-ads', ProductAdController::class);
Route::resource('product-ad-logs', ProductAdLogController::class);
Route::delete('/products/bulk-destroy', [ProductController::class, 'bulkDestroy'])
    ->name('products.bulk-destroy');
Route::get('/products/suggest', [ProductController::class, 'suggest'])
    ->name('products.suggest');
Route::resource('products', ProductController::class);
Route::resource('categories', CategoryController::class);
Route::resource('stores', StoreController::class);

Route::get('/sales-history', [SalesHistoryController::class, 'index'])->name('sales-history.index');

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');