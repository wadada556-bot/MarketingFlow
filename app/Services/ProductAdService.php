<?php

namespace App\Services;

use App\Models\ProductAd;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProductAdService
{
    public function getTabCounts(): array
    {
        $counts = ProductAd::toBase()
            ->selectRaw("COUNT(*) as semua")
            ->selectRaw("SUM(CASE WHEN testing_status = 'testing' THEN 1 ELSE 0 END) as perlu_dicek")
            ->selectRaw("SUM(CASE WHEN testing_status = 'success' THEN 1 ELSE 0 END) as berhasil")
            ->selectRaw("SUM(CASE WHEN testing_status = 'fail' THEN 1 ELSE 0 END) as gagal")
            ->first();

        return [
            'semua'       => (int) $counts->semua,
            'perlu_dicek' => (int) $counts->perlu_dicek,
            'berhasil'    => (int) $counts->berhasil,
            'gagal'       => (int) $counts->gagal,
        ];
    }

    public function getPaginatedAds(array $filters, string $currentStatus)
    {
        $query = ProductAd::select('id', 'product_id', 'status', 'testing_status', 'created_at', 'testing_completed_at')
            ->with([
                'product:id,parent_sku',
                'stores:id,name'
            ])
            ->filter($filters);

        if ($currentStatus === 'perlu_dicek') {
            $query->orderBy('testing_completed_at', 'asc');
        } else {
            $query->latest('id');
        }

        return $query->paginate(6);
    }

    public function createProductAd(array $data): ProductAd
    {
        return DB::transaction(function () use ($data) {
            $dates = $this->parseTestingDates($data['testing_dates'] ?? null);

            $productAd = ProductAd::create([
                'product_id'           => $data['product_id'],
                'status'               => $data['status'],
                'testing_status'       => $data['testing_status'],
                'testing_started_at'   => $dates['started_at'],
                'testing_completed_at' => $dates['completed_at'],
            ]);
            
            $productAd->stores()->attach($data['stores']);

            return $productAd;
        });
    }

    public function updateProductAd(ProductAd $productAd, array $data): bool
    {
        return DB::transaction(function () use ($productAd, $data) {
            $dates = $this->parseTestingDates($data['testing_dates'] ?? null);

            $productAd->update([
                'product_id'           => $data['product_id'],
                'status'               => $data['status'],
                'testing_status'       => $data['testing_status'],
                'testing_started_at'   => $dates['started_at'],
                'testing_completed_at' => $dates['completed_at'],
            ]);
            
            $productAd->stores()->sync($data['stores']);

            return true;
        });
    }

    private function parseTestingDates(?string $dateRange): array
    {
        if (empty($dateRange)) {
            return ['started_at' => null, 'completed_at' => null];
        }

        $dates = explode(' to ', $dateRange);
        
        return [
            'started_at'   => isset($dates[0]) ? Carbon::parse($dates[0]) : null,
            'completed_at' => isset($dates[1]) ? Carbon::parse($dates[1]) : (isset($dates[0]) ? Carbon::parse($dates[0]) : null),
        ];
    }

    public function bulkDeleteProductAds(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            // Hapus baris pivot eksplisit (setara detach), lalu hapus iklan.
            // product_ad_logs ikut terhapus via cascadeOnDelete pada FK.
            DB::table('product_ad_store')->whereIn('product_ad_id', $ids)->delete();
            ProductAd::whereIn('id', $ids)->delete();
        });
    }
}