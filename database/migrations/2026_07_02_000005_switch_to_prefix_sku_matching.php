<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pindah ke pencocokan produk↔SKU berbasis PREFIX (lihat App\Support\SkuMatch).
 *
 * - Kolom `jubelio_inventory.match_sku` (dari migration 000004) TIDAK dipakai
 *   lagi: normalisasi strip "-\d+$" gagal untuk SKU spt HTT1/BM-LCAZD01 dan
 *   malah meregresi produk yg tadinya cocok via parent_sku. Prefix menggantikan
 *   semua — kolomnya di-drop.
 * - Tambah index `orders.sku_variant` supaya lookup `sku_variant LIKE 'X%'`
 *   (dipakai DailySalesQueryService) tetap cepat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jubelio_inventory') && Schema::hasColumn('jubelio_inventory', 'match_sku')) {
            Schema::table('jubelio_inventory', function (Blueprint $table) {
                $table->dropColumn('match_sku');
            });
        }

        if (Schema::hasTable('orders') && ! $this->hasIndex('orders', 'orders_sku_variant_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('sku_variant');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && $this->hasIndex('orders', 'orders_sku_variant_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_sku_variant_index');
            });
        }

        if (Schema::hasTable('jubelio_inventory') && ! Schema::hasColumn('jubelio_inventory', 'match_sku')) {
            Schema::table('jubelio_inventory', function (Blueprint $table) {
                $table->string('match_sku', 100)->nullable()->after('parent_sku')->index();
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return count(
            Schema::getConnection()->select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index])
        ) > 0;
    }
};
