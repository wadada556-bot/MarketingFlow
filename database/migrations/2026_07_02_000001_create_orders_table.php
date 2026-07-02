<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            return;
        }

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->date('sales_date');                            // transaction_date (WIB)
            $table->unsignedBigInteger('channel_id');              // 131076 (dari header order)
            $table->string('channel_name', 50)->nullable();        // channel_name dari header
            $table->unsignedBigInteger('store_id')->default(0);    // store_id dari header
            $table->string('store_name', 150)->nullable();         // store_name dari header order
            $table->string('sku_parent', 100)->nullable();         // item_code di-strip "-angka"
            $table->string('sku_variant', 100);                    // item_code (variasi)
            $table->unsignedInteger('qty')->default(0);            // qty item

            $table->unsignedBigInteger('salesorder_id');           // referensi order induk (tidak unik)
            $table->unsignedBigInteger('salesorder_detail_id');    // 1 per item -> kunci dedup
            $table->timestamps();

            // 1 baris unik per item order; sync aman di-upsert ulang
            $table->unique('salesorder_detail_id', 'orders_detail_unique');
            $table->index('sales_date');
            $table->index('channel_id');
            $table->index('salesorder_id');
            $table->index('sku_parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
