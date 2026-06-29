<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Jadwal otomatis ────────────────────────────────────────────────────────
use Illuminate\Support\Facades\Schedule;

Schedule::command('hpp:sync')->dailyAt('08:30')->timezone('Asia/Jakarta');
Schedule::command('notify:stock-check')->dailyAt('09:00')->timezone('Asia/Jakarta');
Schedule::command('notify:stock-check')->dailyAt('16:00')->timezone('Asia/Jakarta');
Schedule::command('sales:sync')->dailyAt('07:30')->timezone('Asia/Jakarta')->withoutOverlapping();
