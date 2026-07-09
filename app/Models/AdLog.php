<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan bertanggal untuk sebuah iklan (meniru product_ad_logs). Milik aplikasi.
 */
class AdLog extends Model
{
    protected $fillable = ['ad_id', 'action_date', 'description'];

    protected $casts = ['action_date' => 'date'];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }
}
