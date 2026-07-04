<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Listing (produk induk) TikTok per toko, dari export TikTok Seller Center
     * "batch edit sales information". Setiap toko melisting produk yang sama
     * dengan `product_id` TikTok yang BERBEDA, jadi datanya ber-dimensi toko —
     * tidak bisa ditaruh di jubelio_inventory (master SKU global).
     *
     * Grain = (store_id, product_id). `product_name` & `category` hanya
     * bergantung pada product_id (bukan tiap varian), jadi disimpan di sini
     * agar tak berulang; varian ada di tiktok_listing_skus.
     *
     * Beda kanal dgn store_sku_prices (itu scrape Tokopedia), jadi dipisah.
     */
    public function up(): void
    {
        Schema::create('tiktok_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id'); // ID produk TikTok (per toko)
            $table->string('product_name', 255)->nullable();
            $table->string('category', 150)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_listings');
    }
};
