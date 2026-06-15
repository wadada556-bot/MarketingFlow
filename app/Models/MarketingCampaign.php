<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketingCampaign extends Model
{
    protected $fillable = [
        'product_id',
        'type',
        'start_date',
        'status'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'campaign_store');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CampaignHistory::class)->orderBy('step_number', 'asc');
    }

    public function latestHistory(): HasOne
    {
        return $this->hasOne(CampaignHistory::class)->latestOfMany('step_number');
    }
}
