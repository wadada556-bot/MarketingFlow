<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JubelioInventory extends Model
{
    protected $table      = 'jubelio_inventory';
    protected $primaryKey = 'sku_code';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = ['sku_code', 'parent_sku', 'match_sku', 'item_group_id', 'variation_label', 'stok', 'hpp', 'po_qty', 'synced_at'];

    protected $casts = [
        'item_group_id' => 'integer',
        'stok'          => 'integer',
        'hpp'           => 'integer',
        'po_qty'        => 'integer',
        'synced_at'     => 'datetime',
    ];
}
