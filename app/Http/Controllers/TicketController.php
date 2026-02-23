<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\CrmService;
use App\Services\TicketAutomationService;
use App\Services\TicketSlaService;
use Illuminate\Http\RedirectResponse;
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
    /** @var TicketAutomationService */
    protected $automation;
    /** @var TicketSlaService */
    protected $sla;

    public function __construct(CrmService $crm, TicketAutomationService $automation, TicketSlaService $sla)
    {
        $this->crm = $crm;
        $this->automation = $automation;
        $this->sla = $sla;
    }

    public function index(Request $request): View
    {
        $status = $request->get('list');
        $search = $request->get('search');
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $isDefaultView = (!$status || trim((string) $status) === '') && (!$search || trim((string) $search) === '') && $page === 1;
        $isStatusPage1 = $status && trim((string) $status) !== '' && (!$search || trim((string) $search) === '') && $page === 1;
        $statusSlug = $status ? str_replace(' ', '_', trim((string) $status)) : '';
        $cacheKey = $isDefaultView ? 'tickets_list_default' : ($isStatusPage1 ? 'tickets_list_' . $statusSlug : null);

        if ($cacheKey) {
            $ttl = $isDefaultView ? 45 : 30;
            $cached = Cache::remember($cacheKey, $ttl, function () use ($perPage, $status, $search) {
                $items = $this->crm->getTickets($perPage, 0, $status, $search);
                $count = $this->crm->getTicketsCount($status, $search);
                return ['tickets' => $items, 'total' => $count];
            });
            $tickets = $cached['tickets'];
            $total = $cached['total'];
        } else {
            $tickets = $this->crm->getTickets($perPage, $offset, $status, $search);
            $total = $this->crm->getTicketsCount($status, $search);
        }

        $ticketCounts = $this->crm->getTicketCountsByStatus();

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
        ]);
    }

    public function create(Request $request): View
    {
        $crm = app(CrmService::class);
        $contactId = $request->filled('contact_id') ? (int) $request->get('contact_id') : null;
        $fromServeClient = $request->get('from') === 'serve-client';
        $clientNameParam = $request->filled('client_name') ? trim($request->get('client_name')) : null;

        if ($fromServeClient && $contactId) {
            $clients = collect([$crm->getContactById($contactId)])->filter();
        } else {
            $clients = Cache::remember('ticket_create_clients', 90, fn () => $crm->getCustomers(50, 0));
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

        $products = Cache::remember('ticket_products', 300, fn () => $crm->getProducts(100));
        if ($products->isEmpty()) {
            Cache::forget('ticket_products');
        }
        $accounts = $this->sortAccountsForTickets(Cache::remember('ticket_accounts', 300, fn () => $crm->getAccounts(100)));
        $users = Cache::remember('ticket_assign_users', 300, fn () => $crm->getActiveUsers());

        return view('tickets.create', [
            'clients' => $clients,
            'products' => $products,
            'accounts' => $accounts,
            'users' => $users,
            'presetContactId' => $contactId,
            'presetContactDisplay' => $contactDisplay ?: $clientNameParam,
            'presetPolicy' => $presetPolicy,
            'fromServeClient' => $fromServeClient,
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
            'product_id' => 'nullable|integer',
            'organization_id' => 'nullable|integer',
            'hours' => 'nullable|string|max:20',
            'days' => 'nullable|string|max:20',
            'ticket_source' => 'nullable|string|max:50',
            'assigned_to' => 'nullable|integer',
            'policy_number' => 'nullable|string|max:100',
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
            if (! empty($validated['policy_number'] ?? '')) {
                $description = trim($description) . "\n\nRelated policy: " . trim($validated['policy_number']);
            }

            $id = \DB::connection('vtiger')->table('vtiger_troubletickets')->insertGetId([
                'title' => $validated['title'],
                'description' => $description,
                'solution' => $validated['solution'] ?? '',
                'status' => $validated['status'] ?? 'Open',
                'priority' => $validated['priority'] ?? 'Normal',
                'severity' => $validated['severity'] ?? null,
                'category' => $validated['category'] ?? null,
                'contact_id' => $validated['contact_id'],
                'product_id' => $validated['product_id'] ?? null,
                'parent_id' => $validated['organization_id'] ?? null,
                'hours' => $validated['hours'] ?? null,
                'days' => $validated['days'] ?? null,
            ]);

            $existing = \DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $id)->exists();
            if (!$existing) {
                \DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                    'crmid' => $id,
                    'smcreatorid' => $userId,
                    'smownerid' => $ownerId,
                    'modifiedby' => $userId,
                    'setype' => 'HelpDesk',
                    'description' => $description,
                    'createdtime' => now()->format('Y-m-d H:i:s'),
                    'modifiedtime' => now()->format('Y-m-d H:i:s'),
                    'viewedtime' => null,
                    'status' => 1,
                    'version' => 0,
                    'presence' => 1,
                    'deleted' => 0,
                    'smgroupid' => 0,
                    'source' => $validated['ticket_source'] ?? 'CRM',
                    'label' => $validated['title'],
                ]);
            }

            $this->forgetTicketListCaches();
            \App\Events\DashboardStatsUpdated::dispatch();
            if ($request->filled('return_to_serve_client')) {
                return redirect()->route('support.serve-client')->with('success', 'Ticket created. You can create another or search for a different client.');
            }
            $returnToContact = $request->filled('return_to_contact') ? (int) $request->get('return_to_contact') : null;
            if ($returnToContact) {
                return redirect()->to(route('contacts.show', $returnToContact) . '?tab=tickets')->with('success', 'Ticket created.');
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
        return view('tickets.show', ['ticket' => $ticket]);
    }

    /** @return View|RedirectResponse */
    public function edit(int $id)
    {
        $ticket = $this->crm->getTicket($id);
        if (!$ticket) {
            return redirect()->route('tickets.index')->with('error', 'Ticket not found.');
        }
        $crm = app(CrmService::class);
        $clients = Cache::remember('ticket_create_clients', 90, fn () => $crm->getCustomers(50, 0));
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
        $products = Cache::remember('ticket_products', 300, fn () => $crm->getProducts(100));
        if ($products->isEmpty()) {
            Cache::forget('ticket_products');
        }
        $accounts = $this->sortAccountsForTickets(Cache::remember('ticket_accounts', 300, fn () => $crm->getAccounts(100)));
        $users = Cache::remember('ticket_assign_users', 300, fn () => $crm->getActiveUsers());
        return view('tickets.edit', [
            'ticket' => $ticket,
            'clients' => $clients,
            'contactDisplay' => $contactDisplay,
            'products' => $products,
            'accounts' => $accounts,
            'users' => $users,
            'canCloseTickets' => $this->sla->canUserCloseTickets($userRole),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $ticket = $this->crm->getTicket($id);
        if (!$ticket) {
            return redirect()->route('tickets.index')->with('error', 'Ticket not found.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'solution' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'priority' => 'nullable|string|max:50',
            'severity' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'contact_id' => 'required|integer',
            'product_id' => 'nullable|integer',
            'organization_id' => 'nullable|integer',
            'ticket_source' => 'nullable|string|max:50',
            'assigned_to' => 'nullable|integer',
            'hours' => 'nullable|string|max:20',
            'days' => 'nullable|string|max:20',
        ]);

        $newStatus = $validated['status'] ?? $ticket->status;
        if ($newStatus === 'Closed') {
            $authUser = \Illuminate\Support\Facades\Auth::guard('vtiger')->user();
            $userRole = ($authUser && $authUser->primary_role) ? $authUser->primary_role->rolename : null;
            if (!$this->sla->canUserCloseTickets($userRole)) {
                return back()->withInput()->with('error', 'You do not have permission to close tickets.');
            }
            $solution = trim($validated['solution'] ?? $ticket->solution ?? '');
            if ($solution === '') {
                return back()->withInput()->with('error', 'Please add a Solution before closing the ticket.');
            }
        }

        try {
            Ticket::on('vtiger')->where('ticketid', $id)->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? '',
                'solution' => $validated['solution'] ?? $ticket->solution ?? '',
                'status' => $validated['status'] ?? $ticket->status,
                'priority' => $validated['priority'] ?? $ticket->priority,
                'severity' => $validated['severity'] ?? $ticket->severity,
                'category' => $validated['category'] ?? $ticket->category,
                'contact_id' => $validated['contact_id'],
                'product_id' => $request->filled('product_id') ? (int) $validated['product_id'] : null,
                'parent_id' => $request->filled('organization_id') ? (int) $validated['organization_id'] : null,
                'hours' => $validated['hours'] ?? $ticket->hours,
                'days' => $validated['days'] ?? $ticket->days,
            ]);
            $crmentityUpdates = [];
            if (!empty($validated['ticket_source'] ?? '')) {
                $crmentityUpdates['source'] = $validated['ticket_source'];
            }
            if (!empty($validated['assigned_to'] ?? '')) {
                $crmentityUpdates['smownerid'] = (int) $validated['assigned_to'];
            }
            if (!empty($crmentityUpdates)) {
                \DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $id)->update($crmentityUpdates);
            }
            $this->forgetTicketListCaches();
            \App\Events\DashboardStatsUpdated::dispatch();
            return redirect()->route('tickets.show', $id)->with('success', 'Ticket updated.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to update: ' . $e->getMessage());
        }
    }

    /**
     * Sort accounts for Product Line dropdown: preferred order first, then alphabetical.
     */
    private function sortAccountsForTickets(Collection $accounts): Collection
    {
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
        if (strlen($q) < 2) {
            return response()->json([]);
        }
        $cacheKey = 'ticket_search_contacts:' . md5($q . ':' . $limit);
        $data = Cache::remember($cacheKey, 30, function () use ($q, $limit) {
            $customers = $this->crm->getCustomers($limit, 0, $q);
            return $customers->map(fn ($c) => [
                'id' => $c->contactid,
                'name' => trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) ?: 'Contact #' . $c->contactid,
            ])->values()->all();
        });
        return response()->json($data);
    }

    /**
     * AJAX product search for ticket create/edit (Product Name dropdown).
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
        return response()->json($data);
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            \DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $id)->update(['deleted' => 1]);
            $this->forgetTicketListCaches();
            \App\Events\DashboardStatsUpdated::dispatch();
            return redirect()->route('tickets.index')->with('success', 'Ticket deleted.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }

    private function forgetTicketListCaches(): void
    {
        Cache::forget('geminia_ticket_counts_by_status');
        Cache::forget('geminia_tickets_count');
        Cache::forget('tickets_list_default');
        foreach (['Open', 'In_Progress', 'Wait_For_Response', 'Closed', 'Unassigned'] as $slug) {
            Cache::forget('tickets_list_' . $slug);
        }
        Cache::forget('geminia_dashboard_stats');
    }
}
