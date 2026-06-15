<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Store;
use App\Models\Product;
use App\Models\MarketingCampaign;
use App\Models\CampaignHistory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Kategori
        $catBaru = Category::create(['name' => 'Produk Baru']);
        $catKembang = Category::create(['name' => 'Dikembangkan']);

        // 2. Buat Toko
        $stores = [
            'Topi Keren' => Store::create(['name' => 'Topi Keren']),
            'Minzo' => Store::create(['name' => 'Minzo']),
            'Yarra' => Store::create(['name' => 'Yarra']),
            'Caramel' => Store::create(['name' => 'Caramel']),
        ];

        // 3. Data 10 Kampanye (Menyesuaikan dengan tipe ENUM string)
        $data = [
            // --- ADS MARKETING ---
            [
                'sku' => 'PTAH', 'cat' => $catBaru->id, 'type' => 'ads marketing', 'status' => 'active', 'start' => '2026-01-17',
                'toko' => ['Topi Keren'],
                'histories' => [
                    ['date' => '2026-01-17', 'desc' => 'Menawarkan Produk Ke Affiliator'],
                    ['date' => '2026-01-31', 'desc' => 'Memulai Iklan Dengan ROI Awal 18'],
                    ['date' => '2026-02-06', 'desc' => 'Menurunkan ROI dari 18 ke 14'],
                ]
            ],
            [
                'sku' => 'TPJ', 'cat' => $catKembang->id, 'type' => 'ads marketing', 'status' => 'stopped', 'start' => '2026-02-02',
                'toko' => ['Topi Keren'],
                'histories' => [
                    ['date' => '2026-02-02', 'desc' => 'Biaya Iklan di naikan dari 100rb ke 300rb'],
                    ['date' => '2026-04-08', 'desc' => 'Margin Final Tidak Sesuai Target 10% dan minus 3% - Tidak memungkinkan untuk naik harga, iklan harus di takedown'],
                ]
            ],
            [
                'sku' => 'AK419', 'cat' => $catKembang->id, 'type' => 'ads marketing', 'status' => 'stopped', 'start' => '2026-03-10',
                'toko' => ['Minzo', 'Yarra'],
                'histories' => [
                    ['date' => '2026-03-10', 'desc' => 'Memulai iklan di dua toko dengan ROI awal 18 di karenakan materi iklan sudah tersedia'],
                    ['date' => '2026-03-16', 'desc' => 'Stok Habis - Iklan di takedown'],
                ]
            ],
            [
                'sku' => 'KCM22', 'cat' => $catKembang->id, 'type' => 'ads marketing', 'status' => 'stopped', 'start' => '2026-03-06',
                'toko' => ['Caramel'],
                'histories' => [
                    ['date' => '2026-03-06', 'desc' => 'Memulai iklan dengan ROI awal 18 di karenakan materi iklan sudah tersedia'],
                    ['date' => '2026-03-12', 'desc' => 'Budget Pengeluaran dibawah 100rb, iklan kurang maksimal ROI Perlu di turunkan dari 18 ke 15'],
                    ['date' => '2026-03-19', 'desc' => 'Iklan Dihentikan karena sudah habis momennya'],
                ]
            ],
            [
                'sku' => 'ANT144', 'cat' => $catKembang->id, 'type' => 'ads marketing', 'status' => 'stopped', 'start' => '2026-03-12',
                'toko' => ['Minzo'],
                'histories' => [
                    ['date' => '2026-03-12', 'desc' => 'Memulai iklan di dua toko dengan ROI awal 15 di karenakan materi iklan sudah tersedia'],
                    ['date' => '2026-03-19', 'desc' => 'Iklan Dihentikan karena sudah habis momennya'],
                ]
            ],
            // --- BS STRATEGY ---
            [
                'sku' => 'BWAA', 'cat' => $catKembang->id, 'type' => 'bs strategy', 'status' => 'stopped', 'start' => '2026-02-25',
                'toko' => ['Caramel'],
                'histories' => [
                    ['date' => '2026-02-25', 'desc' => 'Stok Restock Lokal - Harga Di Ubah dari 16.500 ke 19.900'],
                    ['date' => '2026-03-02', 'desc' => 'Restock Lokal dan Mulai Mengaktifkan PRE ORDER'],
                    ['date' => '2026-03-16', 'desc' => 'Stok Habis'],
                ]
            ],
            [
                'sku' => 'AK394', 'cat' => $catKembang->id, 'type' => 'bs strategy', 'status' => 'active', 'start' => '2026-02-18',
                'toko' => ['Minzo', 'Yarra'],
                'histories' => [
                    ['date' => '2026-02-18', 'desc' => 'Penjualan Mulai Meningkat - Harga Dinaikin dari 6.900 ke 7.500'],
                    ['date' => '2026-02-25', 'desc' => 'OPEN PRE-ORDER 17.700 pcs'],
                    ['date' => '2026-03-02', 'desc' => 'AK394 Produk kena Pelanggaran SNAD - Di alihkan ke Toko Yarra'],
                ]
            ],
            [
                'sku' => 'ANT120', 'cat' => $catKembang->id, 'type' => 'bs strategy', 'status' => 'active', 'start' => '2026-02-24',
                'toko' => ['Minzo', 'Yarra'],
                'histories' => [
                    ['date' => '2026-02-24', 'desc' => 'Karena Penjualan Kurang - Harga Produk di turunkan dari 12.900 ke 9.900'],
                    ['date' => '2026-03-14', 'desc' => 'Penjualan Meningkat Dan Margin Belum Tercapai - Harga Produk Dinaikan dari 9.900 ke 10.900'],
                ]
            ],
            [
                'sku' => 'AK291', 'cat' => $catKembang->id, 'type' => 'bs strategy', 'status' => 'stopped', 'start' => '2026-02-27',
                'toko' => ['Caramel'],
                'histories' => [
                    ['date' => '2026-02-27', 'desc' => 'Penjualan Meningkat - Stok menipis dan datang lama dan gak bisa lokal - Harga dinaikan dari 24.900 ke 27.900'],
                    ['date' => '2026-03-04', 'desc' => 'Stok Habis'],
                ]
            ],
            [
                'sku' => 'BWAG', 'cat' => $catKembang->id, 'type' => 'bs strategy', 'status' => 'active', 'start' => '2026-02-27',
                'toko' => ['Yarra'],
                'histories' => [
                    ['date' => '2026-02-27', 'desc' => 'Karena mau coba iklan dan Margin Final kurang dari 10% - Harga Dinaikan dari 13.900 ke 14.900'],
                ]
            ],
        ];

        // 4. Eksekusi Insert Data
        foreach ($data as $item) {
            $product = Product::create([
                'category_id' => $item['cat'],
                'parent_sku' => $item['sku'],
                'variant_sku' => $item['var'],
            ]);

            $campaign = MarketingCampaign::create([
                'product_id' => $product->id,
                
                // --- PERUBAHAN DI SINI ---
                // Langsung gunakan string dari array karena tabel menggunakan ENUM string
                'type' => $item['type'], 
                'start_date' => $item['start'],
                'status' => $item['status'], 
            ]);

            // Attach Toko (Pivot)
            $storeIds = array_map(fn($name) => $stores[$name]->id, $item['toko']);
            $campaign->stores()->attach($storeIds);

            // Insert Histories
            foreach ($item['histories'] as $index => $history) {
                CampaignHistory::create([
                    'marketing_campaign_id' => $campaign->id,
                    'action_date' => $history['date'],
                    'description' => $history['desc'],
                    'step_number' => $index + 1,
                ]);
            }
        }
    }
}