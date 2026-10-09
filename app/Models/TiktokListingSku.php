<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokListingSku extends Model
{
    protected $fillable = ['listing_id', 'product_id', 'tiktok_sku_id'];

    protected $casts = [
        'listing_id'    => 'integer',
        'product_id'    => 'integer',
        'tiktok_sku_id' => 'integer',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(TiktokListing::class, 'listing_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
