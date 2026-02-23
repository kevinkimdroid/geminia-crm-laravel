<?php

namespace App\Http\Controllers;

use App\Services\CrmService;
use App\Services\ErpClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Customer service flow: search ERP clients, view policies, create ticket in one go.
 */
class ServeClientController extends Controller
{
    public function __construct(
        private CrmService $crm,
        private ErpClientService $erp
    ) {}

    /**
     * Serve Client page - search UI. Supports ?search= for server-side results (works without JS).
     */
    public function index(Request $request): View|RedirectResponse
    {
        $source = config('erp.clients_view_source', 'crm');
        $canServe = in_array($source, ['erp_sync', 'erp_http']) || $this->erp->isConfigured();
        if (!$canServe || ($source === 'erp_http' && empty(config('erp.clients_http_url')))) {
            return redirect()->route('tickets.index')
                ->with('error', 'Client search is not configured. Set CLIENTS_VIEW_SOURCE (erp_sync/erp_http) or ERP credentials.');
        }

        $searchTerm = trim($request->get('search', ''));
        $erpClients = [];
        $crmContacts = [];
        $searchError = null;

        if (strlen($searchTerm) >= 2) {
            $cacheKey = 'serve_client_index:' . md5($searchTerm);
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $erpClients = $cached['erp'] ?? [];
                $crmContacts = $cached['crm'] ?? [];
                $searchError = $cached['error'] ?? null;
            } else {
                $erpResult = $this->erp->searchClients($searchTerm, 20);
                $erpClients = $erpResult['data'] ?? [];
                $searchError = $erpResult['error'] ?? null;

                try {
                    $customers = $this->crm->getCustomers(20, 0, $searchTerm);
                    foreach ($customers as $c) {
                        $crmContacts[] = [
                            'contactid' => $c->contactid,
                            'name' => trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')),
                            'email' => $c->email ?? '',
                            'phone' => $c->mobile ?? $c->phone ?? '',
                        ];
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
                Cache::put($cacheKey, ['erp' => $erpClients, 'crm' => $crmContacts, 'error' => $searchError], 90);
            }
        }

        return view('support.serve-client', [
            'initialSearch' => $searchTerm,
            'initialErp' => $erpClients,
            'initialCrm' => $crmContacts,
            'initialError' => $searchError,
        ]);
    }

    /**
     * Search ERP clients and/or CRM contacts (AJAX).
     * Optional ?source=erp|crm to fetch only one (allows parallel frontend requests).
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim($request->get('q', ''));
        $source = $request->get('source'); // 'erp', 'crm', or null = both
        if (strlen($term) < 2) {
            return response()->json([
                'success' => true,
                'erp' => [],
                'crm' => [],
                'message' => 'Type at least 2 characters to search.',
            ]);
        }

        $erpClients = [];
        $crmContacts = [];
        $erpError = null;

        $fetchErp = $source === null || $source === 'erp';
        $fetchCrm = $source === null || $source === 'crm';

        $cacheKey = 'serve_client_search:' . md5($term . ':' . $source);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        if ($fetchErp) {
            $erpResult = $this->erp->searchClients($term, 20);
            $erpClients = $erpResult['data'] ?? [];
            $erpError = $erpResult['error'] ?? null;
        }

        if ($fetchCrm) {
            try {
                $customers = $this->crm->getCustomers(20, 0, $term);
                foreach ($customers as $c) {
                    $crmContacts[] = [
                        'contactid' => $c->contactid,
                        'name' => trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')),
                        'email' => $c->email ?? '',
                        'phone' => $c->mobile ?? $c->phone ?? '',
                    ];
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $payload = [
            'success' => ! $erpError,
            'erp' => $erpClients,
            'crm' => $crmContacts,
            'error' => $erpError,
        ];
        Cache::put($cacheKey, $payload, 90);

        return response()->json($payload);
    }

    /**
     * Direct create ticket from policy (one-click from client detail).
     * GET /support/clients/create-ticket?policy=XXX
     */
    public function createTicketFromPolicy(Request $request): RedirectResponse
    {
        $policy = $request->filled('policy') ? trim($request->get('policy')) : null;
        if (!$policy) {
            return redirect()->route('support.serve-client')->with('error', 'Policy number required.');
        }

        $clientData = $this->erp->getPolicyDetails($policy);
        if (!$clientData) {
            return redirect()->route('support.serve-client', ['search' => $policy])
                ->with('error', 'Client not found. Try searching in Serve Client.');
        }

        $erpClient = is_array($clientData) ? $clientData : (array) $clientData;
        $policyNumber = $erpClient['policy_no'] ?? $erpClient['policy_number'] ?? $policy;
        $phone = $erpClient['mobile'] ?? $erpClient['phone'] ?? '';
        $email = $erpClient['email'] ?? $erpClient['email_adr'] ?? '';
        $erpClientName = $this->normalizeNameForMatch($erpClient['name'] ?? $erpClient['client_name'] ?? $erpClient['life_assur'] ?? $erpClient['life_assured'] ?? '');

        $contact = $this->crm->findContactByPolicyNumber($policyNumber);
        if ($contact && ! $this->contactNameMatchesErp($contact, $erpClientName)) {
            $contact = null;
        }
        if (! $contact) {
            $contact = $this->crm->findContactByPhoneOrEmail($phone, $email);
            if ($contact && ! $this->contactNameMatchesErp($contact, $erpClientName)) {
                $contact = null;
            }
        }
        $contactId = $contact?->contactid ?? $this->crm->createContactFromErpClient($erpClient);

        if (!$contactId) {
            return redirect()->route('support.serve-client', ['search' => $policy])
                ->with('error', 'Could not find or create contact. Try Serve Client.');
        }

        $clientName = trim($erpClient['name'] ?? $erpClient['client_name'] ?? $erpClient['life_assur'] ?? $erpClient['life_assured'] ?? '');

        return redirect()->route('tickets.create', [
            'contact_id' => $contactId,
            'policy' => $policyNumber,
            'client_name' => $clientName,
            'from' => 'serve-client',
        ])->with('success', 'Client selected. Complete the ticket details below.');
    }

    /**
     * Get or create CRM contact for the selected client, then redirect to create ticket.
     */
    public function createTicket(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source' => 'required|in:erp,crm',
            'contact_id' => 'required_if:source,crm|nullable|integer',
            'erp_data' => 'required_if:source,erp|nullable|string',
        ]);

