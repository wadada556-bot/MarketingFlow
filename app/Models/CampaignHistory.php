<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignHistory extends Model
{
    protected $fillable = [
        'marketing_campaign_id',
        'action_date',
        'description',
        'step_number',
    ];

    public function marketingCampaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class);
    }
}
