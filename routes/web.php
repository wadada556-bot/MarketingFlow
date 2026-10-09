<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PriceComparisonController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/products');

Route::get('/products/variants', [ProductController::class, 'variants'])
    ->name('products.variants');
Route::get('/products/price-history', [ProductController::class, 'priceHistory'])
    ->name('products.price-history');
Route::get('/products/export', [ProductController::class, 'export'])
    ->name('products.export');
Route::get('/products/snapshots', [ProductController::class, 'snapshots'])
    ->name('products.snapshots');
// Harus sebelum route {date}/{file} di bawah, agar "download-all" tak tertangkap sbg {file}.
Route::get('/products/snapshots/{date}/download-all', [ProductController::class, 'downloadAllSnapshots'])
    ->name('products.snapshots.download-all');
Route::get('/products/snapshots/{date}/{file}', [ProductController::class, 'downloadSnapshot'])
    ->name('products.snapshots.download');
Route::post('/products/hpp', [ProductController::class, 'updateHpp'])
    ->name('products.update-hpp');
Route::post('/products/model-id', [ProductController::class, 'updateModelId'])
    ->name('products.update-model-id');
Route::post('/products/price',[ProductController::class, 'updatePrice'])
    ->name('products.update-price');
Route::post('/products/price/bulk', [ProductController::class, 'bulkUpdatePrice'])
    ->name('products.bulk-update-price');
Route::get('/products/price/bulk-search', [ProductController::class, 'bulkPriceSearch'])
    ->name('products.bulk-price-search');
Route::post('/products/price/bulk-apply', [ProductController::class, 'bulkPriceApply'])
    ->name('products.bulk-price-apply');
Route::post('/products/price/bulk-excel-parse', [ProductController::class, 'bulkPriceParseExcel'])
    ->name('products.bulk-price-excel-parse');
Route::post('/products/price/bulk-excel-match', [ProductController::class, 'bulkPriceMatchExcel'])
    ->name('products.bulk-price-excel-match');
Route::get('/products/price/bulk-excel-template', [ProductController::class, 'bulkPriceExcelTemplate'])
    ->name('products.bulk-price-excel-template');
Route::resource('products', ProductController::class)->only(['index']);
Route::resource('stores', StoreController::class);

Route::get('/price-comparison', [PriceComparisonController::class, 'index'])
    ->name('price-comparison.index');
Route::get('/price-comparison/export', [PriceComparisonController::class, 'export'])
    ->name('price-comparison.export');

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');