<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductAdStore extends Pivot
{
    protected $table = "product_ad_store";
    protected $fillable = [
        "product_ad_id",
        "store_id"
    ];

    public function productAd(): BelongsTo
    {
        return $this->belongsTo(ProductAd::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
