<?php

namespace App\Console\Commands;

use App\Exports\StoreProductsExport;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        // Command ini jalan 00:05 tapi mengarsip data yang DITUTUP 23:58 malam sebelumnya
        // (sync Jubelio harian). Jadi label folder = tanggal bisnis = kemarin, bukan hari jalan,
        // supaya nama folder konsisten dgn isi datanya. 00:05 terjadwal satu-satunya pemanggil.
        $snapshotDate = Carbon::now()->subDay()->format('Y-m-d');
        $dir          = self::BASE_DIR . '/' . $snapshotDate;

        $stores = Store::orderBy('name')->get();
        if ($stores->isEmpty()) {
            $this->warn('[Snapshot Export] Tidak ada toko terdaftar.');
            return self::SUCCESS;
        }

        // manifest.json menyimpan jumlah baris (produk×varian) per file, dihitung SEKARANG
        // (bukan saat render halaman) supaya halaman arsip tetap ringan tanpa membuka .xlsx.
        $manifest = [];
        foreach ($stores as $store) {
            $filename = 'products_' . Str::slug($store->name, '_') . '.xlsx';
            Excel::store(new StoreProductsExport($store->id), $dir . '/' . $filename, 'local');

            $manifest[$filename] = [
                'store' => $store->name,
                'rows'  => $this->countStoreRows($store->id),
            ];
        }

        Storage::disk('local')->put(
            $dir . '/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->info("[Snapshot Export] Selesai: {$stores->count()} toko disimpan ke {$dir}.");

        $this->pruneOldSnapshots();

        return self::SUCCESS;
    }

    /**
     * Jumlah baris yang akan tampil di .xlsx satu toko = 1 baris per (listing × varian).
     * Mirror join StoreProductsExport::collection() tapi hanya COUNT (tanpa buka Excel).
     */
    private function countStoreRows(int $storeId): int
    {
        return DB::table('tiktok_listings as tl')
            ->join('tiktok_listing_skus as ts', 'ts.listing_id', '=', 'tl.id')
            ->join('products as pr', 'pr.id', '=', 'ts.product_id')
            ->where('tl.store_id', $storeId)
            ->count();
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
