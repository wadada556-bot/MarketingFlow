<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Iklan sebuah produk di sebuah toko — entitas STABIL (satu baris per
 * (store_id, product_id)). Identitas (product_name) diisi importer; kolom
 * status/testing diisi aplikasi dan TIDAK PERNAH ditimpa importer.
 *
 * Performa mingguannya di tabel anak `ad_weekly_performances`; catatan di `ad_logs`.
 */
class Ad extends Model
{
    protected $fillable = [
        'store_id', 'product_id', 'product_name',
        'status', 'testing_status', 'testing_started_at', 'testing_completed_at',
    ];

    protected $casts = [
        'testing_started_at'   => 'datetime',
        'testing_completed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function weeklyPerformances(): HasMany
    {
        return $this->hasMany(AdWeeklyPerformance::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AdLog::class)->orderByDesc('action_date');
    }

    /**
     * Kolom SELALU diprefiks `ads.` — index query di-join dengan subquery `perf`
     * yang juga punya kolom senama; nama telanjang akan ambigu bagi MySQL.
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query->when($filters['store'] ?? null, function (Builder $q, mixed $stores) {
            $q->whereIn('ads.store_id', is_array($stores) ? $stores : [$stores]);
        });

        $query->when($filters['q'] ?? null, function (Builder $q, string $term) {
            $like = '%' . $term . '%';
            $q->where(function (Builder $s) use ($like) {
                $s->where('ads.product_name', 'like', $like)
                    ->orWhere('ads.product_id', 'like', $like)
                    // SKU induk & variasi. Induk selalu PREFIX dari sku_code
                    // variannya (lihat App\Support\SkuMatch), jadi LIKE %term%
                    // pada sku_code menutup keduanya sekaligus. Jalur:
                    // ads(product_id, store_id) -> tiktok_listings -> tiktok_listing_skus.
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->selectRaw('1')
                            ->from('tiktok_listings as tl')
                            ->join('tiktok_listing_skus as tls', 'tls.listing_id', '=', 'tl.id')
                            ->whereColumn('tl.product_id', 'ads.product_id')
                            ->whereColumn('tl.store_id', 'ads.store_id')
                            ->where('tls.sku_code', 'like', $like);
                    });
            });
        });

        $query->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('ads.status', $s));
    }

    /**
     * Tab filter, kosakata sama dengan menu Product Ads lama. `testing_status`
     * hanya punya 2 nilai tersimpan (success/fail) -- NULL berarti "masa
     * testing" (belum ditandai), jadi "Perlu Dicek" = IS NULL, bukan '='.
     */
    public function scopeTestingTab(Builder $query, ?string $tab): void
    {
        match ($tab) {
            'perlu_dicek' => $query->whereNull('ads.testing_status'),
            'berhasil'    => $query->where('ads.testing_status', 'success'),
            'gagal'       => $query->where('ads.testing_status', 'fail'),
            default       => null, // 'semua' -- tanpa filter
        };
    }

    public function getTestingBadgeAttribute(): array
    {
        return match ($this->testing_status) {
            'success' => ['color' => 'success',   'label' => 'Berhasil'],
            'fail'    => ['color' => 'danger',    'label' => 'Gagal'],
            default   => ['color' => 'secondary', 'label' => 'Testing'], // NULL = masa testing
        };
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'active'  => ['color' => 'success',   'label' => 'Active'],
            'stopped' => ['color' => 'secondary', 'label' => 'Stopped'],
            default   => ['color' => 'dark',      'label' => '—'],
        };
    }
}
