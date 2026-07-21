<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Jadwal otomatis ────────────────────────────────────────────────────────
use Illuminate\Support\Facades\Schedule;

// Stok + HPP (deteksi perubahan + email) + PO Jubelio (full katalog) -> tabel products.
// Satu sync menutup data harian sedekat mungkin dengan pergantian hari.
Schedule::command('jubelio:sync-inventory')->dailyAt('23:58')->timezone('Asia/Jakarta')->withoutOverlapping();
// Arsip Excel produk per toko, harian (termasuk weekend) -> storage/app/product-snapshots, rolling 7 hari.
// Membaca DB yang sudah final (products hanya berubah sekali/hari), jadi aman setelah tengah malam.
Schedule::command('products:snapshot-export')->dailyAt('00:05')->timezone('Asia/Jakarta')->withoutOverlapping();
