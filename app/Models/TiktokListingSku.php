<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokListingSku extends Model
{
    protected $fillable = [
        'store_id', 'listing_id', 'sku_id', 'sku_code', 'match_sku', 'variation_value', 'synced_at',
    ];

    protected $casts = [
        'store_id'   => 'integer',
        'listing_id' => 'integer',
        'sku_id'     => 'integer',
        'synced_at'  => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(TiktokListing::class, 'listing_id');
    }

    public function jubelioInventory(): BelongsTo
    {
        return $this->belongsTo(JubelioInventory::class, 'sku_code', 'sku_code');
    }
}
