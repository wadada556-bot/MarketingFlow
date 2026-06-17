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
        $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
            StoreSeeder::class,
            ProductAdSeeder::class,
            ProductAdStoreSeeder::class,
            ProductAdLogSeeder::class,
            StoreSaleSeeder::class,
        ]);
    }
}