<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat perubahan harga per (toko, SKU). Diisi OTOMATIS oleh trigger DB
     * `trg_store_sku_prices_history` setiap kali `store_sku_prices` di-update —
     * termasuk saat script Python (generate-diskon-tiktok/main.py) melakukan
     * upsert (INSERT ... ON DUPLICATE KEY UPDATE menjalankan bagian UPDATE saat
     * baris sudah ada), jadi tak perlu mengubah script eksternal.
     *
     * Satu baris = satu jenis harga yang berubah pada satu event sync:
     *   price_type = 'retail'    → Harga Jual Normal berubah
     *   price_type = 'promotion' → Harga Diskon berubah (mis. diskon dihapus:
     *                              new_price = 0)
     *
     * old_price/new_price + changed_at menjawab kebutuhan tampilan detail:
     * harga awal → harga berubah → kapan (tanggal & jam) perubahannya.
     */
    public function up(): void
    {
        Schema::create('store_sku_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('sku_code', 100);
            $table->string('price_type', 20); // 'retail' | 'promotion'
            $table->bigInteger('old_price');
            $table->bigInteger('new_price');
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['store_id', 'sku_code', 'changed_at']);
        });

        // Trigger: catat perubahan retail/promotion setiap UPDATE. Hanya insert
        // bila nilainya benar-benar berubah (bukan sekadar re-sync harga sama).
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_store_sku_prices_history
            BEFORE UPDATE ON store_sku_prices
            FOR EACH ROW
            BEGIN
                IF NEW.retail_price <> OLD.retail_price THEN
                    INSERT INTO store_sku_price_histories
                        (store_id, sku_code, price_type, old_price, new_price, changed_at)
                    VALUES
                        (OLD.store_id, OLD.sku_code, 'retail', OLD.retail_price, NEW.retail_price, NOW());
                END IF;

                IF NEW.promotion_price <> OLD.promotion_price THEN
                    INSERT INTO store_sku_price_histories
                        (store_id, sku_code, price_type, old_price, new_price, changed_at)
                    VALUES
                        (OLD.store_id, OLD.sku_code, 'promotion', OLD.promotion_price, NEW.promotion_price, NOW());
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_store_sku_prices_history');
        Schema::dropIfExists('store_sku_price_histories');
    }
};
