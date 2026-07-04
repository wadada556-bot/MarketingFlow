<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fitur kategori produk dihapus total. Buang kolom products.category_id
     * lalu drop tabel categories.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('categories');
    }

    public function down(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('category_id')->nullable()->index()->after('id');
        });
    }
};
