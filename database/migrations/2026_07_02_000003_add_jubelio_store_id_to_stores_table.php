<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapping nama toko (di tabel `stores`) -> store_id Jubelio (dipakai di
     * tabel `orders`). Kedua sistem tak berbagi id, jadi kolom ini yang
     * menjembatani agar Product Ads bisa membatasi penjualan ke toko iklannya
     * tanpa tebak-tebakan nama (str_contains yang rapuh). Cocokkan by name
     * (case-insensitive lewat collation default MySQL) supaya id lokal boleh beda.
     */
    private const MAP = [
        'caramel aksesoris' => 76823,
        'minzo store'       => 68830,
        'moonklaz'          => 117726,
        'nomide store'      => 115435,
        'topi kece'         => 85009,
        'topi keren'        => 67824,
        'yarra store'       => 48087,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('stores') || Schema::hasColumn('stores', 'jubelio_store_id')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedBigInteger('jubelio_store_id')->nullable()->unique()->after('name');
        });

        foreach (self::MAP as $name => $jubelioId) {
            DB::table('stores')->where('name', $name)->update(['jubelio_store_id' => $jubelioId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'jubelio_store_id')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('jubelio_store_id');
            });
        }
    }
};
