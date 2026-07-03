<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Harga jual per (toko, SKU) hasil scrape Tokopedia Seller PDP (lihat
     * generate-diskon-tiktok/main.py). Beda dgn jubelio_inventory yang global
     * per SKU — harga bisa berbeda antar toko, jadi butuh dimensi store.
     *
     *   retail_price    = Harga Jual Normal (originalPrice/price)
     *   promotion_price = Harga Diskon (discountPrice, 0 bila tak ada campaign)
     *
     * `match_sku` = sku_code ternormalisasi (strtoupper + strip "-\d+$"), kunci
     * join ke products.parent_sku untuk tampilan rentang min–max per induk di
     * menu products. Di-upsert langsung dari Python (unique store_id+sku_code).
     */
    public function up(): void
    {
        Schema::create('store_sku_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('sku_code', 100);
            $table->string('match_sku', 100)->index();
            $table->bigInteger('retail_price')->default(0);
            $table->bigInteger('promotion_price')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'sku_code']);
            $table->index(['store_id', 'match_sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_sku_prices');
    }
};
