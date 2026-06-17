<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAdLog extends Model
{
    protected $fillable = [
        "product_ad_id",
        "action_date",
        "description"
    ];

    public function productAd(): BelongsTo
    {
        return $this->belongsTo(ProductAd::class);
    }
}
