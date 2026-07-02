<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `parent_sku` di jubelio_inventory diturunkan dari grouping item_group_id
     * Jubelio (longest common prefix) — untuk produk 1-varian dgn suffix "-N"
     * (mis. C223-ANT161-1) suffix TIDAK ikut dilucuti, jadi tak cocok dengan
     * konvensi app lain (orders.sku_parent & products.parent_sku pakai strip
     * "-\d+$"). Akibatnya lookup stok/HPP/PO Product Ads & Dashboard meleset →
     * "Tidak ada data SKU dari ERP" walau penjualannya ada.
     *
     * `match_sku` = sku_code yang dinormalisasi PERSIS seperti
     * OrderSyncService::parentSku() (strtoupper + strip "-\d+$"), supaya jadi
     * kunci join yang konsisten dgn orders & products.
     */
    public function up(): void
    {
        if (! Schema::hasTable('jubelio_inventory') || Schema::hasColumn('jubelio_inventory', 'match_sku')) {
            return;
        }

        Schema::table('jubelio_inventory', function (Blueprint $table) {
            $table->string('match_sku', 100)->nullable()->after('parent_sku')->index();
        });

        // Backfill baris yang sudah ada — logika identik dgn OrderSyncService::parentSku().
        DB::table('jubelio_inventory')->orderBy('sku_code')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('jubelio_inventory')
                    ->where('sku_code', $row->sku_code)
                    ->update(['match_sku' => self::normalize($row->sku_code)]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('jubelio_inventory') && Schema::hasColumn('jubelio_inventory', 'match_sku')) {
            Schema::table('jubelio_inventory', function (Blueprint $table) {
                $table->dropColumn('match_sku');
            });
        }
    }

    private static function normalize(string $sku): string
    {
        return strtoupper(preg_replace('/-\d+$/', '', $sku) ?? $sku);
    }
};
