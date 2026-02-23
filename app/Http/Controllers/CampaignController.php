<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $query = Campaign::query();

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('campaign_name', 'like', $term)
                    ->orWhere('campaign_type', 'like', $term)
                    ->orWhere('campaign_status', 'like', $term)
                    ->orWhere('assigned_to', 'like', $term);
            });
        }
        if ($request->filled('list')) {
            $query->where('list_name', $request->list);
        }
        if ($request->filled('status')) {
            $query->where('campaign_status', $request->status);
        }

        $campaigns = $query->orderBy('created_at', 'desc')->paginate(15);
        $lists = Campaign::whereNotNull('list_name')->distinct()->pluck('list_name');

        $totalCampaigns = Campaign::count();
        $activeCampaigns = Campaign::where('campaign_status', 'Active')->count();
        $planningCampaigns = Campaign::where('campaign_status', 'Planning')->count();
        $completedCampaigns = Campaign::where('campaign_status', 'Completed')->count();
        $totalExpectedRevenue = Campaign::where('campaign_status', 'Active')->sum('expected_revenue');

        return view('marketing.campaigns', [
            'campaigns' => $campaigns,
            'lists' => $lists,
            'totalCampaigns' => $totalCampaigns,
            'activeCampaigns' => $activeCampaigns,
            'planningCampaigns' => $planningCampaigns,
            'completedCampaigns' => $completedCampaigns,
            'totalExpectedRevenue' => $totalExpectedRevenue,
        ]);
    }

    public function create(): View
    {
        return view('marketing.campaigns-create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'campaign_name' => 'required|string|max:255',
            'campaign_type' => 'nullable|string|max:100',
            'campaign_status' => 'nullable|string|max:50',
            'expected_revenue' => 'nullable|numeric',
            'expected_close_date' => 'nullable|date',
            'assigned_to' => 'nullable|string|max:255',
            'list_name' => 'nullable|string|max:255',
        ]);

        Campaign::create($validated);
        Cache::forget('geminia_all_campaigns');

        return redirect()->route('marketing.campaigns.index')->with('success', 'Campaign created.');
    }

    public function edit(Campaign $campaign): View
    {
        return view('marketing.campaigns-edit', ['campaign' => $campaign]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'campaign_name' => 'required|string|max:255',
            'campaign_type' => 'nullable|string|max:100',
            'campaign_status' => 'nullable|string|max:50',
            'expected_revenue' => 'nullable|numeric',
            'expected_close_date' => 'nullable|date',
            'assigned_to' => 'nullable|string|max:255',
            'list_name' => 'nullable|string|max:255',
        ]);

        $campaign->update($validated);
        Cache::forget('geminia_all_campaigns');

        return redirect()->route('marketing.campaigns.index')->with('success', 'Campaign updated.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $campaign->delete();
        Cache::forget('geminia_all_campaigns');
        return redirect()->route('marketing.campaigns.index')->with('success', 'Campaign deleted.');
    }
}
