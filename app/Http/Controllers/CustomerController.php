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
    private const CLIENTS_API_CACHE_VERSION = 'v2';

    /** @var CrmService */
    protected $crm;
    /** @var ErpClientService */
    protected $erp;

    public function __construct(CrmService $crm, ErpClientService $erp)
    {
        $this->crm = $crm;
        $this->erp = $erp;
    }

    /**
     * Show client details by policy number (ERP clients) or redirect to contact (CRM).
     */
    /** @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse */
    public function show(Request $request)
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
                : redirect()->route('support.customers', array_filter(['system' => $request->get('system')]));
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
     * Debug: show raw ERP API response. Use ?policy=GEMPPP0335&system=group or ?search=GEMPPP0335&system=group
     */
    public function debugApi(Request $request)
    {
        $policy = trim((string) $request->get('policy', ''));
        $search = trim((string) $request->get('search', ''));
        $system = trim((string) $request->get('system', ''));
        $url = config('erp.clients_http_url');
        if (empty($url)) {
            return response()->json(['error' => 'ERP_CLIENTS_HTTP_URL not configured'], 500);
        }
        $url = rtrim($url, '/');
        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        $params = ['limit' => 5, 'offset' => 0];
        if ($policy !== '') {
            $params['policy'] = $policy;
        }
        if ($search !== '') {
            $params['search'] = $search;
        }
        if (in_array($system, ['group', 'individual', 'mortgage', 'group_pension'], true)) {
            $params['system'] = $system;
        }
        if ($request->get('debug')) {
            $params['debug'] = '1';
        }
        $query = http_build_query($params);
        $apiUrl = $url . $sep . $query;
        $response = \Illuminate\Support\Facades\Http::timeout(20)->get($apiUrl);
        $body = $response->json();
        $parsed = parse_url($url);
        $base = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? 'localhost') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
        $columnsView = match ($system) {
            'group' => 'group',
            'mortgage' => 'mortgage',
            'group_pension' => 'group_pension',
            default => null,
        };
        $columnsUrl = $base . '/columns' . ($columnsView ? '?view=' . $columnsView : '');
        $columnsResp = \Illuminate\Support\Facades\Http::timeout(5)->get($columnsUrl);
        $columnsData = $columnsResp->successful() ? $columnsResp->json() : null;

        return response()->json([
            'policy' => $policy ?: null,
            'search' => $search ?: null,
            'system' => $system ?: null,
            'api_url' => $apiUrl,
            'api_status' => $response->status(),
            'data' => $body['data'] ?? $body['clients'] ?? [],
            'total' => $body['total'] ?? null,
            'error' => $body['error'] ?? null,
            'view_columns' => $columnsData['columns'] ?? null,
            'raw' => $body,
        ]);
    }

    /**
     * Debug: show distinct PRODUCT values from Oracle. Use to configure ERP_GROUP_LIFE_KEYWORDS.
     */
    public function debugProducts(Request $request)
    {
        $url = config('erp.clients_http_url');
        if (empty($url)) {
            return response()->json(['error' => 'ERP_CLIENTS_HTTP_URL not configured'], 500);
        }
        $parsed = parse_url(rtrim($url, '/'));
        $base = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? 'localhost') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
        $response = \Illuminate\Support\Facades\Http::timeout(15)->get($base . '/products');
        $body = $response->json();
        return response()->json($body ?? ['error' => 'API not reachable']);
    }

    public function index(Request $request): View
    {
        $search = $request->get('search');
        $system = $request->get('system'); // group|individual
        $page = max(1, (int) $request->get('page', 1));
        $source = config('erp.clients_view_source', 'crm');
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $clientsError = null;
        $clientsGrandTotal = null;
        $useErp = in_array($source, ['erp', 'erp_sync', 'erp_http'])
            && (in_array($source, ['erp_sync', 'erp_http']) || $this->erp->isConfigured())
            && ($source !== 'erp_http' || ! empty(config('erp.clients_http_url')));

        // Lazy-load: skip slow ERP call on initial page load for fast first paint.
        // When user has searched (or filtered Group/Individual), load server-side so results display.
        $lazyLoad = $useErp && in_array($source, ['erp_sync', 'erp_http'])
            && ! $search && ! $system;

        if ($useErp && ! $lazyLoad) {
            // Never cache with a segment filter, any non-empty search, or merged “All” could hide fresh mortgage rows.
            $skipCache = in_array($system, ['group', 'individual', 'mortgage', 'group_pension'], true)
                || trim((string) ($search ?? '')) !== '';
            if ($skipCache) {
                $result = $this->erp->getClientsForListView($perPage, $offset, $search, $system);
            } else {
                $version = \Illuminate\Support\Facades\Cache::get('clients_list_version', 0);
                $cacheKey = 'clients_list_' . $version . '_' . md5($source . $perPage . $offset . ($search ?? '') . ($system ?? ''));
                $ttl = 60;
                $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, $ttl, fn () => $this->erp->getClientsForListView($perPage, $offset, $search, $system));
            }
            $customers = $result['data'];
            $total = $result['total'];
            $clientsGrandTotal = $result['grand_total'] ?? null;
            $clientsError = $result['error'] ?? null;
            if ($clientsError && $customers->isEmpty()) {
                // API .env missing mortgage/pension/group view vars — do not substitute CRM contacts (misleading).
                $erpHttpConfigGap = $source === 'erp_http' && str_contains((string) $clientsError, 'erp-clients-api/.env');
                if (! $erpHttpConfigGap) {
                    $ownerId = crm_owner_filter();
                    $customers = $this->crm->getCustomers($perPage, $offset, $search, $ownerId);
                    $total = $this->crm->getCustomersCount($search, $ownerId);
                    $clientsGrandTotal = null;
                    $clientsError = $clientsError . ' Showing CRM contacts below (if any).';
                }
            }
        } elseif ($lazyLoad) {
            $customers = collect();
            $total = 0;
        } else {
            $ownerId = crm_owner_filter();
            $customers = $this->crm->getCustomers($perPage, $offset, $search, $ownerId);
            $total = $this->crm->getCustomersCount($search, $ownerId);
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
            'clientsGrandTotal' => $clientsGrandTotal,
            'search' => $search,
            'system' => $system,
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
        $system = $request->get('system'); // group|individual
        $page = max(1, (int) $request->get('page', 1));
        $source = config('erp.clients_view_source', 'crm');
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $useErp = in_array($source, ['erp', 'erp_sync', 'erp_http'])
            && (in_array($source, ['erp_sync', 'erp_http']) || $this->erp->isConfigured())
            && ($source !== 'erp_http' || ! empty(config('erp.clients_http_url')));

        $apiCacheKey = 'customers:api:' . sha1(json_encode([
            'v' => self::CLIENTS_API_CACHE_VERSION,
            'search' => $search,
            'system' => $system,
            'page' => $page,
            'source' => $source,
            'owner' => crm_owner_filter(),
        ]));
        $cachedPayload = \Illuminate\Support\Facades\Cache::get($apiCacheKey);
        if (is_array($cachedPayload)) {
            return response()->json($cachedPayload);
        }

        $clientsGrandTotal = null;
        if ($useErp) {
            $skipCache = in_array($system, ['group', 'individual', 'mortgage', 'group_pension'], true)
                || trim((string) ($search ?? '')) !== '';
            if ($skipCache) {
                $result = $this->erp->getClientsForListView($perPage, $offset, $search, $system);
            } else {
                $version = \Illuminate\Support\Facades\Cache::get('clients_list_version', 0);
                $cacheKey = 'clients_list_' . $version . '_' . md5($source . $perPage . $offset . ($search ?? '') . ($system ?? ''));
                $ttl = 60;
                $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, $ttl, fn () => $this->erp->getClientsForListView($perPage, $offset, $search, $system));
            }
            $customers = $result['data'];
            $total = $result['total'];
            $clientsGrandTotal = $result['grand_total'] ?? null;
            $clientsError = $result['error'] ?? null;
            if ($clientsError && $customers->isEmpty()) {
                $erpHttpConfigGap = $source === 'erp_http' && str_contains((string) $clientsError, 'erp-clients-api/.env');
                if (! $erpHttpConfigGap) {
                    $ownerId = crm_owner_filter();
                    $customers = $this->crm->getCustomers($perPage, $offset, $search, $ownerId);
                    $total = $this->crm->getCustomersCount($search, $ownerId);
                    $clientsGrandTotal = null;
                }
            }
        } else {
            $ownerId = crm_owner_filter();
            $customers = $this->crm->getCustomers($perPage, $offset, $search, $ownerId);
            $total = $this->crm->getCustomersCount($search, $ownerId);
            $clientsError = null;
            $clientsGrandTotal = null;
        }

        $rows = collect($customers)->map(function ($c) use ($source) {
            $c = (object) (is_array($c) ? $c : (array) $c);
            $row = (array) $c;
            $policy = trim((string) $this->pickFirstNonEmpty($row, [
                'policy_no', 'policy_number', 'ipol_policy_no', 'pol_policy_no', 'contract_no', 'scheme_no',
                'POLICY_NO', 'POLICY_NUMBER', 'IPOL_POLICY_NO', 'POL_POLICY_NO', 'CONTRACT_NO', 'SCHEME_NO',
            ]));
            $polPreparedBy = trim((string) $this->pickFirstNonEmpty($row, [
                'pol_prepared_by', 'POL_PREPARED_BY', 'bra_manager', 'unit_manar',
            ]));
            $intermediary = trim((string) $this->pickFirstNonEmpty($row, [
                'intermediary', 'INTERMEDIARY', 'intermediary_name', 'agency', 'agn_name', 'agnName',
            ]));
            $lifeAssured = trim((string) $this->pickFirstNonEmpty($row, [
                'life_assur', 'life_assured', 'lifeAssur', 'lifeAssured',
                'client_name', 'CLIENT_NAME', 'name', 'NAME', 'member_name', 'mem_surname',
            ]));
            $product = trim((string) $this->pickFirstNonEmpty($row, [
                'product', 'PRODUCT', 'prod_desc', 'PROD_DESC', 'prod_sht_desc', 'scheme_name', 'SCHEME_NAME',
            ]));
            $status = trim((string) $this->pickFirstNonEmpty($row, [
                'status', 'STATUS', 'mendr_status', 'MENDR_STATUS', 'endr_status', 'ENDR_STATUS', 'policy_status', 'POLICY_STATUS',
            ]));
            $email = $this->pickFirstNonEmpty($row, ['email', 'EMAIL', 'email_adr', 'EMAIL_ADR']) ?? null;
            $mobile = $this->pickFirstNonEmpty($row, ['mobile', 'phone', 'phone_no', 'PHONE_NO']) ?? null;

            $isErp = ($c->_erp_source ?? false) && in_array($source, ['erp_sync', 'erp_http']);
            $lifeSystem = $c->life_system ?? $this->erp->getLifeSystemFromProduct($product ?: null);
            return [
                'policy' => $policy,
                'policy_no' => $policy,
                'policy_number' => $policy,
                'pol_prepared_by' => $polPreparedBy !== '' ? $polPreparedBy : '—',
                'intermediary' => $intermediary !== '' ? $intermediary : '—',
                'life_assur' => $lifeAssured !== '' ? $lifeAssured : '—',
                'product' => $product !== '' ? $product : '—',
                'life_system' => $lifeSystem,
                'status' => $status !== '' ? $status : '—',
                'is_erp' => $isErp,
                'name' => trim(($c->firstname ?? '') . ' ' . ($c->lastname ?? '')) ?: '—',
                'email' => personal_email_only($email) ?? '—',
                'mobile' => $mobile ?: '—',
            ];
        })->values()->all();

        $payload = [
            'customers' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'error' => $clientsError,
            'source' => $source,
        ];
        if ($useErp) {
            $payload['grand_total'] = $clientsGrandTotal;
        }

        \Illuminate\Support\Facades\Cache::put($apiCacheKey, $payload, 45);

        return response()->json($payload);
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<int,string>  $keys
     */
    private function pickFirstNonEmpty(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $value = $row[$key];
            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
