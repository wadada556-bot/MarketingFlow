<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSkuPrice extends Model
{
    protected $table = 'store_sku_prices';

    protected $fillable = [
        'store_id',
        'sku_code',
        'match_sku',
        'retail_price',
        'promotion_price',
        'synced_at',
    ];

    protected $casts = [
        'store_id'        => 'integer',
        'retail_price'    => 'integer',
        'promotion_price' => 'integer',
        'synced_at'       => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
