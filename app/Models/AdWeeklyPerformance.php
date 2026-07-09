<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Performa iklan per minggu — READ ONLY bagi aplikasi.
 * Ditulis importer (C:\ads\ads_pipeline). Satu baris per (ad, campaign, minggu).
 * `cost` = field "Biaya" TikTok.
 */
class AdWeeklyPerformance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'roi'          => 'decimal:2',
        'raw_json'     => 'array',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }
}
