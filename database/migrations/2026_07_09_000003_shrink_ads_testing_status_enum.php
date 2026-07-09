<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `ads.testing_status` semula testing/success/fail, tapi 'testing' tak pernah
 * benar-benar terpakai sebagai state tersimpan (kolom nullable, default NULL).
 * Disederhanakan jadi 2 nilai saja: success/fail. NULL sekarang MENJADI arti
 * "sedang masa testing" (bukan lagi ENUM 'testing' eksplisit) -- lihat
 * Ad::scopeTestingTab() dan Ad::getTestingBadgeAttribute().
 */
return new class extends Migration
{
    public function up(): void
    {
        // Jaga-jaga: kalau ada baris 'testing' tersisa, map ke NULL dulu
        // (ALTER akan menolak nilai yang tak ada di enum baru).
        DB::table('ads')->where('testing_status', 'testing')->update(['testing_status' => null]);

        DB::statement("ALTER TABLE ads MODIFY testing_status ENUM('success','fail') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ads MODIFY testing_status ENUM('testing','success','fail') NULL");
    }
};
