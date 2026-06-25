<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Covering indexes untuk query agregasi dashboard.
 *
 * Dashboard menjalankan WHERE tanggal BETWEEN ... + SUM/AVG + GROUP BY.
 * Dengan covering index (tanggal di depan, diikuti kolom group & kolom agregasi),
 * MySQL dapat memenuhi query langsung dari index tanpa membaca baris tabel.
 *
 * Murni penambahan index — tidak mengubah struktur kolom atau data.
 */
return new class extends Migration
{
    public function up(): void
    {
        // curSums / prevSums / chartRaw:
        //   SELECT store_id, SUM(gmv), SUM(pesanan) WHERE tanggal BETWEEN ... GROUP BY store_id
        Schema::table('daily_store_stats', function (Blueprint $table) {
            $table->index(['tanggal', 'store_id', 'gmv', 'pesanan'], 'idx_dss_dashboard');
        });

        // curAds / prevAds:
        //   SELECT store_id, SUM(cost), SUM(roi*cost) WHERE tanggal BETWEEN ... GROUP BY store_id
        Schema::table('daily_ad_stores', function (Blueprint $table) {
            $table->index(['tanggal', 'store_id', 'cost', 'roi'], 'idx_das_dashboard');
        });

        // adsPerformance:
        //   ... WHERE tanggal BETWEEN ... GROUP BY product_id, store_id (+ AVG(roi))
        Schema::table('daily_product_ads', function (Blueprint $table) {
            $table->index(['tanggal', 'product_id', 'store_id', 'roi'], 'idx_dpa_dashboard');
        });
    }

    public function down(): void
    {
        Schema::table('daily_store_stats', function (Blueprint $table) {
            $table->dropIndex('idx_dss_dashboard');
        });

        Schema::table('daily_ad_stores', function (Blueprint $table) {
            $table->dropIndex('idx_das_dashboard');
        });

        Schema::table('daily_product_ads', function (Blueprint $table) {
            $table->dropIndex('idx_dpa_dashboard');
        });
    }
};
