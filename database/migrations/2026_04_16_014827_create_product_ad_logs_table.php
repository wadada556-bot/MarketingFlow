<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_ad_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_ad_id')->constrained()->cascadeOnDelete();
            $table->timestamp('action_date')->index();
            $table->text('description')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_ad_logs');
    }
};
