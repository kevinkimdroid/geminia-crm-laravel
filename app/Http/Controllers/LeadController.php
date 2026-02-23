<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\CrmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function __construct(
        private CrmService $crm
    ) {}

    public function index(Request $request): View
    {
        $search = $request->get('q');
        $perPage = 25;
        $page = max(1, (int) $request->get('page', 1));
        $offset = ($page - 1) * $perPage;

        $leads = $this->crm->getLeads($perPage, $offset, $search);
        $total = $this->crm->getLeadsCount($search);

        $leads = new LengthAwarePaginator(
            $leads instanceof Collection ? $leads : collect($leads),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('leads.index', [
            'leads' => $leads,
            'total' => $total,
        ]);
    }

    public function create(): View
    {
        return view('leads.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'leadsource' => 'nullable|string|max:100',
        ]);

        try {
            $ownerId = \Illuminate\Support\Facades\Auth::guard('vtiger')->id() ?? 1;
            $label = trim($validated['firstname'] . ' ' . $validated['lastname']) ?: $validated['company'] ?? 'Lead';
            $now = now()->format('Y-m-d H:i:s');
            $id = null;

            \DB::connection('vtiger')->transaction(function () use ($validated, $ownerId, $label, $now, &$id) {
                $id = (int) \DB::connection('vtiger')->table('vtiger_crmentity')->max('crmid') + 1;

                \DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                    'crmid' => $id,
                    'smcreatorid' => $ownerId,
                    'smownerid' => $ownerId,
                    'modifiedby' => $ownerId,
                    'setype' => 'Leads',
                    'description' => '',
                    'createdtime' => $now,
                    'modifiedtime' => $now,
                    'viewedtime' => null,
                    'status' => '',
                    'version' => 0,
                    'presence' => 1,
                    'deleted' => 0,
                    'smgroupid' => 0,
                    'source' => 'CRM',
                    'label' => $label,
                ]);

                \DB::connection('vtiger')->table('vtiger_leaddetails')->insert([
                    'leadid' => $id,
                    'lead_no' => 'LD' . $id,
                    'firstname' => $validated['firstname'],
                    'lastname' => $validated['lastname'],
                    'company' => $validated['company'] ?? '',
                    'email' => $validated['email'] ?? '',
                    'leadsource' => $validated['leadsource'] ?? '',
                ]);

                $phone = $validated['phone'] ?? '';
                if ($phone !== '') {
                    \DB::connection('vtiger')->table('vtiger_leadaddress')->updateOrInsert(
                        ['leadaddressid' => $id],
                        ['mobile' => $phone, 'phone' => $phone]
                    );
                }
            });
            Cache::forget('geminia_leads_count');
            Cache::forget('geminia_leads_today_' . now()->format('Y-m-d'));
            Cache::forget('geminia_dashboard_stats');
            \App\Events\DashboardStatsUpdated::dispatch();
            return redirect()->route('leads.show', $id)->with('success', 'Lead created.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create lead: ' . $e->getMessage());
        }
    }

    public function show(int $id): View|RedirectResponse
    {
        $lead = $this->crm->getLead($id);
        if (!$lead) {
            return redirect()->route('leads.index')->with('error', 'Lead not found.');
        }
        return view('leads.show', ['lead' => $lead]);
    }

    public function edit(int $id): View|RedirectResponse
    {
        $lead = $this->crm->getLead($id);
        if (!$lead) {
            return redirect()->route('leads.index')->with('error', 'Lead not found.');
        }
        return view('leads.edit', ['lead' => $lead]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $lead = $this->crm->getLead($id);
        if (!$lead) {
            return redirect()->route('leads.index')->with('error', 'Lead not found.');
        }

        $validated = $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'leadsource' => 'nullable|string|max:100',
        ]);

        try {
            Lead::on('vtiger')->where('leadid', $id)->update([
                'firstname' => $validated['firstname'],
                'lastname' => $validated['lastname'],
                'company' => $validated['company'] ?? '',
                'email' => $validated['email'] ?? '',
                'leadsource' => $validated['leadsource'] ?? '',
            ]);
            $phone = $validated['phone'] ?? '';
            \DB::connection('vtiger')->table('vtiger_leadaddress')->updateOrInsert(
                ['leadaddressid' => $id],
                ['mobile' => $phone, 'phone' => $phone]
            );
            return redirect()->route('leads.show', $id)->with('success', 'Lead updated.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to update: ' . $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            \DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $id)->update(['deleted' => 1]);
            Cache::forget('geminia_leads_count');
            Cache::forget('geminia_dashboard_stats');
            \App\Events\DashboardStatsUpdated::dispatch();
            return redirect()->route('leads.index')->with('success', 'Lead deleted.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }
}
