# ─────────────────────────────────────────────────────────────────────────────
# deploy.ps1 — jalankan di SERVER PRODUKSI (Windows) setelah `git pull`.
#
# Tujuan: menerapkan optimasi runtime Laravel supaya web cepat.
#   - composer install tanpa dev + autoloader teroptimasi (class map)
#   - migrasi DB
#   - `artisan optimize`  → cache config + route + view + event (1 perintah)
#
# CATATAN OPcache: `artisan optimize` TIDAK mengaktifkan OPcache PHP.
# OPcache diatur di php.ini server (lihat blok [opcache]) dan baru aktif
# setelah Apache di-restart. Aktifkan sekali di server:
#   opcache.enable=1
#   opcache.memory_consumption=256
#   opcache.max_accelerated_files=20000
#   opcache.validate_timestamps=0   ← di PRODUKSI set 0 (kode tak berubah tiap request)
# lalu restart Apache. Setelah tiap deploy dengan validate_timestamps=0,
# restart Apache agar kode baru terbaca.
# ─────────────────────────────────────────────────────────────────────────────

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

Write-Host '==> git pull' -ForegroundColor Cyan
git pull

Write-Host '==> composer install (prod)' -ForegroundColor Cyan
composer install --no-dev --optimize-autoloader --no-interaction

Write-Host '==> migrasi database' -ForegroundColor Cyan
php artisan migrate --force

Write-Host '==> optimize (config/route/view/event cache)' -ForegroundColor Cyan
php artisan optimize

Write-Host '==> selesai. Jika OPcache validate_timestamps=0, restart Apache sekarang.' -ForegroundColor Green
