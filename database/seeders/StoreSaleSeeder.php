<?php

namespace Database\Seeders;

use App\Models\DailyStoreStat;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class StoreSaleSeeder extends Seeder
{
    public function run(): void
    {
        DailyStoreStat::truncate();

        $today = Carbon::today();

        /*
         * store_id → profil harian (rata-rata hari kerja biasa)
         *   1 = caramel aksesoris
         *   2 = minzo store
         *   3 = moonklaz
         *   4 = nomide store
         *   5 = topi kece
         *   6 = topi keren
         *   7 = yarra store
         */
        $profiles = [
            6 => ['gmv' => 2_200_000, 'pesanan' => 62],
            1 => ['gmv' => 1_820_000, 'pesanan' => 50],
            2 => ['gmv' => 1_540_000, 'pesanan' => 45],
            7 => ['gmv' => 1_150_000, 'pesanan' => 32],
            4 => ['gmv' =>   890_000, 'pesanan' => 24],
            5 => ['gmv' =>   620_000, 'pesanan' => 17],
            3 => ['gmv' =>   720_000, 'pesanan' => 20],
        ];

        // Pengali per hari dalam seminggu (0=Min, 1=Sen, …, 6=Sab)
        $dayMult = [1.15, 0.85, 0.90, 0.95, 0.90, 1.10, 1.45];

        $records = [];

        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            $date    = $today->copy()->subDays($daysAgo);
            $dateStr = $date->format('Y-m-d');
            $dow     = $date->dayOfWeek;
            $dm      = $dayMult[$dow];

            // Tren naik selama 30 hari: hari paling lama ×1.0, hari ini ×1.15
            $trend = 1.0 + (29 - $daysAgo) / 29 * 0.15;

            foreach ($profiles as $storeId => $p) {
                $noise = 0.88 + (($storeId * 17 + $daysAgo * 31) % 25) / 100;

                $records[] = [
                    'store_id'   => $storeId,
                    'tanggal'    => $dateStr,
                    'gmv'        => (int) round($p['gmv'] * $dm * $trend * $noise / 1_000) * 1_000,
                    'pesanan'    => max(1, (int) round($p['pesanan'] * $dm * $trend * $noise)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($records, 100) as $chunk) {
            DailyStoreStat::insert($chunk);
        }
    }
}
