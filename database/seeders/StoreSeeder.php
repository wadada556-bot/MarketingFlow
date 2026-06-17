<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            "caramel aksesoris",
            "minzo store",
            "moonklaz",
            "nomide store",
            "topi kece",
            "topi keren",
            "yarra store"
        ];

        foreach ($stores as $store) {
            Store::create([
                "name" => $store
            ]);
        }
    }
}
