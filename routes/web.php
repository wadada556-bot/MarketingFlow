<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/stock-alerts', [DashboardController::class, 'stockAlerts'])
    ->name('dashboard.stock-alerts');

Route::get('/products/variants', [ProductController::class, 'variants'])
    ->name('products.variants');
Route::get('/products/price-history', [ProductController::class, 'priceHistory'])
    ->name('products.price-history');
Route::get('/products/export', [ProductController::class, 'export'])
    ->name('products.export');
Route::post('/products/hpp', [ProductController::class, 'updateHpp'])
    ->name('products.update-hpp');
Route::post('/products/price', [ProductController::class, 'updatePrice'])
    ->name('products.update-price');
Route::post('/products/price/bulk', [ProductController::class, 'bulkUpdatePrice'])
    ->name('products.bulk-update-price');
Route::get('/products/price/bulk-search', [ProductController::class, 'bulkPriceSearch'])
    ->name('products.bulk-price-search');
Route::post('/products/price/bulk-apply', [ProductController::class, 'bulkPriceApply'])
    ->name('products.bulk-price-apply');
Route::resource('products', ProductController::class)->only(['index']);
Route::resource('stores', StoreController::class);

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');