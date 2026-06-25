<?php

namespace App\Console\Commands;

use App\Models\SkuHpp;
use App\Models\User;
use App\Notifications\HppChangedNotification;
use App\Services\JubelioApiService;
use Illuminate\Console\Command;

class SyncHpp extends Command
{
    protected $signature   = 'hpp:sync';
    protected $description = 'Sync HPP (harga pokok penjualan) dari Jubelio ke database lokal';

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

        // Ambil nilai lama untuk deteksi perubahan signifikan
        $existing = SkuHpp::whereIn('sku_code', array_keys($fresh))
            ->pluck('hpp', 'sku_code');

        $changes = [];
        foreach ($fresh as $sku => $newHpp) {
            $oldHpp = $existing->get($sku);
            if ($oldHpp !== null && $newHpp !== (int) $oldHpp) {
                $changes[] = ['sku' => $sku, 'old' => (int) $oldHpp, 'new' => $newHpp];
            }
        }

        // Upsert ke DB dalam batch 500 agar tidak overload query
        $now  = now();
        $rows = [];
        foreach ($fresh as $sku => $hpp) {
            $rows[] = [
                'sku_code'   => $sku,
                'hpp'        => $hpp,
                'synced_at'  => $now->toDateTimeString(),
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            SkuHpp::upsert($chunk, ['sku_code'], ['hpp', 'synced_at', 'updated_at']);
        }

        $this->info('[HPP Sync] Selesai: ' . count($fresh) . ' SKU di-sync, ' . count($changes) . ' berubah.');

        \Illuminate\Support\Facades\Notification::route('mail', config('mail.to'))
            ->notify(new HppChangedNotification($changes, count($fresh)));
        $this->info('[HPP Sync] Notifikasi email terkirim.');

        return self::SUCCESS;
    }
}
