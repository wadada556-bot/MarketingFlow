# ─────────────────────────────────────────────────────────────────────────────
# deploy.ps1 — jalankan di SERVER PRODUKSI (Windows) untuk merilis versi terbaru.
#
# Langkah:
#   [1/5] Pengaman: pastikan working tree bersih (tak ada edit lokal belum commit)
#   [2/5] git pull
#   [3/5] composer install (prod, autoloader teroptimasi)
#   [4/5] php artisan migrate --force
#   [5/5] php artisan optimize (cache config + route + view + event)
#
# CATATAN OPcache: `artisan optimize` TIDAK mengaktifkan OPcache PHP.
# OPcache diatur di php.ini server (blok [opcache]) dan baru aktif setelah Apache
# di-restart. Aktifkan sekali di server:
#   opcache.enable=1
#   opcache.memory_consumption=256
#   opcache.max_accelerated_files=20000
#   opcache.validate_timestamps=0   ← di PRODUKSI set 0 (kode tak berubah tiap request)
# Setelah tiap deploy dengan validate_timestamps=0, WAJIB restart Apache agar kode
# baru terbaca.
# ─────────────────────────────────────────────────────────────────────────────

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

Write-Host '================================================' -ForegroundColor Cyan
Write-Host '  Marketing Flow - Deploy Script' -ForegroundColor Cyan
Write-Host '================================================' -ForegroundColor Cyan

# ── [1/5] Pengaman: working tree harus bersih ────────────────────────────────
Write-Host "`n[1/5] Cek working tree..." -ForegroundColor Cyan
$dirty = git status --porcelain
if ($dirty) {
    Write-Host '  PERINGATAN: Ada perubahan lokal yang belum di-commit:' -ForegroundColor Yellow
    Write-Host $dirty
    Write-Host '  Batalkan deploy. Cek `git status`, jangan edit file di server.' -ForegroundColor Yellow
    exit 1
}
Write-Host '  Bersih.' -ForegroundColor Green

# ── [2/5] Tarik kode terbaru ─────────────────────────────────────────────────
Write-Host "`n[2/5] git pull..." -ForegroundColor Cyan
git pull

# ── [3/5] Dependency produksi ────────────────────────────────────────────────
Write-Host "`n[3/5] composer install (prod)..." -ForegroundColor Cyan
composer install --no-dev --optimize-autoloader --no-interaction

# ── [4/5] Migrasi database ───────────────────────────────────────────────────
Write-Host "`n[4/5] migrasi database..." -ForegroundColor Cyan
php artisan migrate --force

# ── [5/5] Cache runtime ──────────────────────────────────────────────────────
Write-Host "`n[5/5] optimize (config/route/view/event cache)..." -ForegroundColor Cyan
php artisan optimize

Write-Host "`n==> Selesai. RESTART APACHE sekarang (OPcache validate_timestamps=0)." -ForegroundColor Green
