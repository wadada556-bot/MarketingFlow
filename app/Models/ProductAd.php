<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class ProductAd extends Model
{
    protected $fillable = [
        'product_id',
        'status',
        'testing_status',
        'testing_started_at',
        'testing_completed_at'
    ];
    
    protected $hidden = ['pivot'];
    
    protected $casts = [
        'testing_started_at'   => 'datetime',
        'testing_completed_at' => 'datetime',
    ];

    protected function statusTesting(): Attribute
    {
        return Attribute::make(
            get: function () {
                $ui = match ($this->testing_status) {
                    'testing' => ['color' => 'secondary', 'label' => 'Testing'],
                    'success' => ['color' => 'success',   'label' => 'Success'],
                    'fail'    => ['color' => 'danger',    'label' => 'Fail'],
                    default   => ['color' => 'dark',      'label' => 'No Status'], 
                };

                return sprintf(
                    '<span class="badge rounded-pill bg-%1$s bg-opacity-10 text-%1$s border border-%1$s">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> %2$s
                    </span>',
                    $ui['color'],
                    $ui['label']
                );
            }
        );
    }

    protected function statusBadge(): Attribute
    {
        return Attribute::make(
            get: function () {
                $ui = match ($this->status) {
                    'active'    => ['color' => 'success',   'label' => 'Active'],
                    'completed' => ['color' => 'primary',   'label' => 'Completed'],
                    'stopped'   => ['color' => 'secondary', 'label' => 'Stopped'],
                    default     => ['color' => 'dark',      'label' => 'Unknown'], 
                };

                return sprintf(
                    '<span class="badge rounded-pill bg-%1$s bg-opacity-10 text-%1$s border border-%1$s">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> %2$s
                    </span>',
                    $ui['color'],
                    $ui['label']
                );
            }
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): HasOneThrough
    {
        return $this->hasOneThrough(Category::class, Product::class);
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'product_ad_store')
            ->using(ProductAdStore::class)
            ->withTimestamps();
    }

    public function productAdLogs(): HasMany
    {
        return $this->hasMany(ProductAdLog::class);
    }

    public function scopeFilter(Builder $query, array $filters): void
    {
        $query->when($filters['product'] ?? null, fn(Builder $q, string $product) => 
            $q->where('product_id', $product)
        );

        $query->when($filters['status'] ?? null, fn(Builder $q, $status) => 
            $q->where('status', $status)
        );

        $query->when($filters['category'] ?? null, function (Builder $q, $category) {
            $q->whereHas('product', fn($sub) => $sub->where('category_id', $category));
        });

        $query->when($filters['store'] ?? null, function (Builder $q, mixed $stores) {
            $storeIds = is_array($stores) ? $stores : [$stores];
            $q->whereHas('stores', fn($sub) => $sub->whereIn('store_id', $storeIds));
        });

        $query->when($filters['testing_status'] ?? null, function (Builder $q, $testingStatus) {
            if ($testingStatus === 'semua') {
                return; 
            }

            $dbStatus = match ($testingStatus) {
                'perlu_dicek' => 'testing',
                'berhasil'    => 'success',
                'gagal'       => 'fail',
                default       => null,
            };

            if ($dbStatus) {
                $q->where('testing_status', $dbStatus);
            }
        });
    }
}