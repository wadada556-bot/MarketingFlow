<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Jadwal otomatis ────────────────────────────────────────────────────────
use Illuminate\Support\Facades\Schedule;

Schedule::command('hpp:sync')->dailyAt('08:30')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('notify:stock-check')->dailyAt('09:00')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('notify:stock-check')->dailyAt('16:00')->timezone('Asia/Jakarta')->withoutOverlapping();
// Stok+HPP+PO Jubelio (full katalog) -> tabel jubelio_inventory, dipakai Product Ads & Dashboard
Schedule::command('jubelio:sync-inventory')->everyThirtyMinutes()->withoutOverlapping();
// Rolling 3 hari ke tabel `orders` (pesanan masuk). Sync incremental (last_modified)
// -> hanya order baru/berubah yang di-fetch, jadi murah walau volume ~4rb order/hari.
// Schedule::command('orders:sync --days=3 --concurrency=12')->dailyAt('09:00')->timezone('Asia/Jakarta')->withoutOverlapping();
