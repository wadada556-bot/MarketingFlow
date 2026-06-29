<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySkuSales extends Model
{
    protected $table = 'daily_sku_sales';

    protected $fillable = [
        'sales_date',
        'channel_id',
        'channel_name',
        'store_id',
        'store_name',
        'sku',
        'parent_sku',
        'product_name',
        'qty_terjual',
        'omzet',
        'order_count',
        'synced_at',
    ];

    protected $casts = [
        'sales_date'  => 'date',
        'channel_id'  => 'integer',
        'store_id'    => 'integer',
        'qty_terjual' => 'integer',
        'omzet'       => 'integer',
        'order_count' => 'integer',
        'synced_at'   => 'datetime',
    ];
}
