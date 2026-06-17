<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

use function Symfony\Component\Clock\now;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = ["dikembangkan", "produk baru"];
        foreach ($categories as $category) {
            // DB::table('categories')->insert([
            //     "name" => $category,
            //     "created_at" => now(),
            //     "updated_at" => now()
            // ]);

            Category::create([
                "name" => $category
            ]);
        }
    }
}
