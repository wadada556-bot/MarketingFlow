<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index komposit untuk query History Penjualan:
     *   WHERE channel_id = ? AND sales_date BETWEEN ? AND ? [AND store_id = ?]
     * Tanpa ini MySQL cuma pakai index channel_id lalu memindai SEMUA baris
     * channel itu untuk filter tanggal (Using where). Dengan komposit,
     * channel_id (eq) + sales_date (range) langsung dari index → hanya jendela
     * tanggal yang dipindai. Krusial saat tabel orders besar.
     */
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $exists = collect(DB::select("SHOW INDEX FROM orders WHERE Key_name = 'orders_channel_date_store_index'"))->isNotEmpty();
        if ($exists) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['channel_id', 'sales_date', 'store_id'], 'orders_channel_date_store_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_channel_date_store_index');
            });
        }
    }
};
