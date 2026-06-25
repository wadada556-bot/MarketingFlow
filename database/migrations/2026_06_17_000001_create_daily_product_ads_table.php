<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_product_ads')) {
            return;
        }

        Schema::create('daily_product_ads', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->date('tanggal');
            $table->decimal('roi', 8, 2)->default(0.00);
            $table->timestamps();

            $table->index('tanggal');
            $table->foreign('product_id', 'daily_product_ads_product_id_foreign')
                ->references('id')->on('products')->onDelete('set null');
            $table->foreign('store_id', 'daily_product_ads_store_id_foreign')
                ->references('id')->on('stores')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_product_ads');
    }
};
