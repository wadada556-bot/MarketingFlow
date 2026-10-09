<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ID Model = override manual (per listing) untuk pengelompokan produk lintas toko.
     * NULL = pakai nilai turunan dari sku_code (lihat ProductController::variantKey).
     */
    public function up(): void
    {
        Schema::table('tiktok_listings', function (Blueprint $table) {
            $table->string('model_id', 100)->nullable()->after('tiktok_product_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tiktok_listings', function (Blueprint $table) {
            $table->dropColumn('model_id');
        });
    }
};
