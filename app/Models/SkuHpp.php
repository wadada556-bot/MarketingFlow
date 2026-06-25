<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkuHpp extends Model
{
    protected $table      = 'sku_hpp';
    protected $primaryKey = 'sku_code';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = ['sku_code', 'hpp', 'synced_at'];

    protected $casts = [
        'hpp'       => 'integer',
        'synced_at' => 'datetime',
    ];
}
