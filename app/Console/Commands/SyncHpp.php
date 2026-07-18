<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Notifications\HppChangedNotification;
use App\Services\JubelioApiService;
use Illuminate\Console\Command;

class SyncHpp extends Command
{
    protected $signature   = 'hpp:sync';
    protected $description = 'Sync HPP (harga pokok penjualan) dari Jubelio ke products + email perubahan';

    /**
     * Satu-satunya sumber HPP di DB = products.hpp (tabel sku_hpp yang
     * dulu jadi baseline terpisah sudah di-drop — duplikat antar tabel).
     * Deteksi perubahan: bandingkan HPP segar dari Jubelio dengan nilai
     * tersimpan; command inilah SATU-SATUNYA penulis hpp dari sync (30-menitan
     * jubelio:sync-inventory sengaja tidak meng-update hpp lagi), jadi nilai
     * tersimpan = baseline sejak run kemarin.
     *
     * HPP kiriman 0 = "tidak ada HPP di Jubelio" → di-skip, supaya isian manual
     * (produk bundling, via menu Products) tidak pernah tertimpa.
     */
    public function handle(JubelioApiService $jubelio): int
    {
        $this->info('[HPP Sync] Mengambil data HPP dari Jubelio...');

        try {
            $fresh = $jubelio->fetchAllHpp();
        } catch (\Throwable $e) {
            $this->error("[HPP Sync] Gagal: {$e->getMessage()}");
            return self::FAILURE;
        }

        if (empty($fresh)) {
            $this->warn('[HPP Sync] Tidak ada data yang diterima dari Jubelio.');
            return self::SUCCESS;
        }

        $this->info('[HPP Sync] Diterima ' . count($fresh) . ' SKU. Cek perubahan...');

        $existing = Product::pluck('hpp', 'sku_code');

        $now     = now();
        $changes = [];
        foreach ($fresh as $sku => $newHpp) {
            if ($newHpp <= 0) {
                continue; // 0 = tidak ada HPP; jangan timpa nilai manual
            }
            $oldHpp = $existing->get($sku);
            if ($oldHpp === null) {
                continue; // SKU belum ada di products (menunggu sync inventory)
            }
            if ((int) $oldHpp !== $newHpp) {
                $changes[] = ['sku' => $sku, 'old' => (int) $oldHpp, 'new' => $newHpp];
                Product::where('sku_code', $sku)
                    ->update(['hpp' => $newHpp, 'updated_at' => $now]);
            }
        }

        $this->info('[HPP Sync] Selesai: ' . count($fresh) . ' SKU dicek, ' . count($changes) . ' berubah.');

        User::first()->notify(new HppChangedNotification($changes, count($fresh)));
        $this->info('[HPP Sync] Notifikasi email terkirim.');

        return self::SUCCESS;
    }
}
