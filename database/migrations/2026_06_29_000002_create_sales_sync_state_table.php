<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_sync_state')) {
            return;
        }

        Schema::create('sales_sync_state', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();          // mis. 'backfill' / 'daily'
            $table->timestamp('last_synced_at')->nullable();
            $table->date('cursor_date')->nullable();       // checkpoint backfill (tanggal terakhir yg sudah diproses)
            $table->unsignedInteger('cursor_page')->nullable();
            $table->string('status', 20)->default('idle'); // idle | running | done | failed
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_sync_state');
    }
};
