<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiktokListing extends Model
{
    protected $fillable = ['store_id', 'tiktok_product_id', 'model_id'];

    protected $casts = [
        'store_id'          => 'integer',
        'tiktok_product_id' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function skus(): HasMany
    {
        return $this->hasMany(TiktokListingSku::class, 'listing_id');
    }
}
