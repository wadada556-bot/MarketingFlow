<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyProductAd extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'tanggal',
        'roi',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'roi'     => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
