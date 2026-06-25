<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index tambahan untuk performa query yang tidak tercakup migration sebelumnya.
 *
 * Murni penambahan index — tidak mengubah struktur kolom atau data.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Composite index untuk filter utama halaman product-ads:
        //   WHERE status = ? AND testing_status = ?
        // Index individual (status) dan (testing_status) sudah ada, tapi composite
        // memungkinkan MySQL memenuhi kedua kondisi dalam satu index scan.
        Schema::table('product_ads', function (Blueprint $table) {
            $table->index(['status', 'testing_status'], 'idx_pa_status_testing');
        });

        // Per-store time-range lookup:
        //   WHERE store_id = ? AND tanggal BETWEEN ...
        // Index covering dashboard (tanggal, store_id, ...) tidak efisien untuk pola ini
        // karena store_id bukan leading column.
        Schema::table('daily_store_stats', function (Blueprint $table) {
            $table->index(['store_id', 'tanggal'], 'idx_dss_store_date');
        });

        // Product+store time-range lookup (dipakai untuk detail ROAS per produk per toko):
        //   WHERE product_id = ? AND store_id = ? AND tanggal BETWEEN ...
        Schema::table('daily_product_ads', function (Blueprint $table) {
            $table->index(['product_id', 'store_id', 'tanggal'], 'idx_dpa_product_store_date');
        });
    }

    public function down(): void
    {
        Schema::table('product_ads', function (Blueprint $table) {
            $table->dropIndex('idx_pa_status_testing');
        });

        Schema::table('daily_store_stats', function (Blueprint $table) {
            $table->dropIndex('idx_dss_store_date');
        });

        Schema::table('daily_product_ads', function (Blueprint $table) {
            $table->dropIndex('idx_dpa_product_store_date');
        });
    }
};
