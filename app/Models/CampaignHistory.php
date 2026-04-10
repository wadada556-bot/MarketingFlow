<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignHistory extends Model
{
    protected $fillable = [
        'marketing_campaign_id',
        'action_date',
        'description',
        'step_number',
    ];
}
