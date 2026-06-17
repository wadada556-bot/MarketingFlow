<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_store_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->date('tanggal');
            $table->bigInteger('gmv')->default(0);
            $table->integer('pesanan')->default(0);
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_store_stats');
    }
};
