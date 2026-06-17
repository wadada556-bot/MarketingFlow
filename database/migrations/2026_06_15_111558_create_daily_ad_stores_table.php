<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_ad_stores', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->date('tanggal');
            $table->bigInteger('cost')->default(0);
            $table->decimal('roi', 8, 2)->default(0.00);
            $table->timestamps();

            $table->index('store_id');
            $table->index('tanggal');
            $table->unique(['store_id', 'tanggal'], 'uq_store_tanggal');
            $table->foreign('store_id', 'fk_daily_ad_store')->references('id')->on('stores');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_ad_stores');
    }
};
