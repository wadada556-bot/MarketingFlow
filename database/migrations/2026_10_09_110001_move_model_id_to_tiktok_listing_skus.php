<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ID Model dipindah dari level listing ke level variasi (tiktok_listing_skus),
     * karena satu listing bisa berisi bundling beberapa model. NULL = pakai nilai
     * turunan dari sku_code (lihat ProductController::variantKey). Nilai manual
     * yang sudah ada di level listing disalin ke semua variasi listing tsb.
     */
    public function up(): void
    {
        Schema::table('tiktok_listing_skus', function (Blueprint $table) {
            $table->string('model_id', 100)->nullable()->after('tiktok_sku_id')->index();
        });

        DB::statement(
            'UPDATE tiktok_listing_skus ts
             JOIN tiktok_listings tl ON tl.id = ts.listing_id
             SET ts.model_id = tl.model_id
             WHERE tl.model_id IS NOT NULL'
        );

        Schema::table('tiktok_listings', function (Blueprint $table) {
            $table->dropIndex(['model_id']);
            $table->dropColumn('model_id');
        });
    }

    public function down(): void
    {
        Schema::table('tiktok_listings', function (Blueprint $table) {
            $table->string('model_id', 100)->nullable()->after('tiktok_product_id')->index();
        });

        Schema::table('tiktok_listing_skus', function (Blueprint $table) {
            $table->dropIndex(['model_id']);
            $table->dropColumn('model_id');
        });
    }
};
