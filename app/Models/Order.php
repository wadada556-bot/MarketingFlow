<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'sales_date',
        'channel_id',
        'channel_name',
        'store_id',
        'store_name',
        'sku_parent',
        'sku_variant',
        'qty',
        'salesorder_id',
        'salesorder_detail_id',
        'last_modified',
    ];

    protected $casts = [
        'sales_date'           => 'date',
        'channel_id'           => 'integer',
        'store_id'             => 'integer',
        'qty'                  => 'integer',
        'salesorder_id'        => 'integer',
        'salesorder_detail_id' => 'integer',
        'last_modified'        => 'datetime',
    ];
}
