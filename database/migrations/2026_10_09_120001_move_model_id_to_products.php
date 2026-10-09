<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ID Model dipindah ke master `products` (satu nilai per sku_code, berlaku di
     * semua toko). NULL = pakai nilai turunan dari sku_code (ProductController::variantKey).
     * Nilai manual yang sudah ada per (listing, SKU) disalin ke produknya; bila satu
     * SKU punya nilai berbeda antar toko, dipakai yang paling sering muncul.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('model_id', 100)->nullable()->after('variation_label')->index();
        });

        $best = [];   // product_id => [model_id, jumlah]
        DB::table('tiktok_listing_skus')
            ->whereNotNull('model_id')
            ->groupBy('product_id', 'model_id')
            ->orderBy('product_id')
            ->get(['product_id', 'model_id', DB::raw('COUNT(*) as cnt')])
            ->each(function ($r) use (&$best) {
                if (! isset($best[$r->product_id]) || $r->cnt > $best[$r->product_id][1]) {
                    $best[$r->product_id] = [$r->model_id, (int) $r->cnt];
                }
            });
        foreach ($best as $productId => [$modelId]) {
            DB::table('products')->where('id', $productId)->update(['model_id' => $modelId]);
        }

        Schema::table('tiktok_listing_skus', function (Blueprint $table) {
            $table->dropIndex(['model_id']);
            $table->dropColumn('model_id');
        });
    }

    public function down(): void
    {
        Schema::table('tiktok_listing_skus', function (Blueprint $table) {
            $table->string('model_id', 100)->nullable()->after('tiktok_sku_id')->index();
        });

        DB::statement(
            'UPDATE tiktok_listing_skus ts
             JOIN products pr ON pr.id = ts.product_id
             SET ts.model_id = pr.model_id
             WHERE pr.model_id IS NOT NULL'
        );

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['model_id']);
            $table->dropColumn('model_id');
        });
    }
};
