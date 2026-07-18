<?php

namespace App\Console\Commands;

use App\Models\JubelioInventory;
use App\Services\JubelioApiService;
use Illuminate\Console\Command;

class SyncJubelioInventory extends Command
{
    protected $signature   = 'jubelio:sync-inventory';
    protected $description = 'Sync stok, HPP, dan PO inbound dari Jubelio ke tabel lokal jubelio_inventory';

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

        // Kolom hpp TIDAK di-update oleh sync 30-menitan ini — hpp dikelola oleh
        // hpp:sync (harian 08:30, sekaligus deteksi perubahan utk email) dan
        // edit manual di menu Products. Nilai hpp di sini hanya dipakai saat
        // INSERT SKU baru (belum pernah ada barisnya).
        $now  = now();
        $rows = [];
        foreach ($inventory as $sku => $data) {
            $hpp = (int) $data['hpp'];

            $rows[] = [
                'sku_code'        => $sku,
                'parent_sku'      => $data['parent_sku'],
                'item_group_id'   => $data['item_group_id'] ?: null,
                'variation_label' => $data['variation_label'],
                'stok'            => $data['stok'],
                'hpp'             => $hpp,
                'po_qty'          => $po[$sku] ?? 0,
                'synced_at'       => $now->toDateTimeString(),
                'created_at'      => $now->toDateTimeString(),
                'updated_at'      => $now->toDateTimeString(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            JubelioInventory::upsert(
                $chunk,
                ['sku_code'],
                ['parent_sku', 'item_group_id', 'variation_label', 'stok', 'po_qty', 'synced_at', 'updated_at']
            );
        }

        $this->info('[Jubelio Inventory Sync] Selesai: ' . count($rows) . ' SKU di-sync.');

        return self::SUCCESS;
    }
}
