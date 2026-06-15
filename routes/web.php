<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarketingCampaignController;


Route::get('/campaigns', [MarketingCampaignController::class, 'index'])
    ->name('campaigns.index');
