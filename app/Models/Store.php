<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $fillable = [
        'name'
    ];
    protected $hidden = ['pivot'];

    public function getNameAttribute($value): string
    {
        return strtoupper($value);
    }

    public function tiktokListings(): HasMany
    {
        return $this->hasMany(TiktokListing::class);
    }

    public function tiktokListingSkus(): HasMany
    {
        return $this->hasMany(TiktokListingSku::class);
    }
}
