<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalisasi lanjutan (2026-07-18):
     *
     * - `sku_hpp` di-drop: isinya duplikat penuh `jubelio_inventory.hpp`
     *   (11.586 SKU identik). Dulu dipakai sebagai baseline harian deteksi
     *   perubahan HPP untuk email hpp:sync, karena jubelio:sync-inventory
     *   (30-menitan) ikut menimpa hpp. Sekarang sync 30-menitan TIDAK lagi
     *   meng-update hpp (hanya stok/PO/label), sehingga jubelio_inventory.hpp
     *   sendiri menjadi baseline — satu sumber kebenaran HPP.
     * - `notification_logs` di-drop: penulis satu-satunya adalah
     *   NotifyStockCheck (fitur Peringatan Stok) yang sudah dihapus; tidak ada
     *   kode yang membaca/menulisnya lagi.
     */
    public function up(): void
    {
        Schema::dropIfExists('sku_hpp');
        Schema::dropIfExists('notification_logs');
    }

    /** One-way: restore hanya dari backup user. */
    public function down(): void
    {
        // sengaja kosong
    }
};
