<?php

namespace Database\Seeders;

use App\Models\ProductAd;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductAdSeeder extends Seeder
{
    public function run(): void
    {
        $product_ads = [
            [
                "product_id" => 1,
                "status" => "active"
            ],
            [
                "product_id" => 2,
                "status" => "stopped"
            ],
            [
                "product_id" => 3,
                "status" => "active"
            ],
            [
                "product_id" => 4,
                "status" => "active"
            ],
            [
                "product_id" => 5,
                "status" => "stopped"
            ],
        ];

        foreach ($product_ads as $product_ad) {
            ProductAd::create($product_ad);
        }
    }
}
