<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pembersihan besar (keputusan user 2026-07-18, backup DB sudah dibuat):
     *
     * - Iklan: seluruh pipeline ads dihapus (importer C:\ads dihentikan; tidak
     *   ada satu pun kode app yang membaca tabel-tabel ini).
     * - Peringatan Stok dihapus total → products/product_ads/product_ad_store
     *   (+ log & tabel daily_*) tidak dibutuhkan lagi.
     * - Orders dihapus (sync sudah lama nonaktif; pembaca terakhirnya adalah
     *   Peringatan Stok yang ikut dihapus). Di prod tabel ini 1,6 juta baris.
     * - store_sku_sync_runs/failures & store_sales: sisa pipeline lama yang
     *   hanya pernah ada di prod / tak pernah terpakai.
     *
     * YANG SENGAJA DIPERTAHANKAN: sku_hpp (baseline harian deteksi perubahan
     * HPP untuk email hpp:sync — jubelio_inventory.hpp tak bisa jadi baseline
     * karena ditimpa jubelio:sync-inventory tiap 30 menit).
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'ad_logs',
            'ad_weekly_performances',
            'ads',
            'ads_old',
            'ad_products_old',
            'daily_ad_stores',
            'daily_product_ads',
            'daily_store_stats',
            'product_ad_logs',
            'product_ad_store',
            'product_ads',
            'products',
            'orders',
            'sales_sync_state',
            'store_sales',
            'store_sku_sync_failures',
            'store_sku_sync_runs',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    /** One-way: restore hanya dari backup user. */
    public function down(): void
    {
        // sengaja kosong
    }
};
