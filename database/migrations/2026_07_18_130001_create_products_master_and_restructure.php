<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Redesign 5 tabel inti (2026-07-18, backup: C:\db-backup-marketing-flow\*_5core_*.sql):
     *
     * - `products` = MASTER baru (1 baris per SKU fisik, ganti `jubelio_inventory`;
     *   item_group_id dibuang — ditulis tapi tak pernah dibaca).
     * - `tiktok_listings`: kolom product_id di-rename `tiktok_product_id` (id milik
     *   TikTok, bukan FK products) supaya tidak rancu; synced_at dibuang.
     * - `tiktok_listing_skus`: jembatan listing↔products — listing_id FK +
     *   product_id FK->products + tiktok_sku_id; store_id/sku_code/match_sku/
     *   variation_value/synced_at dibuang (redundan/tak dibaca).
     * - `tiktok_listing_prices`: (listing_id FK, product_id FK) unique — semua kolom
     *   identitas duplikat dibuang.
     * - Histories dibuat ulang dengan nama kolom baru (tetap snapshot denormalized
     *   supaya riwayat terbaca walau listing/produk dihapus).
     *
     * FK bebas dipasang karena importer TikTok listings eksternal sudah dihapus
     * 2026-07-16 — kelima tabel kini 100% milik aplikasi ini.
     */
    public function up(): void
    {
        // 1. Master products ← jubelio_inventory
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku_code', 100)->unique('products_sku_code_unique');
            $table->string('parent_sku', 100)->index('products_parent_sku_index');
            $table->string('variation_label', 150)->nullable();
            $table->integer('stok')->default(0);
            $table->bigInteger('hpp')->default(0); // 0 = belum terisi; sync tak menimpa dgn 0
            $table->integer('po_qty')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            INSERT INTO products (sku_code, parent_sku, variation_label, stok, hpp, po_qty,
                                  synced_at, created_at, updated_at)
            SELECT sku_code, parent_sku, variation_label, stok, hpp, po_qty,
                   synced_at, created_at, updated_at
            FROM jubelio_inventory
        SQL);

        // 2. tiktok_listings: rename kolom + drop synced_at + unique baru
        DB::statement('ALTER TABLE tiktok_listings CHANGE product_id tiktok_product_id BIGINT UNSIGNED NOT NULL');
        // Unique BARU dibuat dulu — index unik lama menopang FK store_id, MySQL
        // menolak drop bila tak ada index lain yang diawali store_id.
        DB::statement('ALTER TABLE tiktok_listings ADD UNIQUE KEY tl_store_tiktok_product_unique (store_id, tiktok_product_id)');
        DB::statement('ALTER TABLE tiktok_listings DROP INDEX tiktok_listings_store_id_product_id_unique');
        DB::statement('ALTER TABLE tiktok_listings DROP COLUMN synced_at');

        // 3. tiktok_listing_skus baru (id lama dipertahankan)
        Schema::create('tiktok_listing_skus_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')
                ->constrained('tiktok_listings', 'id', 'tls_listing_id_foreign')->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products', 'id', 'tls_product_id_foreign')->cascadeOnDelete();
            $table->unsignedBigInteger('tiktok_sku_id');
            $table->timestamps();

            $table->unique(['listing_id', 'tiktok_sku_id'], 'tls_listing_tiktok_sku_unique');
            $table->index('product_id', 'tls_product_id_index');
        });

        // JOIN products = 1 baris orphan (sku_code tak ada di master) sengaja gugur.
        DB::statement(<<<'SQL'
            INSERT INTO tiktok_listing_skus_new (id, listing_id, product_id, tiktok_sku_id, created_at, updated_at)
            SELECT ts.id, ts.listing_id, pr.id, ts.sku_id, ts.created_at, ts.updated_at
            FROM tiktok_listing_skus ts
            JOIN products pr ON pr.sku_code = ts.sku_code
        SQL);

        // 4. tiktok_listing_prices baru
        Schema::create('tiktok_listing_prices_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')
                ->constrained('tiktok_listings', 'id', 'tlp_listing_id_foreign')->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products', 'id', 'tlp_product_id_foreign')->cascadeOnDelete();
            $table->bigInteger('retail_price')->default(0);
            $table->bigInteger('promotion_price')->default(0);
            $table->timestamps();

            $table->unique(['listing_id', 'product_id'], 'tlp_listing_product_unique');
            $table->index('product_id', 'tlp_product_id_index');
        });

        DB::statement(<<<'SQL'
            INSERT INTO tiktok_listing_prices_new (listing_id, product_id, retail_price,
                                                   promotion_price, created_at, updated_at)
            SELECT tl.id, pr.id, p.retail_price, p.promotion_price, p.created_at, p.updated_at
            FROM tiktok_listing_prices p
            JOIN tiktok_listings tl ON tl.store_id = p.store_id AND tl.tiktok_product_id = p.product_id
            JOIN products pr ON pr.sku_code = p.sku_code
        SQL);

        // 5. Histories baru (nama kolom baru) + copy isi lama
        Schema::create('tiktok_listing_price_histories_new', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('tiktok_product_id');
            $table->unsignedBigInteger('tiktok_sku_id');
            $table->string('sku_code', 100);
            $table->string('price_type', 20); // 'retail' | 'promotion'
            $table->bigInteger('old_price');
            $table->bigInteger('new_price');
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['store_id', 'tiktok_product_id', 'sku_code', 'changed_at'], 'tlph_listing_changed_index');
        });

        DB::statement(<<<'SQL'
            INSERT INTO tiktok_listing_price_histories_new
                (id, store_id, tiktok_product_id, tiktok_sku_id, sku_code, price_type,
                 old_price, new_price, changed_at)
            SELECT id, store_id, product_id, sku_id, sku_code, price_type,
                   old_price, new_price, changed_at
            FROM tiktok_listing_price_histories
        SQL);

        // 6. Ganti tabel lama dengan yang baru
        DB::unprepared('DROP TRIGGER IF EXISTS trg_tiktok_listing_prices_history');
        Schema::drop('tiktok_listing_skus');
        Schema::drop('tiktok_listing_prices');
        Schema::drop('tiktok_listing_price_histories');
        Schema::rename('tiktok_listing_skus_new', 'tiktok_listing_skus');
        Schema::rename('tiktok_listing_prices_new', 'tiktok_listing_prices');
        Schema::rename('tiktok_listing_price_histories_new', 'tiktok_listing_price_histories');
        Schema::drop('jubelio_inventory');

        // 7. Trigger snapshot baru — identitas diambil via SELECT karena baris harga
        //    kini hanya menyimpan listing_id + product_id.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_tiktok_listing_prices_history
            BEFORE UPDATE ON tiktok_listing_prices
            FOR EACH ROW
            BEGIN
                DECLARE v_store_id BIGINT UNSIGNED;
                DECLARE v_tiktok_product_id BIGINT UNSIGNED;
                DECLARE v_sku_code VARCHAR(100);
                DECLARE v_tiktok_sku_id BIGINT UNSIGNED;

                IF NEW.retail_price <> OLD.retail_price OR NEW.promotion_price <> OLD.promotion_price THEN
                    SELECT tl.store_id, tl.tiktok_product_id INTO v_store_id, v_tiktok_product_id
                    FROM tiktok_listings tl WHERE tl.id = OLD.listing_id;

                    SELECT pr.sku_code INTO v_sku_code
                    FROM products pr WHERE pr.id = OLD.product_id;

                    SELECT MIN(ts.tiktok_sku_id) INTO v_tiktok_sku_id
                    FROM tiktok_listing_skus ts
                    WHERE ts.listing_id = OLD.listing_id AND ts.product_id = OLD.product_id;

                    IF NEW.retail_price <> OLD.retail_price THEN
                        INSERT INTO tiktok_listing_price_histories
                            (store_id, tiktok_product_id, tiktok_sku_id, sku_code, price_type,
                             old_price, new_price, changed_at)
                        VALUES
                            (v_store_id, v_tiktok_product_id, COALESCE(v_tiktok_sku_id, 0), v_sku_code,
                             'retail', OLD.retail_price, NEW.retail_price, NOW());
                    END IF;

                    IF NEW.promotion_price <> OLD.promotion_price THEN
                        INSERT INTO tiktok_listing_price_histories
                            (store_id, tiktok_product_id, tiktok_sku_id, sku_code, price_type,
                             old_price, new_price, changed_at)
                        VALUES
                            (v_store_id, v_tiktok_product_id, COALESCE(v_tiktok_sku_id, 0), v_sku_code,
                             'promotion', OLD.promotion_price, NEW.promotion_price, NOW());
                    END IF;
                END IF;
            END
        SQL);
    }

    /** One-way: restore dari C:\db-backup-marketing-flow bila perlu. */
    public function down(): void
    {
        // sengaja kosong
    }
};
