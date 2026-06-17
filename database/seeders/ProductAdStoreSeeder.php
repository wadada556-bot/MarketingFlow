<?php

namespace Database\Seeders;

use App\Models\ProductAdStore;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductAdStoreSeeder extends Seeder
{
    public function run(): void
    {
        $product_ad_stores = [
            [
                "product_ad_id" => 1,
                "store_id" => 6
            ],
            [
                "product_ad_id" => 2,
                "store_id" => 6
            ],
            [
                "product_ad_id" => 3,
                "store_id" => 6
            ],
            [
                "product_ad_id" => 4,
                "store_id" => 1
            ],
            [
                "product_ad_id" => 5,
                "store_id" => 1
            ],
        ];

        foreach ($product_ad_stores as $product_ad_store) {
            ProductAdStore::create($product_ad_store);
        }
    }
}
