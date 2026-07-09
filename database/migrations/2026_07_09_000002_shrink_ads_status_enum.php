<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `ads.status` semula active/stopped/completed (dibuat mengikuti konvensi
 * product_ads lama), tapi tak pernah dipakai apa pun selain default kolom —
 * tak ada UI yang mengisinya, semua baris tetap 'active'. Disederhanakan jadi
 * 2 nilai saja: active/stopped, plus UI toggle untuk mengubahnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Jaga-jaga: kalau kelak ada baris 'completed', map dulu sebelum enum menyempit
        // (ALTER akan menolak nilai yang tak ada di enum baru).
        DB::table('ads')->where('status', 'completed')->update(['status' => 'stopped']);

        DB::statement("ALTER TABLE ads MODIFY status ENUM('active','stopped') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ads MODIFY status ENUM('active','stopped','completed') NOT NULL DEFAULT 'active'");
    }
};
