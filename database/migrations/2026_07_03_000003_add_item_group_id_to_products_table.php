<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `item_group_id` = penghubung STABIL ke jubelio_inventory (numerik, tahan
     * terhadap SKU yang di-rename). `parent_sku` tetap identitas utama. Diisi
     * hanya untuk produk yang parent_sku-nya memetakan ke TEPAT 1 item_group
     * Jubelio; yang multi-group (mis. ZQ) dibiarkan null (ditangani manual).
     */
    public function up(): void
    {
        if (! Schema::hasTable('products') || Schema::hasColumn('products', 'item_group_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('item_group_id')->nullable()->after('parent_sku')->index();
        });

        // Peta parent_sku (upper) → item_group_id, HANYA yang 1 group.
        $single = DB::table('jubelio_inventory')
            ->selectRaw('UPPER(parent_sku) as psku, MIN(item_group_id) as gid, COUNT(DISTINCT item_group_id) as gc')
            ->groupBy(DB::raw('UPPER(parent_sku)'))
            ->having('gc', '=', 1)
            ->get()
            ->keyBy('psku');

        foreach (DB::table('products')->get() as $p) {
            $key = strtoupper(trim($p->parent_sku));
            if (isset($single[$key])) {
                DB::table('products')->where('id', $p->id)
                    ->update(['item_group_id' => $single[$key]->gid]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'item_group_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('item_group_id');
            });
        }
    }
};
