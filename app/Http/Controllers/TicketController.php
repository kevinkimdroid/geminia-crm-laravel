<?php

namespace App\Http\Controllers;

use App\Exports\TicketsExport;
use App\Models\Ticket;
use App\Services\CrmService;
use App\Services\ErpClientService;
use App\Services\TicketAutomationService;
use App\Services\TicketSlaService;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TicketController extends Controller
{
    /** @var CrmService */
    protected $crm;
    /** @var ErpClientService|null */
    protected $erp;
    /** @var TicketAutomationService */
    protected $automation;
    /** @var TicketSlaService */
    protected $sla;

    public function __construct(CrmService $crm, TicketAutomationService $automation, TicketSlaService $sla, ?ErpClientService $erp = null)
    {
        $this->crm = $crm;
        $this->erp = $erp ?? app()->has(ErpClientService::class) ? app(ErpClientService::class) : null;
        $this->automation = $automation;
        $this->sla = $sla;
    }

    public function index(Request $request): View
    {
        $status = $request->get('list');
        $search = $request->get('search');
        $ownerFilter = crm_owner_filter();
        $assignedTo = $ownerFilter ?? ($request->filled('assigned_to') ? (int) $request->get('assigned_to') : null);
        $page = max(1, (int) $request->get('page', 1));
        $perPage = max(10, min(100, (int) ($request->get('per_page') ?: 25)));
        $offset = ($page - 1) * $perPage;

        $isDefaultView = (!$status || trim((string) $status) === '') && (!$search || trim((string) $search) === '') && $page === 1 && !$assignedTo;
        $isStatusPage1 = $status && trim((string) $status) !== '' && (!$search || trim((string) $search) === '') && $page === 1 && !$assignedTo;
        $statusSlug = $status ? str_replace(' ', '_', trim((string) $status)) : '';
        $ownerSuffix = $ownerFilter !== null ? '_u' . $ownerFilter : '';
        $cacheKey = $isDefaultView ? 'tickets_list_default' . $ownerSuffix : ($isStatusPage1 ? 'tickets_list_' . $statusSlug . $ownerSuffix : null);

        if ($cacheKey) {
            $ttl = $isDefaultView ? 120 : 90;
            $cached = Cache::remember($cacheKey, $ttl, function () use ($perPage, $status, $search, $assignedTo) {
                $items = $this->crm->getTickets($perPage, 0, $status, $search, false, $assignedTo);
                $count = $this->crm->getTicketsCount($status, $search, $assignedTo);
                return ['tickets' => $items, 'total' => $count];
            });
            $tickets = $cached['tickets'];
            $total = $cached['total'];
        } else {
            $tickets = $this->crm->getTickets($perPage, $offset, $status, $search, false, $assignedTo);
            $total = $this->crm->getTicketsCount($status, $search, $assignedTo);
        }

        $ticketCounts = $this->crm->getTicketCountsByStatus($ownerFilter);
        $users = Cache::remember('ticket_assign_users', 300, fn () => $this->crm->getActiveUsers());

        $tickets = new LengthAwarePaginator(
            $tickets instanceof Collection ? $tickets : collect($tickets),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('tickets.index', [
            'tickets' => $tickets,
            'ticketCounts' => $ticketCounts,
            'total' => $total,
            'currentList' => $status,
            'search' => $search,
            'assignedTo' => $assignedTo,
            'users' => $users,
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $status = $request->get('list');
        $search = $request->get('search');
        $assignedTo = crm_owner_filter() ?? ($request->filled('assigned_to') ? (int) $request->get('assigned_to') : null);

        $tickets = $this->crm->getTicketsForExport($status, $search, 50000, $assignedTo);

        $rows = collect($tickets)->map(function ($ticket) {
            $contactName = trim(($ticket->contact_first ?? '') . ' ' . ($ticket->contact_last ?? '')) ?: '—';
            $policyNum = pick_policy_excluding_pin($ticket->cf_860 ?? null, $ticket->cf_856 ?? null, $ticket->cf_872 ?? null);
            if (! $policyNum && ! empty($ticket->description ?? '') && preg_match('/Related policy:\s*([^\n]+)/i', $ticket->description, $m)) {
                $p = trim($m[1]);
                $cid = (string) ($ticket->contact_id ?? '');
                if ($p !== '' && $p !== $cid && ! looks_like_kra_pin($p) && ! looks_like_client_id($p)) {
                    $policyNum = $p;
                }
            }
            $ownerName = trim(($ticket->owner_first ?? '') . ' ' . ($ticket->owner_last ?? '')) ?: ($ticket->owner_username ?? '—');
            $createdByName = trim((string) ($ticket->creator_name ?? '')) ?: ($ticket->creator_username ?? '—');
            $closedByName = ($ticket->status ?? '') === 'Closed'
                ? (trim((string) ($ticket->modifier_name ?? '')) ?: ($ticket->modifier_username ?? '—'))
                : '—';
            $ticketNo = $ticket->ticket_no ?? 'TT' . $ticket->ticketid;
            $created = $ticket->createdtime ? date('Y-m-d H:i', strtotime($ticket->createdtime)) : '—';

            return [
                $ticketNo,
                $ticket->title ?? 'Untitled',
                $contactName,
                $policyNum ?? '—',
                $ticket->status ?? '—',
                $ticket->priority ?? 'Normal',
                $ticket->source ?? '—',
                $createdByName,
                $ownerName,
                $closedByName,
                $created,
                trim($ticket->description ?? ''),
            ];
        })->toArray();

        $filename = 'tickets-' . date('Y-m-d') . '.xlsx';
        return Excel::download(new TicketsExport($rows), $filename);
    }

    public function create(Request $request): View
    {
        if ($request->get('refresh')) {
            Cache::forget('ticket_accounts');
            Cache::forget('ticket_create_clients');
        }
        $crm = app(CrmService::class);
        $contactId = $request->filled('contact_id') ? (int) $request->get('contact_id') : null;
        $fromServeClient = $request->get('from') === 'serve-client';
        $fromMailManager = $request->get('from') === 'mail-manager';
        $fromLead = $request->get('from') === 'lead';
        $leadId = $request->filled('lead_id') ? (int) $request->get('lead_id') : null;
        $clientNameParam = $request->filled('client_name') ? trim($request->get('client_name')) : null;

        if ($fromLead && $leadId) {
            $lead = $crm->getLead($leadId);
            if (! $lead) {
                return redirect()->route('leads.index')->with('error', 'Lead not found.');
            }
            $contact = $crm->findContactByPhoneOrEmail($lead->phone ?? $lead->mobile ?? null, $lead->email ?? '');
            if ($contact) {
                $contactId = (int) $contact->contactid;
            } else {
                $contactId = $crm->createContactFromErpClient([
                    'first_name' => $lead->firstname ?? 'Client',
                    'last_name' => $lead->lastname ?? '',
                    'email' => $lead->email ?? '',
                    'mobile' => $lead->mobile ?? $lead->phone ?? '',
                    'phone' => $lead->phone ?? $lead->mobile ?? '',
                ]);
            }
            if (! $contactId) {
                return redirect()->route('leads.show', $leadId)->with('error', 'Could not find or create contact for this lead.');
            }
            $presetTitle = 'Lead follow-up: ' . ($lead->company ?: $lead->full_name);
            $presetDescription = "Lead: {$lead->full_name}" . ($lead->company ? " ({$lead->company})" : '');
            if ($lead->email || $lead->phone || $lead->mobile) {
                $presetDescription .= "\n\nContact: " . trim(($lead->email ?: '') . ' ' . ($lead->phone ?: $lead->mobile ?: ''));
            }
            $clientNameParam = $lead->full_name;
        }

        $presetTitle = $presetTitle ?? ($request->filled('title') ? $request->get('title') : null);
        $presetDescription = $presetDescription ?? ($request->filled('description') ? $request->get('description') : null);

        if ($fromServeClient && $contactId) {
            $clients = collect([$crm->getContactById($contactId)])->filter();
        } else {
            $clients = Cache::remember('ticket_create_clients_' . (crm_owner_filter() ?? 'all'), 120, fn () => $crm->getCustomers(30, 0, null, crm_owner_filter()));
        }
        $contactDisplay = '';

        if ($contactId) {
            $client = $clients->firstWhere('contactid', $contactId);
            $contactDisplay = $client
                ? trim(($client->firstname ?? '') . ' ' . ($client->lastname ?? ''))
                : ($clientNameParam ?: $crm->getContactDisplayName($contactId));
            if (!$client && $contactId) {
                $singleContact = $crm->getContactById($contactId);
                if ($singleContact) {
                    $clients = $clients->prepend($singleContact);
                    $contactDisplay = $contactDisplay ?: trim(($singleContact->firstname ?? '') . ' ' . ($singleContact->lastname ?? '')) ?: $clientNameParam;
                }
            }
            // Ensure display when from Serve Client (client_name param)
            $contactDisplay = $contactDisplay ?: $clientNameParam;
        }
        $presetPolicy = $request->filled('policy') ? trim($request->get('policy')) : null;
        $authUser = \Illuminate\Support\Facades\Auth::guard('vtiger')->user();
        $userRole = ($authUser && $authUser->primary_role) ? $authUser->primary_role->rolename : null;

        try {
            $accounts = $this->sortAccountsForTickets(Cache::remember('ticket_accounts', 300, fn () => $crm->getAccounts(100)));
            if ($accounts->isEmpty()) {
                Cache::forget('ticket_accounts');
                $accounts = $this->getFallbackProductLines();
            } else {
                $accounts = $this->mergeFallbackProductLines($accounts);
            }
        } catch (\Throwable $e) {
            $accounts = $this->getFallbackProductLines();
        }
        $users = Cache::remember('ticket_assign_users', 300, fn () => $crm->getActiveUsers());

        return view('tickets.create', [
            'clients' => $clients,
            'accounts' => $accounts,
            'users' => $users,
            'presetContactId' => $contactId,
            'presetContactDisplay' => $contactDisplay ?: $clientNameParam,
            'presetPolicy' => $presetPolicy,
            'presetOrganizationId' => $request->get('organization_id'),
            'presetTitle' => $presetTitle,
            'presetDescription' => $presetDescription,
            'fromServeClient' => $fromServeClient,
            'fromMailManager' => $fromMailManager,
            'fromLead' => $fromLead,
            'returnToLead' => $fromLead ? $leadId : null,
            'returnToMailManager' => $request->filled('email_id'),
            'emailId' => $request->filled('email_id') ? (int) $request->get('email_id') : null,
            'canCloseTickets' => $this->sla->canUserCloseTickets($userRole),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'solution' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'priority' => 'nullable|string|max:50',
            'severity' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'contact_id' => 'required|integer',
            'product_id' => 'nullable', // integer for vtiger, or "erp:Product Name" from ERP fallback
            'organization_id' => 'nullable',
            'hours' => 'nullable|string|max:20',
            'days' => 'nullable|string|max:20',
            'ticket_source' => 'nullable|string|max:50',
            'assigned_to' => 'nullable|integer',
            'policy_number' => 'nullable|string|max:100',
            'return_to_mail_manager' => 'nullable',
            'return_to_lead' => 'nullable|integer',
            'email_id' => 'nullable|integer',
            'send_email_to_client' => 'nullable|boolean',
            'client_email_message' => 'nullable|string|max:2000',
        ]);

        if (($validated['status'] ?? '') === 'Closed') {
            $authUser = \Illuminate\Support\Facades\Auth::guard('vtiger')->user();
            $userRole = ($authUser && $authUser->primary_role) ? $authUser->primary_role->rolename : null;
            if (!$this->sla->canUserCloseTickets($userRole)) {
                return back()->withInput()->with('error', 'You do not have permission to create tickets with Closed status.');
            }
        }

        $userId = \Illuminate\Support\Facades\Auth::guard('vtiger')->id() ?? 1;
        $ownerId = $this->automation->resolveAssignee(
            $validated['title'],
            $validated['description'] ?? null
        ) ?? $validated['assigned_to'] ?? $userId;

        try {
            $description = $validated['description'] ?? '';
            $policyNumber = trim($validated['policy_number'] ?? '');
            $contactId = (int) ($validated['contact_id'] ?? 0);
            if ($policyNumber !== '' && $policyNumber !== (string) $contactId && ! looks_like_kra_pin($policyNumber) && ! looks_like_client_id($policyNumber)) {
                $description = trim($description) . "\n\nRelated policy: " . $policyNumber;
            }
            $orgId = $validated['organization_id'] ?? null;
            if (is_string($orgId) && str_starts_with($orgId, 'line:')) {
                $lineName = substr($orgId, 5);
                if ($lineName !== '' && ! str_contains($description, 'Product Line: ' . $lineName)) {
                    $description = trim($description) . "\n\nProduct Line: " . $lineName;
                }
            }
            $productId = null;
            $productIdRaw = $validated['product_id'] ?? null;
            if ($productIdRaw !== null && $productIdRaw !== '') {
                if (is_string($productIdRaw) && str_starts_with((string) $productIdRaw, 'erp:')) {
                    $erpProductName = substr((string) $productIdRaw, 4);
                    if ($erpProductName !== '') {
                        $description = trim($description) . "\n\nProduct: " . $erpProductName;
                    }
                } else {
                    $productId = (int) $productIdRaw;
                }
            }

            $solutionText = trim((string) ($validated['solution'] ?? ''));
            $fullDescription = $solutionText !== '' ? trim($description . "\n\n--- Resolution ---\n" . $solutionText) : $description;

            // Vtiger requires crmentity first (central entity table); ticketid = crmid
            $id = (int) \DB::connection('vtiger')->table('vtiger_crmentity')->max('crmid') + 1;
            $now = now()->format('Y-m-d H:i:s');
            $parentId = $this->resolveOrganizationId($validated['organization_id'] ?? null);

            \DB::connection('vtiger')->transaction(function () use ($validated, $userId, $ownerId, $fullDescription, $id, $now, $productId, $parentId) {
                \DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                    'crmid' => $id,
                    'smcreatorid' => $userId,
                    'smownerid' => $ownerId,
                    'modifiedby' => $userId,
                    'setype' => 'HelpDesk',
                    'description' => $fullDescription,
                    'createdtime' => $now,
                    'modifiedtime' => $now,
                    'viewedtime' => null,
                    'status' => 1,
                    'version' => 0,
                    'presence' => 1,
                    'deleted' => 0,
                    'smgroupid' => 0,
                    'source' => $validated['ticket_source'] ?? 'CRM',
                    'label' => $validated['title'],
                ]);

                \DB::connection('vtiger')->table('vtiger_troubletickets')->insert([
                    'ticketid' => $id,
                    'ticket_no' => 'TT' . $id,
                    'title' => $validated['title'],
                    'status' => $validated['status'] ?? 'Open',
                    'priority' => $validated['priority'] ?? 'Normal',
                    'severity' => $validated['severity'] ?? null,
                    'category' => ! empty($validated['category'] ?? '') ? $validated['category'] : 'Other',
                    'contact_id' => $validated['contact_id'],
                    'product_id' => $productId,
                    'parent_id' => $parentId,
                    'hours' => $validated['hours'] ?? null,
                    'days' => $validated['days'] ?? null,
                ]);
            });

            $this->forgetTicketListCaches();
            \App\Events\DashboardStatsUpdated::dispatch();

            try {
                $notifyPolicy = trim($validated['policy_number'] ?? '');
                $notifyPolicy = ($notifyPolicy !== '' && ! looks_like_kra_pin($notifyPolicy)) ? $notifyPolicy : null;
                app(\App\Services\TicketNotificationService::class)->sendTicketCreatedNotification(
                    $id,
                    'TT' . $id,
                    $validated['title'],
                    $ownerId,
                    (int) $validated['contact_id'],
                    $notifyPolicy,
                    $request->boolean('send_email_to_client'),
                    trim($validated['client_email_message'] ?? '') ?: null
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Ticket creation notification failed', ['error' => $e->getMessage()]);
            }

            if ($request->filled('return_to_mail_manager') && $request->filled('email_id')) {
                \DB::connection('vtiger')->table('mail_manager_emails')->where('id', (int) $request->get('email_id'))->update(['ticket_id' => $id]);
                return redirect()->route('tools.mail-manager', ['selected' => $request->get('email_id')])->with('success', 'Ticket created and linked to this email.');
            }
            if ($request->filled('return_to_serve_client')) {
                return redirect()->route('support.serve-client')->with('success', 'Ticket created. You can create another or search for a different client.');
            }
            if ($request->filled('return_to_lead')) {
                return redirect()->route('leads.show', (int) $request->get('return_to_lead'))->with('success', 'Ticket created.');
            }
            $returnToContact = $request->filled('return_to_contact') ? (int) $request->get('return_to_contact') : null;
            if ($returnToContact) {
                return redirect()->to(route('contacts.show', $returnToContact) . '?tab=tickets')->with('success', 'Ticket created.');
            }
            $returnToLead = $request->filled('return_to_lead') ? (int) $request->get('return_to_lead') : null;
            if ($returnToLead) {
                return redirect()->route('leads.show', $returnToLead)->with('success', 'Ticket created.');
            }
            return redirect()->route('tickets.index')->with('success', 'Ticket created.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create ticket: ' . $e->getMessage());
        }
    }

    /** @return View|RedirectResponse */
    public function show(int $id)
    {
        $ticket = $this->crm->getTicket($id);
        if (!$ticket) {
            return redirect()->route('tickets.index')->with('error', 'Ticket not found.');
        }
        abort_if(!ticket_can_access($id), 403, 'You do not have permission to access this record.');
        $feedback = null;
        if (class_exists(\App\Models\TicketFeedback::class)) {
            try {
                $feedback = \App\Models\TicketFeedback::where('ticket_id', $id)->first();
            } catch (\Throwable $e) {
                // Table may not exist yet
            }
        }
        return view('tickets.show', ['ticket' => $ticket, 'feedback' => $feedback]);
    }

    /** @return View|RedirectResponse */
    public function edit(Request $request, int $id)
    {
        $ticket = $this->crm->getTicket($id);
        if (!$ticket) {
            return redirect()->route('tickets.index')->with('error', 'Ticket not found.');
        }
        abort_if(!ticket_can_access($id), 403, 'You do not have permission to access this record.');
        if ($request->get('refresh')) {
            Cache::forget('ticket_accounts');
            Cache::forget('ticket_create_clients');
        }
        $crm = app(CrmService::class);
        $clients = Cache::remember('ticket_create_clients_' . (crm_owner_filter() ?? 'all'), 120, fn () => $crm->getCustomers(30, 0, null, crm_owner_filter()));
        $contactDisplay = '';
        if ($ticket->contact_id ?? null) {
            $client = $clients->firstWhere('contactid', $ticket->contact_id);
            if (!$client) {
                $client = $crm->getContactById($ticket->contact_id);
                if ($client) {
                    $clients = $clients->prepend($client);
                }
            }
            $contactDisplay = $client ? trim(($client->firstname ?? '') . ' ' . ($client->lastname ?? '')) : '';
        }
        $authUser = \Illuminate\Support\Facades\Auth::guard('vtiger')->user();
        $userRole = ($authUser && $authUser->primary_role) ? $authUser->primary_role->rolename : null;
        try {
            $accounts = $this->sortAccountsForTickets(Cache::remember('ticket_accounts', 300, fn () => $crm->getAccounts(100)));
            if ($accounts->isEmpty()) {
                Cache::forget('ticket_accounts');
                $accounts = $this->getFallbackProductLines();
            } else {
                $accounts = $this->mergeFallbackProductLines($accounts);
            }
        } catch (\Throwable $e) {
            $accounts = $this->getFallbackProductLines();
        }
        $users = Cache::remember('ticket_assign_users', 300, fn () => $crm->getActiveUsers());
        $effectiveOrgId = $ticket->parent_id;
        if (! $effectiveOrgId && preg_match('/Product Line:\s*(.+?)(?:\n|$)/', (string) ($ticket->description ?? ''), $m)) {
            $lineName = trim($m[1]);
            $fallback = $this->getFallbackProductLines()->firstWhere('accountname', $lineName);
            $effectiveOrgId = $fallback ? (string) $fallback->accountid : 'line:' . $lineName;
        }
        return view('tickets.edit', [
            'ticket' => $ticket,
            'clients' => $clients,
            'contactDisplay' => $contactDisplay,
            'accounts' => $accounts,
            'users' => $users,
            'presetOrganizationId' => $effectiveOrgId,
            'canCloseTickets' => $this->sla->canUserCloseThisTicket($id),
        ]);
    }

    /**
     * Show minimal quick-close form (solution only).
     */
    public function showCloseForm(int $ticket): View|RedirectResponse
    {
        $ticketObj = $this->crm->getTicket($ticket);
        if (! $ticketObj) {
            return redirect()->route('tickets.index')->with('error', 'Ticket not found.');
        }
        abort_if(!ticket_can_access($ticket), 403, 'You do not have permission to access this record.');
        if (($ticketObj->status ?? '') === 'Closed') {
            return redirect()->route('tickets.show', $ticket)->with('info', 'Ticket is already closed.');
        }
        if (! $this->sla->canUserCloseThisTicket($ticket)) {
            return redirect()->route('tickets.show', $ticket)->with('error', 'You do not have permission to close tickets.');
        }
        return view('tickets.close', ['ticket' => $ticketObj]);
    }

    /**
     * Quick close a ticket — minimal form: just solution. Fast way to resolve.
     */
    public function quickClose(Request $request, int $ticket): RedirectResponse
    {
        $ticketObj = $this->crm->getTicket($ticket);
        if (! $ticketObj) {
            return redirect()->route('tickets.index')->with('error', 'Ticket not found.');
        }
        abort_if(!ticket_can_access($ticket), 403, 'You do not have permission to access this record.');
        if (! $this->sla->canUserCloseThisTicket($ticket)) {
            return redirect()->route('tickets.show', $ticket)->with('error', 'You do not have permission to close tickets.');
        }
        $solution = trim((string) $request->get('solution', ''));
        if ($solution === '') {
            $solution = 'Closed';
        }
        try {
            $userId = $authUser ? (int) $authUser->id : 1;
            \DB::connection('vtiger')->table('vtiger_troubletickets')->where('ticketid', $ticket)->update(['status' => 'Closed']);
            $existingDesc = \DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $ticket)->value('description') ?? '';
            $fullDesc = trim($existingDesc . "\n\n--- Resolution ---\n" . $solution);
            \DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $ticket)->update([
                'description' => $fullDesc,
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
                'modifiedby' => $userId,
            ]);
            $this->forgetTicketListCaches();
            \App\Events\DashboardStatsUpdated::dispatch();

            $contactId = (int) ($ticketObj->contact_id ?? 0);
            if ($contactId) {
                try {
                    $ticketNo = $ticketObj->ticket_no ?? 'TT' . $ticket;
                    app(\App\Services\TicketNotificationService::class)->sendFeedbackRequestEmail($ticket, $ticketNo, $contactId);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Feedback request email failed', ['error' => $e->getMessage(), 'ticket' => $ticket]);
                }
            }

            return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket closed.');
        } catch (\Throwable $e) {
            return redirect()->route('tickets.show', $ticket)->with('error', 'Failed to close: ' . $e->getMessage());
        }
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $ticket = $this->crm->getTicket($id);
        if (!$ticket) {
            return redirect()->route('tickets.index')->with('error', 'Ticket not found.');
        }
        abort_if(!ticket_can_access($id), 403, 'You do not have permission to access this record.');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'solution' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'priority' => 'nullable|string|max:50',
            'severity' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'contact_id' => 'required|integer',
            'product_id' => 'nullable', // integer for vtiger, or "erp:Product Name" from ERP fallback
            'organization_id' => 'nullable',
            'ticket_source' => 'nullable|string|max:50',
            'assigned_to' => 'nullable|integer',
            'hours' => 'nullable|string|max:20',
            'days' => 'nullable|string|max:20',
        ]);

        $newStatus = $validated['status'] ?? $ticket->status;
        if ($newStatus === 'Closed') {
            if (!$this->sla->canUserCloseThisTicket($id)) {
                return back()->withInput()->with('error', 'You do not have permission to close tickets.');
            }
            $solution = trim($validated['solution'] ?? $ticket->solution ?? '');
            if ($solution === '') {
                return back()->withInput()->with('error', 'Please add a Solution before closing the ticket.');
            }
        }

        try {
            $description = $validated['description'] ?? $ticket->description ?? '';
            $orgId = $validated['organization_id'] ?? null;
            if (is_string($orgId) && str_starts_with($orgId, 'line:')) {
                $lineName = substr($orgId, 5);
                if ($lineName !== '' && ! str_contains($description, 'Product Line: ' . $lineName)) {
                    $description = trim($description) . "\n\nProduct Line: " . $lineName;
                }
            }
            $productId = $ticket->product_id;
            $productIdRaw = $validated['product_id'] ?? null;
            if ($productIdRaw !== null && $productIdRaw !== '') {
                if (is_string($productIdRaw) && str_starts_with((string) $productIdRaw, 'erp:')) {
                    $erpProductName = substr((string) $productIdRaw, 4);
                    $productId = null;
                    if ($erpProductName !== '' && ! str_contains($description, 'Product: ' . $erpProductName)) {
                        $description = trim($description) . "\n\nProduct: " . $erpProductName;
                    }
                } else {
                    $productId = (int) $productIdRaw;
                }
            } elseif (! $request->filled('product_id')) {
                $productId = null;
            }

            $solutionText = trim((string) ($validated['solution'] ?? $ticket->solution ?? ''));
            $fullDescription = $solutionText !== '' ? trim($description . "\n\n--- Resolution ---\n" . $solutionText) : $description;

            \DB::connection('vtiger')->table('vtiger_troubletickets')->where('ticketid', $id)->update([
                'title' => $validated['title'],
                'status' => $validated['status'] ?? $ticket->status,
                'priority' => $validated['priority'] ?? $ticket->priority,
                'severity' => $validated['severity'] ?? $ticket->severity,
                'category' => $validated['category'] ?? $ticket->category,
                'contact_id' => $validated['contact_id'],
                'product_id' => $productId,
                'parent_id' => $this->resolveOrganizationId($validated['organization_id'] ?? null),
                'hours' => $validated['hours'] ?? $ticket->hours,
                'days' => $validated['days'] ?? $ticket->days,
            ]);
            $authUser = \Illuminate\Support\Facades\Auth::guard('vtiger')->user();
            $userId = $authUser ? (int) $authUser->id : 1;
            $crmentityUpdates = ['description' => $fullDescription, 'modifiedtime' => now()->format('Y-m-d H:i:s')];
            if ($newStatus === 'Closed') {
                $crmentityUpdates['modifiedby'] = $userId;
            }
            if (!empty($validated['ticket_source'] ?? '')) {
                $crmentityUpdates['source'] = $validated['ticket_source'];
            }
            $newOwnerId = null;
            if (!empty($validated['assigned_to'] ?? '')) {
                $newOwnerId = (int) $validated['assigned_to'];
                $crmentityUpdates['smownerid'] = $newOwnerId;
            }
            \DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $id)->update($crmentityUpdates);
            $this->forgetTicketListCaches();
            \App\Events\DashboardStatsUpdated::dispatch();

            // Notify new assignee when ticket is reassigned
            if ($newOwnerId !== null && (int) ($ticket->smownerid ?? 0) !== $newOwnerId) {
                try {
                    app(\App\Services\TicketNotificationService::class)->sendTicketAssignedNotification(
                        $id,
                        $ticket->ticket_no ?? 'TT' . $id,
                        $validated['title'],
                        $newOwnerId
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Ticket reassignment notification failed', ['error' => $e->getMessage(), 'ticket' => $id]);
                }
            }

            if ($newStatus === 'Closed') {
                $contactId = (int) ($validated['contact_id'] ?? $ticket->contact_id ?? 0);
                if ($contactId) {
                    try {
                        $ticketNo = $ticket->ticket_no ?? 'TT' . $id;
                        app(\App\Services\TicketNotificationService::class)->sendFeedbackRequestEmail($id, $ticketNo, $contactId);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Feedback request email failed', ['error' => $e->getMessage(), 'ticket' => $id]);
                    }
                }
            }

            return redirect()->route('tickets.show', $id)->with('success', 'Ticket updated.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to update: ' . $e->getMessage());
        }
    }

    /**
     * Quick reassign ticket (right-click menu). Updates smownerid only.
     */
    public function reassign(Request $request, int $ticket): RedirectResponse|JsonResponse
    {
        $ticketObj = $this->crm->getTicket($ticket);
        if (! $ticketObj) {
            return $request->wantsJson()
                ? response()->json(['error' => 'Ticket not found.'], 404)
                : redirect()->route('tickets.index')->with('error', 'Ticket not found.');
        }
        $assignedTo = (int) $request->get('assigned_to', 0);
        if ($assignedTo <= 0) {
            return $request->wantsJson()
                ? response()->json(['error' => 'Invalid assignee.'], 422)
                : back()->with('error', 'Please select a user to assign.');
        }
        try {
            \DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $ticket)->update([
                'smownerid' => $assignedTo,
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
                'modifiedby' => (int) (\Illuminate\Support\Facades\Auth::guard('vtiger')->id() ?? \Illuminate\Support\Facades\Auth::id() ?? 1),
            ]);
            $this->forgetTicketListCaches();
            \App\Events\DashboardStatsUpdated::dispatch();
            try {
                app(\App\Services\TicketNotificationService::class)->sendTicketAssignedNotification(
                    $ticket,
                    $ticketObj->ticket_no ?? 'TT' . $ticket,
                    $ticketObj->title ?? '',
                    $assignedTo
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Ticket reassignment notification failed', ['error' => $e->getMessage()]);
            }
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Ticket reassigned.']);
            }
            return back()->with('success', 'Ticket reassigned.');
        } catch (\Throwable $e) {
            return $request->wantsJson()
                ? response()->json(['error' => $e->getMessage()], 500)
                : back()->with('error', 'Failed to reassign: ' . $e->getMessage());
        }
    }

    /**
     * Resolve organization_id to vtiger parent_id. Returns null for "line:X" (Product Line fallback).
     */
    private function resolveOrganizationId($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value) && str_starts_with($value, 'line:')) {
            return null;
        }
        return (int) $value;
    }

    /**
     * Fetch ERP products for ticket dropdown when vtiger has none.
     * Uses ERP_CLIENTS_HTTP_URL when set (regardless of CLIENTS_VIEW_SOURCE).
     */
    private function fetchErpProductsForTickets(): Collection
    {
        $url = rtrim((string) config('erp.clients_http_url'), '/');
        if ($url === '') {
            return collect();
        }
        try {
            $sep = strpos($url, '?') !== false ? '&' : '?';
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url . $sep . 'products=1');
            if (! $response->successful()) {
                $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url . '/products');
            }
            if (! $response->successful()) {
                return collect();
            }
            $body = $response->json();
            $names = $body['products'] ?? [];
            if (! is_array($names) || empty($names)) {
                return collect();
            }
            return collect($names)->map(fn ($n) => (object) ['productid' => 'erp:' . $n, 'productname' => $n]);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Merge fallback product lines (Group Life, Individual Life, etc.) into accounts when vtiger has accounts.
     */
    private function mergeFallbackProductLines(Collection $accounts): Collection
    {
        $existingIds = $accounts->pluck('accountid')->map(fn ($v) => (string) $v)->toArray();
        foreach ($this->getFallbackProductLines() as $line) {
            $id = (string) $line->accountid;
            if (! in_array($id, $existingIds, true)) {
                $accounts = $accounts->push($line);
                $existingIds[] = $id;
            }
        }
        return $accounts;
    }

    /**
     * Fallback Product Line options when vtiger has no accounts (e.g. Credit Life, Group Life).
     */
    private function getFallbackProductLines(): Collection
    {
        $lines = config('tickets.organization_sort', []);
        if (empty($lines)) {
            $lines = ['Individual Life', 'Group Life', 'Credit Life', 'Mortgage', 'Group Last Expense'];
        }
        return collect($lines)->map(fn ($n, $i) => (object) ['accountid' => 'line:' . $n, 'accountname' => $n]);
    }

    /**
     * Sort accounts for Product Line dropdown: preferred order first, then alphabetical.
     */
    private function sortAccountsForTickets(Collection $accounts): Collection
    {
        if ($accounts->isEmpty()) {
            return $accounts;
        }
        $first = $accounts->first();
        if (is_string($first->accountid ?? null) && str_starts_with((string) $first->accountid, 'line:')) {
            return $accounts;
        }
        $order = config('tickets.organization_sort', []);
        if (empty($order)) {
            return $accounts->sortBy('accountname', SORT_NATURAL | SORT_FLAG_CASE)->values();
        }
        $orderMap = array_flip(array_map('strtoupper', $order));
        return $accounts->sort(function ($a, $b) use ($orderMap) {
            $nameA = strtoupper($a->accountname ?? '');
            $nameB = strtoupper($b->accountname ?? '');
            $posA = $orderMap[$nameA] ?? 9999;
            $posB = $orderMap[$nameB] ?? 9999;
            if ($posA !== $posB) {
                return $posA <=> $posB;
            }
            return strcasecmp($nameA, $nameB);
        })->values();
    }

    /**
     * AJAX contact search for ticket create/edit forms (lazy loading).
     */
    public function searchContacts(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        $limit = min(50, max(5, (int) $request->get('limit', 20)));
        if (strlen($q) < 1) {
            return response()->json([]);
        }
        $cacheKey = 'ticket_search_contacts:' . md5($q . ':' . $limit);
        $data = Cache::remember($cacheKey, 30, function () use ($q, $limit) {
            $seen = [];
            $results = [];

            // CRM contacts
            $customers = $this->crm->getCustomers((int) ceil($limit / 2), 0, $q, crm_owner_filter());
            foreach ($customers as $c) {
                $id = (int) $c->contactid;
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $results[] = [
                        'id' => $id,
                        'name' => trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) ?: 'Contact #' . $id,
                    ];
                }
            }

            // ERP clients (Group Life + Individual) when configured
            $source = config('erp.clients_view_source', 'crm');
            $erpConfigured = $source === 'erp_http' ? ! empty(config('erp.clients_http_url')) : ($source === 'erp_sync');
            if ($this->erp && $erpConfigured) {
                try {
                    $erpResult = $this->erp->searchClients($q, (int) ceil($limit / 2));
                    $erpData = $erpResult['data'] ?? [];
                    foreach (is_iterable($erpData) ? $erpData : [] as $r) {
                        $row = is_array($r) ? $r : (array) $r;
                        $policy = trim((string) ($row['policy_number'] ?? $row['policy_no'] ?? ''));
                        if (! $policy) {
                            continue;
                        }
                        $contact = $this->crm->findContactByPolicyNumber($policy);
                        $contactId = $contact ? (int) $contact->contactid : $this->crm->createContactFromErpClient($row);
                        if ($contactId && ! isset($seen[$contactId])) {
                            $seen[$contactId] = true;
                            $name = trim((string) ($row['life_assur'] ?? $row['client_name'] ?? $row['life_assured'] ?? ''));
                            $label = $name ? "{$name} ({$policy})" : $policy;
                            $lifeSystem = $row['life_system'] ?? ($this->erp ? $this->erp->getLifeSystemFromProduct($row['product'] ?? null) : null);
                            if ($lifeSystem === 'group') {
                                $label .= ' — Group Life';
                            }
                            $results[] = ['id' => $contactId, 'name' => $label];
                        }
                    }
                } catch (\Throwable $e) {
                    // Continue with CRM-only results
                }
            }

            return array_slice(array_values($results), 0, $limit);
        });
        return response()->json($data);
    }

    /**
     * AJAX product search for ticket create/edit (Product Name dropdown).
     * Uses vtiger products first; falls back to ERP products (PROD_DESC) when vtiger is empty.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        $limit = min(100, max(10, (int) $request->get('limit', 50)));
        $products = $this->crm->getProducts($limit, $q ?: null);
        $data = $products->map(fn ($p) => [
            'value' => (string) $p->productid,
            'text' => $p->productname ?? 'Product #' . $p->productid,
        ])->values()->all();

        // Fallback to ERP products when vtiger has none (use ERP when URL is configured)
        if (empty($data) && config('erp.clients_http_url')) {
            try {
                $url = rtrim(config('erp.clients_http_url'), '/');
                $sep = strpos($url, '?') !== false ? '&' : '?';
                $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url . $sep . 'products=1');
                if (! $response->successful()) {
                    $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url . '/products');
                }
                if ($response->successful()) {
                    $body = $response->json();
                    $names = $body['products'] ?? [];
                    if (is_array($names) && ! empty($names)) {
                        $term = $q ? strtoupper($q) : '';
                        $filtered = $term ? array_filter($names, fn ($n) => str_contains(strtoupper((string) $n), $term)) : $names;
                        $filtered = array_slice(array_values($filtered), 0, $limit);
                        $data = array_map(fn ($n) => ['value' => 'erp:' . $n, 'text' => $n], $filtered);
                    }
                }
            } catch (\Throwable $e) {
                // ignore; return empty
            }
        }
        return response()->json($data);
    }

    /**
     * AJAX account/search for Product Line dropdown.
     */
    public function searchAccounts(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        $limit = min(100, max(10, (int) $request->get('limit', 50)));
        $accounts = $this->sortAccountsForTickets(
            $this->crm->getAccounts($limit)
        );
        if (! $accounts->isEmpty()) {
            $accounts = $this->mergeFallbackProductLines($accounts);
        } else {
            $accounts = $this->getFallbackProductLines();
        }
        $data = $accounts->map(fn ($a) => [
            'value' => (string) $a->accountid,
            'text' => $a->accountname ?? 'Account #' . $a->accountid,
        ])->values()->all();

        $term = $q ? strtoupper($q) : '';
        if ($term) {
            $data = array_values(array_filter($data, fn ($d) => str_contains(strtoupper((string) ($d['text'] ?? '')), $term)));
        }
        return response()->json($data);
    }

    public function inactivate(int $ticket): RedirectResponse
    {
        $ticketObj = $this->crm->getTicket($ticket);
        if (! $ticketObj) {
            return redirect()->route('tickets.index')->with('error', 'Ticket not found.');
        }
        abort_if(!ticket_can_access($ticket), 403, 'You do not have permission to access this record.');
        $inactiveStatus = config('tickets.inactive_status', 'Inactive');
        if (($ticketObj->status ?? '') === $inactiveStatus) {
            return back()->with('info', 'Ticket is already inactive.');
        }
        try {
            \DB::connection('vtiger')->table('vtiger_troubletickets')->where('ticketid', $ticket)->update(['status' => $inactiveStatus]);
            $this->forgetTicketListCaches();
            \App\Events\DashboardStatsUpdated::dispatch();
            return redirect()->route('tickets.index')->with('success', 'Ticket inactivated.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to inactivate: ' . $e->getMessage());
        }
    }

    private function forgetTicketListCaches(): void
    {
        Cache::forget('geminia_ticket_counts_by_status');
        Cache::forget('geminia_tickets_count');
        Cache::forget('tickets_list_default');
        foreach (['Open', 'In_Progress', 'Wait_For_Response', 'Closed', 'Inactive', 'Unassigned'] as $slug) {
            Cache::forget('tickets_list_' . $slug);
        }
        Cache::forget('geminia_dashboard_stats');
    }
}
