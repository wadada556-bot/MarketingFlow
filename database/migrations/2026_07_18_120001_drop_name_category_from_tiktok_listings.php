<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * product_name & category tidak pernah dibaca aplikasi (permintaan user
     * 2026-07-18). CATATAN: importer TikTok listings eksternal (Python) mungkin
     * masih mencoba menulis kolom ini — kalau import berikutnya error, sesuaikan
     * importer-nya (buang kedua kolom dari INSERT).
     */
    public function up(): void
    {
        Schema::table('tiktok_listings', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('tiktok_listings', function (Blueprint $table) {
            $table->string('product_name')->nullable();
            $table->string('category')->nullable();
        });
    }
};
