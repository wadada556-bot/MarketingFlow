<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Category extends Model
{
    protected $fillable = [
        'name',
    ];

    public function getNameAttribute($value): string
    {
        return ucwords($value);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function productAds(): HasManyThrough
    {
        return $this->hasManyThrough(ProductAd::class, Product::class);
    }
}