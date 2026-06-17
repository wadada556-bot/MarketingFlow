<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyStoreStat extends Model
{
    protected $fillable = ['store_id', 'tanggal', 'gmv', 'pesanan'];

    protected $casts = [
        'tanggal' => 'date',
        'gmv'     => 'integer',
        'pesanan' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
