<?php

namespace App\Http\Controllers;

use App\Services\CrmService;
use App\Services\ErpClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        private CrmService $crm,
        private ErpClientService $erp
    ) {}

    /**
     * Show client details by policy number (ERP clients) or redirect to contact (CRM).
     */
    public function show(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $policy = $request->get('policy', '');
        $policy = trim($policy);
        $fromServeClient = $request->get('from') === 'serve-client';
        if ($policy === '') {
            return redirect()->route('support.customers')->with('error', 'Policy number required.');
        }

        $source = config('erp.clients_view_source', 'crm');
        $useErp = in_array($source, ['erp', 'erp_sync', 'erp_http']);

        if ($useErp) {
            $client = $this->erp->getPolicyDetails($policy);
        if (!$client) {
            // Fallback: try CRM contact by policy (ERP/API may be down with ORA-03113)
            $contact = $this->crm->findContactByPolicyNumber($policy);
            if ($contact) {
                return redirect()->route('contacts.show', $contact->contactid)
                    ->with('info', 'ERP unavailable. Showing CRM contact for policy ' . $policy);
            }
            $redirect = $fromServeClient
                ? redirect()->route('support.serve-client', ['search' => $policy])
                : redirect()->route('support.customers');
            return $redirect->with('error', 'Client not found for policy ' . $policy . '. (Ensure erp-clients-api is running and Oracle is reachable.)');
        }

            $contact = $this->crm->findContactByPolicyNumber($policy);
            $tickets = $contact ? $this->crm->getTicketsForContact($contact->contactid, 50) : collect();

            // Always use requested policy for display (API/Oracle may return POLICY_NO vs POLICY_NUMBER mismatch)
            $client['policy_no'] = $policy;
            $client['policy_number'] = $policy;

            return view('support.client-show', [
                'client' => (object) $client,
                'tickets' => $tickets,
                'contact' => $contact,
                'policy' => $policy,
                'fromServeClient' => $fromServeClient,
            ]);
        }

        $contact = $this->crm->findContactByPolicyNumber($policy);
        if ($contact) {
            return redirect()->route('contacts.show', $contact->contactid);
        }

        return redirect()->route('support.customers')->with('error', 'Client not found.');
    }

    /**
     * Debug: show raw ERP API response for a policy. Use ?policy=090807694
     */
    public function debugApi(Request $request)
    {
        $policy = trim((string) $request->get('policy', ''));
        if ($policy === '') {
            return response()->json(['error' => 'Add ?policy=090807694 to see raw API data for that policy'], 400);
        }
        $url = config('erp.clients_http_url');
        if (empty($url)) {
            return response()->json(['error' => 'ERP_CLIENTS_HTTP_URL not configured'], 500);
        }
        $url = rtrim($url, '/');
        $sep = str_contains($url, '?') ? '&' : '?';
        $response = \Illuminate\Support\Facades\Http::timeout(15)->get($url . $sep . 'policy=' . urlencode($policy) . '&limit=1');
        $body = $response->json();
        $parsed = parse_url($url);
        $base = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? 'localhost') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
        $columnsResp = \Illuminate\Support\Facades\Http::timeout(5)->get($base . '/columns');
        $columnsData = $columnsResp->successful() ? $columnsResp->json() : null;

        return response()->json([
            'policy' => $policy,
            'api_url' => $url,
            'api_status' => $response->status(),
            'data' => $body['data'] ?? $body['clients'] ?? [],
            'view_columns' => $columnsData['columns'] ?? null,
            'raw' => $body,
        ]);
    }

    public function index(Request $request): View
    {
        $search = $request->get('search');
        $page = max(1, (int) $request->get('page', 1));
        $source = config('erp.clients_view_source', 'crm');
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $clientsError = null;
        $useErp = in_array($source, ['erp', 'erp_sync', 'erp_http'])
            && (in_array($source, ['erp_sync', 'erp_http']) || $this->erp->isConfigured())
            && ($source !== 'erp_http' || ! empty(config('erp.clients_http_url')));

        // Lazy-load: skip slow ERP call on initial page load for fast first paint
        $lazyLoad = $useErp && in_array($source, ['erp_sync', 'erp_http']);

        if ($useErp && ! $lazyLoad) {
            $cacheKey = 'clients_list_' . md5($source . $perPage . $offset . ($search ?? ''));
            $ttl = (!$search && $page === 1) ? 180 : 120; // 3 min first page, 2 min for search
            $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, $ttl, fn () => $this->erp->getClientsForListView($perPage, $offset, $search));
            $customers = $result['data'];
            $total = $result['total'];
            $clientsError = $result['error'] ?? null;
            if ($clientsError && $customers->isEmpty()) {
                $customers = $this->crm->getCustomers($perPage, $offset, $search);
                $total = $this->crm->getCustomersCount($search);
                $clientsError = 'Oracle connection failed. Showing CRM contacts below (if any).';
            }
        } elseif ($lazyLoad) {
            $customers = collect();
            $total = 0;
        } else {
            $customers = $this->crm->getCustomers($perPage, $offset, $search);
            $total = $this->crm->getCustomersCount($search);
        }

        $customers = new LengthAwarePaginator(
            $customers instanceof Collection ? $customers : collect($customers),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $listRoute = request()->routeIs('contacts.index') ? 'contacts.index' : 'support.customers';

        return view('support.customers', [
            'customers' => $customers,
            'total' => $total,
            'search' => $search,
            'page' => $page,
            'clientsSource' => $source,
            'clientsError' => $clientsError ?? null,
            'listRoute' => $listRoute,
            'clientsLazyLoad' => $lazyLoad ?? false,
        ]);
    }

    /**
     * JSON API for clients list (used for lazy-load to avoid blocking page).
     */
    public function clientsApi(Request $request): JsonResponse
    {
        $search = $request->get('search');
        $page = max(1, (int) $request->get('page', 1));
        $source = config('erp.clients_view_source', 'crm');
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $useErp = in_array($source, ['erp', 'erp_sync', 'erp_http'])
            && (in_array($source, ['erp_sync', 'erp_http']) || $this->erp->isConfigured())
            && ($source !== 'erp_http' || ! empty(config('erp.clients_http_url')));

        if ($useErp) {
            $cacheKey = 'clients_list_' . md5($source . $perPage . $offset . ($search ?? ''));
            $ttl = (! $search && $page === 1) ? 180 : 120;
            $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, $ttl, fn () => $this->erp->getClientsForListView($perPage, $offset, $search));
            $customers = $result['data'];
            $total = $result['total'];
            $clientsError = $result['error'] ?? null;
            if ($clientsError && $customers->isEmpty()) {
                $customers = $this->crm->getCustomers($perPage, $offset, $search);
                $total = $this->crm->getCustomersCount($search);
            }
        } else {
            $customers = $this->crm->getCustomers($perPage, $offset, $search);
            $total = $this->crm->getCustomersCount($search);
            $clientsError = null;
        }

        $rows = collect($customers)->map(function ($c) use ($source) {
            $c = (object) (is_array($c) ? $c : (array) $c);
            $policy = $c->policy_no ?? $c->policy_number ?? '';
            $isErp = ($c->_erp_source ?? false) && in_array($source, ['erp_sync', 'erp_http']);
            return [
                'policy' => $policy,
                'pol_prepared_by' => $c->pol_prepared_by ?? '—',
                'intermediary' => $c->intermediary ?? '—',
                'life_assur' => $c->life_assur ?? $c->client_name ?? '—',
                'product' => $c->product ?? '—',
                'status' => $c->status ?? '—',
                'is_erp' => $isErp,
                'name' => trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) ?: '—',
                'email' => $c->email ?? '—',
                'mobile' => $c->mobile ?? '—',
            ];
        })->values()->all();

        return response()->json([
            'customers' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'error' => $clientsError,
            'source' => $source,
        ]);
    }
}
