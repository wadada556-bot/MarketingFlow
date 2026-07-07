<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat perubahan harga per (toko, SKU). Baris di-INSERT oleh trigger DB
 * `trg_store_sku_prices_history` (lihat migration), bukan oleh aplikasi —
 * jadi model ini praktis read-only. Hanya `changed_at` yang bermakna waktu;
 * tabel tidak punya created_at/updated_at.
 */
class StoreSkuPriceHistory extends Model
{
    protected $table = 'store_sku_price_histories';

    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'sku_code',
        'price_type',
        'old_price',
        'new_price',
        'changed_at',
    ];

    protected $casts = [
        'old_price'  => 'integer',
        'new_price'  => 'integer',
        'changed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
