<?php

namespace Database\Seeders;

use App\Models\ProductAdLog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductAdLogSeeder extends Seeder
{
    public function run(): void
    {
        $product_ad_logs = [
            [
                "product_ad_id" => 1,
                "action_date" => "2026-01-17",
                "description" => "Menawarkan Produk Ke Affiliator"
            ],
            [
                "product_ad_id" => 1,
                "action_date" => "2026-01-31",
                "description" => "Memulai Iklan Dengan ROI Awal 18"
            ],
            [
                "product_ad_id" => 1,
                "action_date" => "2026-02-06",
                "description" => "Menurunkan ROI dari 18 ke 14"
            ],
            [
                "product_ad_id" => 1,
                "action_date" => "2026-02-13",
                "description" => "Materi Banyak, Tapi blm naik - ROI di ubah dari 14 ke 10"
            ],
            [
                "product_ad_id" => 1,
                "action_date" => "2026-04-08",
                "description" => "Margin Final tidak sesuai target 10%, ROI diubah dari 10 ke 12,5"
            ],
            [
                "product_ad_id" => 2,
                "action_date" => "2026-02-02",
                "description" => "Biaya Iklan di naikan dari 100rb ke 300rb"
            ],
            [
                "product_ad_id" => 2,
                "action_date" => "2026-02-04",
                "description" => "Menawarkan produk ke affiliator"
            ],
            [
                "product_ad_id" => 2,
                "action_date" => "2026-04-08",
                "description" => "Margin Final Tidak Sesuai Target 10% dan minus 3% - Tidak memungkinkan untuk naik harga, iklan harus di takedown"
            ],
            [
                "product_ad_id" => 3,
                "action_date" => "2026-02-03",
                "description" => "Materi Iklan sudah 0 - Tim Affiliator menawarkan kerjasama kembali"
            ],
            [
                "product_ad_id" => 3,
                "action_date" => "2026-04-06",
                "description" => "Budget Mentok - Budget Naik dari 300r ke 500rb"
            ],
            [
                "product_ad_id" => 3,
                "action_date" => "2026-04-08",
                "description" => "Margin Final tidak sesuai target 10%, ROI diubah dari 7,1 ke 12"
            ],
            [
                "product_ad_id" => 4,
                "action_date" => "2026-02-05",
                "description" => "Memulai iklan dengan ROI awal 18 di karenakan materi iklan sudah tersedia"
            ],
            [
                "product_ad_id" => 4,
                "action_date" => "2026-02-18",
                "description" => "Penjualan Turun - ROI turun dari 18 ke 13"
            ],
            [
                "product_ad_id" => 5,
                "action_date" => "2026-01-17",
                "description" => "Memulai Iklan dengan ROI awal 15 di karenakan materi iklan sudah tersedia"
            ],
            [
                "product_ad_id" => 5,
                "action_date" => "2026-01-18",
                "description" => "Dikarenakan ada potensi produk naik - Tim affiliator memulai menawarkan produk ke affiliator"
            ],
            [
                "product_ad_id" => 5,
                "action_date" => "2026-03-16",
                "description" => "Stok Habis - Iklan di Takedown"
            ],
        ];

        foreach ($product_ad_logs as $product_ad_log) {
            ProductAdLog::create($product_ad_log);
        }
    }
}
