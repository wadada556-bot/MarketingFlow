<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'last_modified')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            // last_modified dari header order Jubelio — dipakai sync incremental:
            // order hanya di-fetch ulang kalau nilai ini berubah.
            $table->dateTime('last_modified')->nullable()->after('salesorder_detail_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'last_modified')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('last_modified');
            });
        }
    }
};
