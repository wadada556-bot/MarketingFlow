<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Varian (SKU) TikTok per toko — satu baris per (store_id, sku_id) TikTok.
     * Menautkan `sku_id` TikTok ke `sku_code` internal (dari kolom seller_sku
     * di export; cocok 100% dgn jubelio_inventory.sku_code).
     *
     * Di-key oleh sku_id (bukan sku_code) karena satu SKU fisik kadang dilisting
     * di >1 listing TikTok pada toko yang sama (mis. 95 kasus di Caramel), jadi
     * satu sku_code bisa punya >1 sku_id.
     *
     * `match_sku` = sku_code ternormalisasi (strtoupper + strip "-\d+$"), kunci
     * join konsisten ke orders/products/jubelio_inventory, seperti tabel lain.
     */
    public function up(): void
    {
        Schema::create('tiktok_listing_skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('tiktok_listings')->cascadeOnDelete();
            $table->unsignedBigInteger('sku_id'); // ID varian TikTok (per toko)
            $table->string('sku_code', 100);       // = seller_sku (SKU internal)
            $table->string('match_sku', 100)->index();
            $table->string('variation_value', 150)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'sku_id']);
            $table->index(['store_id', 'sku_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_listing_skus');
    }
};
