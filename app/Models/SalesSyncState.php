<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesSyncState extends Model
{
    protected $table = 'sales_sync_state';

    protected $fillable = [
        'key',
        'last_synced_at',
        'cursor_date',
        'cursor_page',
        'status',
        'note',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'cursor_date'    => 'date',
        'cursor_page'    => 'integer',
    ];
}
