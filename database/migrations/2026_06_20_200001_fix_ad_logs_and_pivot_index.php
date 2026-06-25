<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-3: Drop TEXT index pada product_ad_logs.description (sia-sia, slows INSERT/UPDATE).
 * A-4: Tambah composite index (product_ad_id, store_id) pada pivot product_ad_store
 *      agar whereHas('stores', ...) lebih efisien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_ad_logs', function (Blueprint $table) {
            $table->dropIndex(['description']);
        });

        Schema::table('product_ad_store', function (Blueprint $table) {
            $table->index(['product_ad_id', 'store_id'], 'idx_pas_ad_store');
        });
    }

    public function down(): void
    {
        Schema::table('product_ad_store', function (Blueprint $table) {
            $table->dropIndex('idx_pas_ad_store');
        });

        Schema::table('product_ad_logs', function (Blueprint $table) {
            $table->text('description')->index()->change();
        });
    }
};
