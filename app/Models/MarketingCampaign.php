<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingCampaign extends Model
{
    protected $fillable = [
        'product_id',
        'type',
        'start_date',
        'status'
    ];
}
