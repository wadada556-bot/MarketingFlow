<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jubelio_inventory', function (Blueprint $table) {
            $table->string('sku_code', 100)->primary();
            $table->string('parent_sku', 100)->index();
            $table->integer('stok')->default(0);
            $table->bigInteger('hpp')->default(0);
            $table->integer('po_qty')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jubelio_inventory');
    }
};