        $contactId = null;

        if ($validated['source'] === 'crm' && !empty($validated['contact_id'])) {
            $contactId = (int) $validated['contact_id'];
        }

        if ($validated['source'] === 'erp' && !empty($validated['erp_data'])) {
            $erpClient = json_decode($validated['erp_data'], true);
            if (!is_array($erpClient)) {
                return back()->with('error', 'Invalid client data.');
            }

            $policyNumber = $erpClient['policy_no'] ?? $erpClient['policy_number'] ?? $erpClient['POLICY_NO'] ?? $erpClient['POLICY_NUMBER'] ?? '';
            $phone = $erpClient['mobile'] ?? $erpClient['phone'] ?? '';
            $email = $erpClient['email'] ?? '';

            $erpClientName = $this->normalizeNameForMatch($erpClient['name'] ?? $erpClient['client_name'] ?? $erpClient['life_assur'] ?? $erpClient['life_assured'] ?? '');
            $contact = $this->crm->findContactByPolicyNumber($policyNumber);
            if ($contact && ! $this->contactNameMatchesErp($contact, $erpClientName)) {
                $contact = null;
            }
            if (! $contact) {
                $contact = $this->crm->findContactByPhoneOrEmail($phone, $email);
                if ($contact && ! $this->contactNameMatchesErp($contact, $erpClientName)) {
                    $contact = null;
                }
            }
            if ($contact) {
                $contactId = $contact->contactid;
            } else {
                $contactId = $this->crm->createContactFromErpClient($erpClient);
            }
        }

        if (!$contactId) {
            return back()->with('error', 'Could not find or create contact for this client.');
        }

        $params = ['contact_id' => $contactId, 'from' => 'serve-client'];
        if ($validated['source'] === 'erp' && !empty($erpClient['policy_no'] ?? $erpClient['policy_number'] ?? '')) {
            $params['policy'] = trim($erpClient['policy_no'] ?? $erpClient['policy_number'] ?? '');
        }
        $clientName = $validated['source'] === 'erp'
            ? trim($erpClient['name'] ?? $erpClient['client_name'] ?? $erpClient['life_assur'] ?? $erpClient['life_assured'] ?? '')
            : null;
        if ($clientName) {
            $params['client_name'] = $clientName;
        }

        return redirect()->route('tickets.create', $params)
            ->with('success', 'Client selected. Complete the ticket details below.');
    }

    /**
     * Normalize name for matching (lowercase, collapse spaces, trim).
     */
    private function normalizeNameForMatch(string $name): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($name ?? '')));
    }

    /**
     * Check if CRM contact name matches ERP client name. Prevents wrong client from policy/phone lookup.
     */
    private function contactNameMatchesErp(object $contact, string $erpClientName): bool
    {
        if ($erpClientName === '') {
            return true;
        }
        $contactName = $this->normalizeNameForMatch(
            trim(($contact->firstname ?? '') . ' ' . ($contact->lastname ?? ''))
        );
        if ($contactName === '') {
            return true;
        }
        return $contactName === $erpClientName
            || str_contains($erpClientName, $contactName)
            || str_contains($contactName, $erpClientName);
    }
}
