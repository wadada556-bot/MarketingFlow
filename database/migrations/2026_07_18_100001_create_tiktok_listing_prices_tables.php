<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Harga per LISTING TikTok: satu baris per (store_id, product_id, sku_code).
     * Menggantikan `store_sku_prices` (per toko+SKU saja — tidak bisa membedakan
     * harga bila satu sku_code terdaftar di >1 product_id pada toko yang sama)
     * dan `tiktok_listing_price_overrides` (solusi override yang ditolak user).
     *
     * Scraper Tokopedia yang dulu meng-upsert store_sku_prices sudah pensiun
     * (task "Sync Harga Toko Harian" dicabut 2026-07-17) — harga kini dikelola
     * manual lewat UI menu Products, jadi app ini pemilik tunggal tabel ini.
     *
     * Semantik harga mengikuti tabel lama: 0 = tidak ada harga/diskon.
     */
    public function up(): void
    {
        Schema::create('tiktok_listing_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');   // product_id TikTok (scoped per toko)
            $table->unsignedBigInteger('sku_id');       // sku_id TikTok (dari tiktok_listing_skus)
            $table->string('sku_code', 100);            // seller SKU (= jubelio_inventory.sku_code)
            $table->bigInteger('retail_price')->default(0);
            $table->bigInteger('promotion_price')->default(0);
            $table->timestamp('synced_at')->nullable(); // kebaruan data (chip di UI)
            $table->timestamps();

            // Nama eksplisit: nama otomatis Laravel melebihi batas 64 karakter MySQL.
            $table->unique(['store_id', 'product_id', 'sku_code'], 'tlp_store_product_sku_unique');
            $table->index(['store_id', 'sku_code'], 'tlp_store_sku_index');
        });

        Schema::create('tiktok_listing_price_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('sku_id');
            $table->string('sku_code', 100);
            $table->string('price_type', 20); // 'retail' | 'promotion'
            $table->bigInteger('old_price');
            $table->bigInteger('new_price');
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['store_id', 'product_id', 'sku_code', 'changed_at'], 'tlph_listing_changed_index');
        });

        // Backfill: fan-out harga lama per (toko, SKU) ke SEMUA listing yang memuat
        // SKU itu. SKU yang punya harga tapi tak punya listing TikTok sengaja tidak
        // dibawa (UI hanya menampilkan harga lewat listing). GROUP BY karena satu
        // sku_code bisa terdaftar 2x (sku_id berbeda) dalam SATU listing yang sama —
        // harga tetap satu per (store, product, sku_code), ambil sku_id terkecil.
        if (Schema::hasTable('store_sku_prices')) {
            DB::statement(<<<'SQL'
                INSERT INTO tiktok_listing_prices
                    (store_id, product_id, sku_id, sku_code, retail_price, promotion_price,
                     synced_at, created_at, updated_at)
                SELECT ts.store_id, tl.product_id, MIN(ts.sku_id), ts.sku_code,
                       MIN(p.retail_price), MIN(p.promotion_price), MIN(p.synced_at), NOW(), NOW()
                FROM tiktok_listing_skus ts
                JOIN tiktok_listings tl ON tl.id = ts.listing_id AND tl.store_id = ts.store_id
                JOIN store_sku_prices p ON p.store_id = ts.store_id AND p.sku_code = ts.sku_code
                GROUP BY ts.store_id, tl.product_id, ts.sku_code
            SQL);
        }

        // Override per-listing yang sempat dibuat (kalau ada) menang atas harga sync.
        if (Schema::hasTable('tiktok_listing_price_overrides')) {
            DB::statement(<<<'SQL'
                UPDATE tiktok_listing_prices tlp
                JOIN tiktok_listing_price_overrides ov
                  ON ov.store_id = tlp.store_id
                 AND ov.product_id = tlp.product_id
                 AND ov.sku_code = tlp.sku_code
                SET tlp.promotion_price = ov.promotion_price, tlp.updated_at = NOW()
            SQL);
        }

        // Trigger dibuat SETELAH backfill supaya migrasi data tidak tercatat
        // sebagai "perubahan harga". Hanya insert bila nilai benar-benar berubah.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_tiktok_listing_prices_history
            BEFORE UPDATE ON tiktok_listing_prices
            FOR EACH ROW
            BEGIN
                IF NEW.retail_price <> OLD.retail_price THEN
                    INSERT INTO tiktok_listing_price_histories
                        (store_id, product_id, sku_id, sku_code, price_type, old_price, new_price, changed_at)
                    VALUES
                        (OLD.store_id, OLD.product_id, OLD.sku_id, OLD.sku_code, 'retail',
                         OLD.retail_price, NEW.retail_price, NOW());
                END IF;

                IF NEW.promotion_price <> OLD.promotion_price THEN
                    INSERT INTO tiktok_listing_price_histories
                        (store_id, product_id, sku_id, sku_code, price_type, old_price, new_price, changed_at)
                    VALUES
                        (OLD.store_id, OLD.product_id, OLD.sku_id, OLD.sku_code, 'promotion',
                         OLD.promotion_price, NEW.promotion_price, NOW());
                END IF;
            END
        SQL);

        // Tabel harga lama tidak dipakai lagi. Riwayat lama tidak dimigrasikan
        // (tak punya product_id — keputusan user: mulai bersih; data ada di backup).
        DB::unprepared('DROP TRIGGER IF EXISTS trg_store_sku_prices_history');
        Schema::dropIfExists('store_sku_price_histories');
        Schema::dropIfExists('store_sku_prices');
        Schema::dropIfExists('tiktok_listing_price_overrides');
    }

    /** One-way: tabel lama tidak dipulihkan (restore dari backup bila perlu). */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_tiktok_listing_prices_history');
        Schema::dropIfExists('tiktok_listing_price_histories');
        Schema::dropIfExists('tiktok_listing_prices');
    }
};
