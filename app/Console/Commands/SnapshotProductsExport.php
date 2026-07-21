<?php

namespace App\Console\Commands;

use App\Exports\StoreProductsExport;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class SnapshotProductsExport extends Command
{
    protected $signature   = 'products:snapshot-export';
    protected $description = 'Simpan snapshot Excel produk per toko ke storage (arsip harian, rolling 7 hari)';

    private const RETENTION_DAYS = 7;
    private const BASE_DIR       = 'product-snapshots';

    public function handle(): int
    {
        $today = Carbon::now()->format('Y-m-d');
        $dir   = self::BASE_DIR . '/' . $today;

        $stores = Store::orderBy('name')->get();
        if ($stores->isEmpty()) {
            $this->warn('[Snapshot Export] Tidak ada toko terdaftar.');
            return self::SUCCESS;
        }

        foreach ($stores as $store) {
            $filename = 'products_' . Str::slug($store->name, '_') . '.xlsx';
            Excel::store(new StoreProductsExport($store->id), $dir . '/' . $filename, 'local');
        }

        $this->info("[Snapshot Export] Selesai: {$stores->count()} toko disimpan ke {$dir}.");

        $this->pruneOldSnapshots();

        return self::SUCCESS;
    }

    /**
     * Hapus folder tanggal yang lebih tua dari batas retensi. Nama folder = tanggal
     * (Y-m-d), jadi parsing langsung dari nama, bukan mtime filesystem.
     */
    private function pruneOldSnapshots(): void
    {
        $cutoff  = Carbon::now()->subDays(self::RETENTION_DAYS)->startOfDay();
        $removed = 0;

        foreach (Storage::disk('local')->directories(self::BASE_DIR) as $path) {
            $date = basename($path);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            if (Carbon::createFromFormat('Y-m-d', $date)->startOfDay()->lt($cutoff)) {
                Storage::disk('local')->deleteDirectory($path);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->info("[Snapshot Export] {$removed} folder arsip lama dihapus (>" . self::RETENTION_DAYS . ' hari).');
        }
    }
}
