<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Notifications\HppChangedNotification;
use App\Services\JubelioApiService;
use Illuminate\Console\Command;

class SyncJubelioInventory extends Command
{
    protected $signature   = 'jubelio:sync-inventory';
    protected $description = 'Sync stok, HPP (+ deteksi perubahan & email), dan PO inbound dari Jubelio ke tabel lokal products';

    /**
     * Satu-satunya sync yang menulis products dari Jubelio. Stok/PO/label di-upsert
     * tiap run; HPP di-update terjaga (deteksi perubahan + email HppChangedNotification).
     *
     * Endpoint inventory Jubelio mengembalikan stok DAN HPP dalam satu response, jadi
     * keduanya diambil sekali lewat fetchAllInventory() — tidak ada fetch HPP terpisah.
     *
     * HPP=0 dari Jubelio = "tidak ada HPP" → di-skip, supaya isian manual (produk
     * bundling, via menu Products) tidak pernah tertimpa. Kolom hpp sengaja TIDAK
     * masuk update-columns bulk upsert; hanya terisi saat INSERT SKU baru, lalu
     * di-update terjaga di loop deteksi perubahan. Karena command ini satu-satunya
     * penulis hpp dari sync, nilai tersimpan = baseline sejak run kemarin.
     */
    public function handle(JubelioApiService $jubelio): int
    {
        $this->info('[Jubelio Inventory Sync] Mengambil stok + HPP (full katalog)...');

        try {
            $inventory = $jubelio->fetchAllInventory();
        } catch (\Throwable $e) {
            $this->error("[Jubelio Inventory Sync] Gagal ambil inventory: {$e->getMessage()}");
            return self::FAILURE;
        }

        if (empty($inventory)) {
            $this->warn('[Jubelio Inventory Sync] Tidak ada data inventory yang diterima.');
            return self::SUCCESS;
        }

        $this->info('[Jubelio Inventory Sync] Diterima ' . count($inventory) . ' SKU. Mengambil PO inbound...');

        try {
            $po = $jubelio->fetchAllPo();
        } catch (\Throwable $e) {
            $this->warn("[Jubelio Inventory Sync] Gagal ambil PO ({$e->getMessage()}), lanjut tanpa data PO.");
            $po = [];
        }

        // Baca baseline HPP SEBELUM upsert (dipakai deteksi perubahan di bawah).
        $existing = Product::pluck('hpp', 'sku_code');

        $now  = now();
        $rows = [];
        foreach ($inventory as $sku => $data) {
            $rows[] = [
                'sku_code'        => $sku,
                'parent_sku'      => $data['parent_sku'],
                'variation_label' => $data['variation_label'],
                'stok'            => $data['stok'],
                'hpp'             => (int) $data['hpp'], // hanya dipakai saat INSERT SKU baru
                'po_qty'          => $po[$sku] ?? 0,
                'synced_at'       => $now->toDateTimeString(),
                'created_at'      => $now->toDateTimeString(),
                'updated_at'      => $now->toDateTimeString(),
            ];
        }

        // hpp TIDAK di update-columns → nilai manual/existing tidak tertimpa di sini;
        // perubahan HPP ditangani di loop terjaga setelah upsert.
        foreach (array_chunk($rows, 500) as $chunk) {
            Product::upsert(
                $chunk,
                ['sku_code'],
                ['parent_sku', 'variation_label', 'stok', 'po_qty', 'synced_at', 'updated_at']
            );
        }

        // Deteksi perubahan HPP + update terjaga, memakai HPP yang sudah ada di
        // hasil fetchAllInventory() (tidak ada request tambahan ke Jubelio).
        $changes = [];
        foreach ($inventory as $sku => $data) {
            $newHpp = (int) $data['hpp'];
            if ($newHpp <= 0) {
                continue; // 0 = tidak ada HPP; jangan timpa nilai manual
            }
            $oldHpp = $existing->get($sku);
            if ($oldHpp === null) {
                continue; // SKU baru — hpp-nya sudah terisi lewat INSERT di atas
            }
            if ((int) $oldHpp !== $newHpp) {
                $changes[] = ['sku' => $sku, 'old' => (int) $oldHpp, 'new' => $newHpp];
                Product::where('sku_code', $sku)
                    ->update(['hpp' => $newHpp, 'updated_at' => $now]);
            }
        }

        $this->info('[Jubelio Inventory Sync] Selesai: ' . count($rows) . ' SKU di-sync, ' . count($changes) . ' HPP berubah.');

        User::first()?->notify(new HppChangedNotification($changes, count($inventory)));
        $this->info('[Jubelio Inventory Sync] Notifikasi email terkirim.');

        return self::SUCCESS;
    }
}
