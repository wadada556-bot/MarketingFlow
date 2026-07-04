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
            "PTAH", "TPJ", "SPAD", "GL-FNA", "BWAA", "BWAW", "BSCT", "AKS28",
            "BSCR", "ANT160", "ANT120", "AK313", "AK291", "ZQ", "ZS", "BWAC",
            "ANT31", "BWAG", "ANT146", "TRO", "ANT96", "TRC", "BWAB", "ANT118",
            "ANT88", "ANT161", "AK419", "BULUMATA LECCA", "KCM22", "ANT102",
            "ANT144", "GLG17", "CNC26",
        ];

        foreach ($products as $parentSku) {
            Product::create(['parent_sku' => $parentSku]);
        }
    }
}
