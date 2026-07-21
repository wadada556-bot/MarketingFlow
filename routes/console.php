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
