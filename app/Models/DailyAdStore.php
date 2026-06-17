<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyAdStore extends Model
{
    protected $fillable = [
        'store_id',
        'tanggal',
        'cost',
        'roi',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'cost'    => 'integer',
        'roi'     => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
