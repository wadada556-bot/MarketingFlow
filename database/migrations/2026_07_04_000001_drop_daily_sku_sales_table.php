<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop tabel `daily_sku_sales` — pendekatan agregasi penjualan lama yang sudah
 * digantikan tabel `orders` (per item order). Tak ada model/query yang memakainya.
 *
 * Catatan: tabel `store_sales` yang sempat disebut TIDAK pernah dibuat migration
 * mana pun (file create_store_sales_table justru membuat daily_store_stats yang
 * masih dipakai), jadi tidak ada yang perlu di-drop untuk itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('daily_sku_sales');
    }

    public function down(): void
    {
        if (Schema::hasTable('daily_sku_sales')) {
            return;
        }

        Schema::create('daily_sku_sales', function (Blueprint $table) {
            $table->id();
            $table->date('sales_date');
            $table->unsignedBigInteger('channel_id');
            $table->string('channel_name', 50)->nullable();
            $table->unsignedBigInteger('store_id')->default(0);
            $table->string('store_name', 150)->nullable();
            $table->string('sku', 100);
            $table->string('parent_sku', 100)->nullable();
            $table->string('product_name', 255)->nullable();
            $table->unsignedInteger('qty_terjual')->default(0);
            $table->bigInteger('omzet')->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['sales_date', 'store_id', 'sku'], 'daily_sku_sales_unique');
            $table->index('sales_date');
            $table->index('channel_id');
            $table->index('parent_sku');
        });
    }
};
