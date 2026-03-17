<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactFollowup;
use App\Services\CrmService;
use Illuminate\Support\Facades\Cache;
use App\Services\ErpClientService;
use App\Services\MailService;
use App\Services\PbxCallService;
use App\Services\PbxConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ContactController extends Controller
{
    /** @var CrmService */
    protected $crm;
    /** @var ErpClientService */
    protected $erp;
    /** @var PbxCallService */
    protected $pbxCalls;
    /** @var PbxConfigService */
    protected $pbxConfig;
    /** @var MailService */
    protected $mailService;

    public function __construct(CrmService $crm, ErpClientService $erp, PbxCallService $pbxCalls, PbxConfigService $pbxConfig, MailService $mailService)
    {
        $this->crm = $crm;
        $this->erp = $erp;
        $this->pbxCalls = $pbxCalls;
        $this->pbxConfig = $pbxConfig;
        $this->mailService = $mailService;
    }

    public function index(Request $request): View
    {
        $perPage = 25;
        $page = max(1, (int) $request->get('page', 1));
        $offset = ($page - 1) * $perPage;

        $ownerId = crm_owner_filter();
        $contacts = $this->crm->getContacts($perPage, $offset, $ownerId);
        $total = $this->crm->getContactsCount($ownerId);

        $contacts = new LengthAwarePaginator(
            $contacts instanceof Collection ? $contacts : collect($contacts),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('contacts.index', [
            'contacts' => $contacts,
            'total' => $total,
        ]);
    }

    public function create(): View
    {
        return view('contacts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
        ]);

        try {
            $ownerId = \Illuminate\Support\Facades\Auth::id() ?? \Illuminate\Support\Facades\Auth::guard('vtiger')->id() ?? 1;
            $label = trim($validated['firstname'] . ' ' . $validated['lastname']);
            $now = now()->format('Y-m-d H:i:s');
            $id = (int) \DB::connection('vtiger')->table('vtiger_crmentity')->max('crmid') + 1;

            \DB::connection('vtiger')->transaction(function () use ($id, $ownerId, $label, $now, $validated) {
                \DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                    'crmid' => $id,
                    'smcreatorid' => $ownerId,
                    'smownerid' => $ownerId,
                    'modifiedby' => $ownerId,
                    'setype' => 'Contacts',
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

                \DB::connection('vtiger')->table('vtiger_contactdetails')->insert([
                    'contactid' => $id,
                    'firstname' => $validated['firstname'],
                    'lastname' => $validated['lastname'],
                    'email' => $validated['email'] ?? '',
                    'phone' => $validated['phone'] ?? '',
                    'mobile' => $validated['mobile'] ?? '',
                ]);
            });

            Cache::forget('geminia_contacts_count');
            Cache::forget('ticket_create_clients');
            Cache::forget('geminia_dashboard_stats');
            \App\Events\DashboardStatsUpdated::dispatch();
            return redirect()->route('contacts.show', $id)->with('success', 'Contact created.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create contact: ' . $e->getMessage());
        }
    }

    /** @return View|RedirectResponse */
    public function show(Request $request, int $id)
    {
        $contact = $this->crm->getContact($id);
        if (!$contact) {
            return redirect()->route('contacts.index')->with('error', 'Contact not found.');
        }
        abort_if(!contact_can_access($id), 403, 'You do not have permission to access this record.');
        $tab = $request->get('tab', 'summary');
        $tickets = collect();
        $ticketsPaginator = null;
        $policies = [];
        $policiesError = null;
        $calls = collect();
        $callsTotal = 0;
        $smsLogs = collect();
        $smsPaginator = null;
        $emails = [];
        $emailsPaginator = null;
        $campaigns = collect();
        $followups = collect();

        if ($tab === 'campaigns') {
            $campaigns = $this->crm->getCampaignsForContact($id);
        }

        $followups = ContactFollowup::where('contact_id', $id)->orderByDesc('followup_date')->orderByDesc('created_at')->limit(20)->get();

        if ($tab === 'emails') {
            $emailsPage = max(1, (int) $request->get('page', 1));
            $emailsPerPage = 20;
            $emailsOffset = ($emailsPage - 1) * $emailsPerPage;
            $emails = $this->mailService->getEmailsForContact($contact, $emailsPerPage, $emailsOffset);
            $emailsTotal = $this->mailService->getEmailsForContactCount($contact);
            $emailsPaginator = new LengthAwarePaginator(
                $emails,
                $emailsTotal,
                $emailsPerPage,
                $emailsPage,
                ['path' => route('contacts.show', $id), 'query' => array_merge($request->query(), ['tab' => 'emails'])]
            );
        }

        if ($tab === 'sms') {
            $smsPage = max(1, (int) $request->get('page', 1));
            $smsPerPage = 20;
            $smsOffset = ($smsPage - 1) * $smsPerPage;
            $smsLogs = $this->crm->getSmsForContact($id, $smsPerPage, $smsOffset);
            $smsTotal = $this->crm->getSmsForContactCount($id);
            $smsPaginator = new LengthAwarePaginator(
                $smsLogs,
                $smsTotal,
                $smsPerPage,
                $smsPage,
                ['path' => route('contacts.show', $id), 'query' => array_merge($request->query(), ['tab' => 'sms'])]
            );
        }

        if ($tab === 'calls') {
            $callsPage = max(1, (int) $request->get('page', 1));
            $callsPerPage = 20;
            $callsOffset = ($callsPage - 1) * $callsPerPage;
            $callsResult = $this->pbxCalls->getCallsForContact($contact, $callsPerPage, $callsOffset);
            $calls = $callsResult['calls'];
            $callsTotal = $callsResult['total'];
            $callsPaginator = new LengthAwarePaginator(
                $calls,
                $callsTotal,
                $callsPerPage,
                $callsPage,
                ['path' => route('contacts.show', $id), 'query' => array_merge($request->query(), ['tab' => 'calls'])]
            );
            $pbxFromVtiger = $callsResult['from_vtiger'] ?? false;
            $pbxCanCall = $this->pbxConfig->isConfigured();
        } else {
            $callsPaginator = null;
            $pbxFromVtiger = false;
            $pbxCanCall = false;
        }

        if ($tab === 'policies') {
            $result = $this->erp->getPoliciesForContact($contact);
            $policies = $result['data'] ?? [];
            $policiesError = $result['error'] ?? null;
        }

        if ($tab === 'tickets') {
            $page = max(1, (int) $request->get('page', 1));
            $perPage = 20;
            $offset = ($page - 1) * $perPage;
            $ticketStatus = $request->get('list');
            $ticketSearch = $request->get('search');

            $ownerId = crm_owner_filter();
            $tickets = $this->crm->getTicketsForContactPaginated($id, $perPage, $offset, $ticketStatus, $ticketSearch, $ownerId);
            $total = $this->crm->getTicketsForContactCount($id, $ticketStatus, $ticketSearch, $ownerId);

            $ticketsPaginator = new LengthAwarePaginator(
                $tickets instanceof Collection ? $tickets : collect($tickets),
                $total,
                $perPage,
                $page,
                ['path' => route('contacts.show', $id), 'query' => $request->query()]
            );
        }

        $ownerId = crm_owner_filter();
        $adjacent = $this->crm->getAdjacentContactIds($id, $ownerId);
        $ticketsCount = $this->crm->getTicketsForContactCount($id, null, null, $ownerId);

        return view('contacts.show', [
            'contact' => $contact,
            'deals' => $this->crm->getContactDeals($id, 5),
            'activities' => $this->crm->getContactActivities($id, 5),
            'comments' => $this->crm->getContactComments($id, 5),
            'activeTab' => $tab,
            'tickets' => $tickets,
            'ticketsPaginator' => $ticketsPaginator,
            'ticketStatus' => $tab === 'tickets' ? $request->get('list') : null,
            'ticketSearch' => $tab === 'tickets' ? $request->get('search') : null,
            'policies' => $policies ?? [],
            'policiesError' => $policiesError ?? null,
            'calls' => $calls ?? collect(),
            'callsPaginator' => $callsPaginator ?? null,
            'smsLogs' => $smsLogs ?? collect(),
            'smsPaginator' => $smsPaginator ?? null,
            'pbxFromVtiger' => $pbxFromVtiger ?? false,
            'pbxCanCall' => $pbxCanCall ?? false,
            'prevContactId' => $adjacent['prev'],
            'nextContactId' => $adjacent['next'],
            'ticketsCount' => $ticketsCount,
            'emails' => $emails ?? [],
            'emailsPaginator' => $emailsPaginator ?? null,
            'emailsCount' => (int) Cache::remember('geminia_emails_contact_' . $id, 120, fn () => $this->mailService->getEmailsForContactCount($contact)),
            'campaigns' => $campaigns ?? collect(),
            'followups' => $followups ?? collect(),
            'allCampaigns' => Cache::remember('geminia_all_campaigns', 300, fn () => Campaign::orderBy('campaign_name')->get()),
        ]);
    }

    /** @return View|RedirectResponse */
    public function edit(int $id)
    {
        $contact = $this->crm->getContact($id);
        if (!$contact) {
            return redirect()->route('contacts.index')->with('error', 'Contact not found.');
        }
        abort_if(!contact_can_access($id), 403, 'You do not have permission to access this record.');
        return view('contacts.edit', ['contact' => $contact]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $contact = $this->crm->getContact($id);
        if (!$contact) {
            return redirect()->route('contacts.index')->with('error', 'Contact not found.');
        }
        abort_if(!contact_can_access($id), 403, 'You do not have permission to access this record.');

        $validated = $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
        ]);

        try {
            Contact::on('vtiger')->where('contactid', $id)->update([
                'firstname' => $validated['firstname'],
                'lastname' => $validated['lastname'],
                'email' => $validated['email'] ?? '',
                'phone' => $validated['phone'] ?? '',
                'mobile' => $validated['mobile'] ?? '',
            ]);
            return redirect()->route('contacts.show', $id)->with('success', 'Contact updated.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to update: ' . $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            \DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $id)->update(['deleted' => 1]);
            Cache::forget('geminia_contacts_count');
            Cache::forget('ticket_create_clients');
            Cache::forget('geminia_dashboard_stats');
            \App\Events\DashboardStatsUpdated::dispatch();
            return redirect()->route('contacts.index')->with('success', 'Contact deleted.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }

    public function storeFollowup(Request $request, int $contact): RedirectResponse
    {
        $validated = $request->validate([
            'note' => 'required|string|max:5000',
            'followup_date' => 'nullable|date',
        ]);

        ContactFollowup::create([
            'contact_id' => $contact,
            'user_id' => ($user = $request->user()) ? $user->id : null,
            'note' => $validated['note'],
            'followup_date' => $validated['followup_date'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('contacts.show', $contact)->with('success', 'Follow-up logged.');
    }

    public function addToCampaign(Request $request, int $contact): RedirectResponse
    {
        $request->validate([
            'campaign_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('campaigns', 'id')],
        ]);

        if ($this->crm->addContactToCampaign($contact, (int) $request->campaign_id)) {
            return redirect()->route('contacts.show', $contact)->with('success', 'Contact added to campaign.');
        }
        return back()->with('error', 'Could not add contact to campaign.');
    }

    public function removeFromCampaign(int $contact, int $campaign): RedirectResponse
    {
        if ($this->crm->removeContactFromCampaign($contact, $campaign)) {
            return redirect()->route('contacts.show', $contact)->with('success', 'Contact removed from campaign.');
        }
        return back()->with('error', 'Could not remove contact from campaign.');
    }
}
