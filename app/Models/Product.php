<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'parent_sku',
        'item_group_id',
    ];

    protected $casts = [
        'item_group_id' => 'integer',
    ];

    public function getParentSkuAttribute($value): string
    {
        return strtoupper($value);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function productAds(): HasMany
    {
        return $this->hasMany(ProductAd::class);
    }

    public function productAdLogs(): HasManyThrough
    {
        return $this->hasManyThrough(ProductAdLog::class, ProductAd::class);
    }
}
