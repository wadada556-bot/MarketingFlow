<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Jadwal otomatis ────────────────────────────────────────────────────────
use Illuminate\Support\Facades\Schedule;

// Stok+HPP(insert baru)+PO Jubelio (full katalog) -> tabel products (dipakai menu Products)
Schedule::command('jubelio:sync-inventory')->dailyAt('23:45')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('hpp:sync')->dailyAt('00:00')->timezone('Asia/Jakarta')->withoutOverlapping();
// Arsip Excel produk per toko, harian (termasuk weekend) -> storage/app/product-snapshots, rolling 7 hari
Schedule::command('products:snapshot-export')->dailyAt('01:00')->timezone('Asia/Jakarta')->withoutOverlapping();
