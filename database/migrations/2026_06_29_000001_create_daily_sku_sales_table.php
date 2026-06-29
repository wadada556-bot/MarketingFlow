<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_sku_sales')) {
            return;
        }

        Schema::create('daily_sku_sales', function (Blueprint $table) {
            $table->id();
            $table->date('sales_date');                       // tanggal transaksi (WIB)
            $table->unsignedBigInteger('channel_id');         // 128 = Tokopedia, 131076 = TikTok
            $table->string('channel_name', 50)->nullable();
            $table->unsignedBigInteger('store_id')->default(0);
            $table->string('store_name', 150)->nullable();
            $table->string('sku', 100);                       // item_code dari detail order
            $table->string('parent_sku', 100)->nullable();    // SKU induk (tanpa suffix variasi)
            $table->string('product_name', 255)->nullable();
            $table->unsignedInteger('qty_terjual')->default(0);
            $table->bigInteger('omzet')->default(0);          // rupiah, sum(amount) net
            $table->unsignedInteger('order_count')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // 1 baris unik per (tanggal, toko, sku)
            $table->unique(['sales_date', 'store_id', 'sku'], 'daily_sku_sales_unique');
            $table->index('sales_date');
            $table->index('channel_id');
            $table->index('parent_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sku_sales');
    }
};
