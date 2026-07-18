<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master produk — 1 baris per SKU fisik. Sumber data: sync Jubelio
 * (jubelio:sync-inventory utk stok/PO/label, hpp:sync utk HPP) + edit HPP
 * manual dari menu Products. hpp = 0 berarti "belum terisi".
 */
class Product extends Model
{
    protected $fillable = [
        'sku_code', 'parent_sku', 'variation_label', 'stok', 'hpp', 'po_qty', 'synced_at',
    ];

    protected $casts = [
        'stok'      => 'integer',
        'hpp'       => 'integer',
        'po_qty'    => 'integer',
        'synced_at' => 'datetime',
    ];

    public function listingSkus(): HasMany
    {
        return $this->hasMany(TiktokListingSku::class, 'product_id');
    }
}
