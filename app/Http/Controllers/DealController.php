<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Services\CrmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DealController extends Controller
{
    public function __construct(
        private CrmService $crm
    ) {}

    public function index(Request $request): View
    {
        $perPage = 25;
        $page = max(1, (int) $request->get('page', 1));
        $offset = ($page - 1) * $perPage;

        $deals = $this->crm->getDeals($perPage, $offset);
        $total = $this->crm->getDealsCount();

        $deals = new LengthAwarePaginator(
            $deals instanceof Collection ? $deals : collect($deals),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $pipelineValue = Cache::remember('geminia_pipeline_value', 90, fn () => $this->crm->getPipelineValue());

        return view('deals.index', [
            'deals' => $deals,
            'pipelineValue' => $pipelineValue,
        ]);
    }

    public function create(): View
    {
        return view('deals.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'potentialname' => 'required|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'sales_stage' => 'nullable|string|max:100',
            'closingdate' => 'nullable|date',
        ]);

        try {
            $id = \DB::connection('vtiger')->table('vtiger_potential')->insertGetId([
                'potentialname' => $validated['potentialname'],
                'amount' => $validated['amount'] ?? 0,
                'sales_stage' => $validated['sales_stage'] ?? 'Prospecting',
                'closingdate' => $validated['closingdate'] ?? null,
            ]);
            Cache::forget('geminia_pipeline_value');
            Cache::forget('geminia_deals_count');
            Cache::forget('geminia_reports_index');
            Cache::forget('geminia_dashboard_stats');
            \App\Events\DashboardStatsUpdated::dispatch();
            return redirect()->route('deals.show', $id)->with('success', 'Deal created.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create deal: ' . $e->getMessage());
        }
    }

    public function show(int $id): View|RedirectResponse
    {
        $deal = $this->crm->getDeal($id);
        if (!$deal) {
            return redirect()->route('deals.index')->with('error', 'Deal not found.');
        }
        return view('deals.show', ['deal' => $deal]);
    }

    public function edit(int $id): View|RedirectResponse
    {
        $deal = $this->crm->getDeal($id);
        if (!$deal) {
            return redirect()->route('deals.index')->with('error', 'Deal not found.');
        }
        return view('deals.edit', ['deal' => $deal]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $deal = $this->crm->getDeal($id);
        if (!$deal) {
            return redirect()->route('deals.index')->with('error', 'Deal not found.');
        }

        $validated = $request->validate([
            'potentialname' => 'required|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'sales_stage' => 'nullable|string|max:100',
            'closingdate' => 'nullable|date',
        ]);

        try {
            Deal::on('vtiger')->where('potentialid', $id)->update([
                'potentialname' => $validated['potentialname'],
                'amount' => $validated['amount'] ?? 0,
                'sales_stage' => $validated['sales_stage'] ?? $deal->sales_stage,
                'closingdate' => $validated['closingdate'] ?? $deal->closingdate,
            ]);
            Cache::forget('geminia_pipeline_value');
            Cache::forget('geminia_deals_count');
            Cache::forget('geminia_reports_index');
            Cache::forget('geminia_dashboard_stats');
            \App\Events\DashboardStatsUpdated::dispatch();
            return redirect()->route('deals.show', $id)->with('success', 'Deal updated.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to update: ' . $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            \DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $id)->update(['deleted' => 1]);
            Cache::forget('geminia_pipeline_value');
            Cache::forget('geminia_deals_count');
            Cache::forget('geminia_reports_index');
            Cache::forget('geminia_dashboard_stats');
            \App\Events\DashboardStatsUpdated::dispatch();
            return redirect()->route('deals.index')->with('success', 'Deal deleted.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }
}
