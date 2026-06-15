<?php

namespace App\Http\Controllers;

use App\Models\MarketingCampaign;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = $request->input('status');
        $typeFilter = $request->input('type');

        $campaignsQuery = MarketingCampaign::with([
            'product.category',
            'stores',
            'latestHistory'
        ]);

        $campaignsQuery->when($statusFilter, function ($query, $status) {
            $query->where('status', $status);
        });
        $campaignsQuery->when($typeFilter, function ($query, $type) {
            $query->where('type', $type);
        });

        $campaigns = $campaignsQuery->orderBy('start_date', 'desc')->paginate(15);
        $campaigns->appends($request->all());

        return view('campaigns.index', compact('campaigns'));
    }
}