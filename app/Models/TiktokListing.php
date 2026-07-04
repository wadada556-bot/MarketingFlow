<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiktokListing extends Model
{
    protected $fillable = ['store_id', 'product_id', 'product_name', 'category', 'synced_at'];

    protected $casts = [
        'store_id'   => 'integer',
        'product_id' => 'integer',
        'synced_at'  => 'datetime',
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
