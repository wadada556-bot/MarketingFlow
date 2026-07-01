<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JubelioInventory extends Model
{
    protected $table      = 'jubelio_inventory';
    protected $primaryKey = 'sku_code';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = ['sku_code', 'parent_sku', 'stok', 'hpp', 'po_qty', 'synced_at'];

    protected $casts = [
        'stok'      => 'integer',
        'hpp'       => 'integer',
        'po_qty'    => 'integer',
        'synced_at' => 'datetime',
    ];
}
