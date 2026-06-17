<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                "category_id" => 2,
                "parent_sku" => "PTAH",
            ],
            [
                "category_id" => 1,
                "parent_sku" => "TPJ"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "SPAD"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "GL-FNA"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "BWAA"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "BWAW"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "BSCT"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "AKS28"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "BSCR"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "ANT160"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "ANT120"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "AK313"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "AK291"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "ZQ"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "ZS"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "BWAC"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "ANT31"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "BWAG"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "ANT146"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "TRO"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "ANT96"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "TRC"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "BWAB"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "ANT118"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "ANT88"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "ANT161"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "AK419"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "BULUMATA LECCA"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "KCM22"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "ANT102"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "ANT144"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "GLG17"
            ],
            [
                "category_id" => 1,
                "parent_sku" => "CNC26"
            ],
        ];


        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
