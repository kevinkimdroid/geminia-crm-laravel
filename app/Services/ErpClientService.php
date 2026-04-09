<?php

namespace App\Services;

use Illuminate\Database\LostConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ErpClientService
{
    /**
     * Human-readable label for Support > Clients "System" column and client detail.
     */
    public function getClientSystemLabel(string $system): string
    {
        $labels = config('clients_ui.tab_labels', []);
        if (isset($labels[$system]) && is_string($labels[$system]) && $labels[$system] !== '') {
            return $labels[$system];
        }

        return match ($system) {
            'group' => 'Group Life',
            'individual' => 'Individual Life',
            'mortgage' => 'Mortgage',
            'group_pension' => 'Group Pension',
            default => 'Individual Life',
        };
    }

    /**
     * Client segment from product name (used for badges and erp_sync filters).
     * Order: mortgage → group pension → group life → individual.
     *
     * @return 'group'|'individual'|'mortgage'|'group_pension'
     */
    public function getLifeSystemFromProduct(?string $product): string
    {
        $product = (string) ($product ?? '');
        $productUpper = strtoupper($product);
        foreach (array_filter(config('erp.mortgage_keywords', [])) as $kw) {
            if ($kw !== '' && str_contains($productUpper, strtoupper($kw))) {
                return 'mortgage';
            }
        }
        foreach (array_filter(config('erp.group_pension_keywords', [])) as $kw) {
            if ($kw !== '' && str_contains($productUpper, strtoupper($kw))) {
                return 'group_pension';
            }
        }
        foreach (array_filter(config('erp.group_life_keywords', [])) as $kw) {
            if ($kw !== '' && str_contains($productUpper, strtoupper($kw))) {
                return 'group';
            }
        }
        foreach (array_filter(config('erp.individual_life_keywords', [])) as $kw) {
            if ($kw !== '' && str_contains($productUpper, strtoupper($kw))) {
                return 'individual';
            }
        }
        return 'individual';
    }

    /**
     * Apply life system filter (group/individual) to query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    protected function applyLifeSystemFilter($query, string $system, string $productColumn = 'product'): void
    {
        if ($system === 'group') {
            $keywords = array_filter(config('erp.group_life_keywords', []));
            if (empty($keywords)) {
                $query->whereRaw("UPPER({$productColumn}) LIKE '%GROUP%'");
            } else {
                $query->where(function ($q) use ($keywords, $productColumn) {
                    foreach ($keywords as $kw) {
                        $like = '%' . strtoupper($kw) . '%';
                        $q->orWhereRaw("UPPER({$productColumn}) LIKE ?", [$like]);
                    }
                });
            }
        } elseif ($system === 'individual') {
            $groupKw = array_filter(config('erp.group_life_keywords', []));
            if (! empty($groupKw)) {
                foreach ($groupKw as $kw) {
                    $query->whereRaw("UPPER({$productColumn}) NOT LIKE ?", ['%' . strtoupper($kw) . '%']);
                }
            } else {
                $query->whereRaw("(UPPER({$productColumn}) LIKE '%INDIVIDUAL%' OR UPPER({$productColumn}) NOT LIKE '%GROUP%')");
            }
        } elseif ($system === 'mortgage') {
            $keywords = array_filter(config('erp.mortgage_keywords', []));
            if (! empty($keywords)) {
                $query->where(function ($q) use ($keywords, $productColumn) {
                    foreach ($keywords as $kw) {
                        $like = '%' . strtoupper($kw) . '%';
                        $q->orWhereRaw("UPPER({$productColumn}) LIKE ?", [$like]);
                    }
                });
            }
        } elseif ($system === 'group_pension') {
            $keywords = array_filter(config('erp.group_pension_keywords', []));
            if (! empty($keywords)) {
                $query->where(function ($q) use ($keywords, $productColumn) {
                    foreach ($keywords as $kw) {
                        $like = '%' . strtoupper($kw) . '%';
                        $q->orWhereRaw("UPPER({$productColumn}) LIKE ?", [$like]);
                    }
                });
            }
        }
    }

    /**
     * Fetch all clients from the ERP system (Oracle/PL-SQL).
     *
     * @param  int|null  $limit  Max records to return (null = no limit)
     * @param  int  $offset  Offset for pagination
     * @return array{data: array, total: int|null, error?: string}
     */
    public function getClients(?int $limit = null, int $offset = 0): array
    {
        if (! config('erp.enabled', true)) {
            return ['data' => [], 'total' => null, 'error' => 'ERP integration is disabled (ERP_ENABLED=false).'];
        }

        try {
            $source = config('erp.clients_source', 'table');

            if ($source === 'http') {
                return $this->fetchFromHttp($limit, $offset);
            }

            if ($source === 'procedure') {
                return $this->fetchFromProcedure($limit, $offset);
            }

            return $this->fetchFromTable($limit, $offset);
        } catch (\Throwable $e) {
            Log::error('ERP clients fetch failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'data' => [],
                'total' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch clients from a table or view.
     */
    protected function fetchFromTable(?int $limit, int $offset): array
    {
        $table = config('erp.clients_table', 'CLIENTS');
        $columns = config('erp.clients_columns', '*');

        $query = DB::connection('erp')
            ->table($table)
            ->selectRaw($columns === '*' ? '*' : $columns);

        $total = $query->count();

        if ($limit !== null) {
            $query->limit($limit)->offset($offset);
        }

        $rows = $query->get();

        $data = $rows->map(fn ($row) => $this->mapRow((array) $row))->values()->all();

        return [
            'data' => $data,
            'total' => $total,
        ];
    }

    /**
     * Fetch clients from a PL/SQL stored procedure.
     * Best approach: create a VIEW that selects from your procedure (or a table it populates)
     * and set ERP_CLIENTS_VIEW. Otherwise we use the configured table.
     */
    protected function fetchFromProcedure(?int $limit, int $offset): array
    {
        $view = config('erp.clients_view');

        if ($view) {
            config(['erp.clients_table' => $view]);

            return $this->fetchFromTable($limit, $offset);
        }

        throw new \RuntimeException(
            'For procedure source, set ERP_CLIENTS_VIEW to a view/table that your PL/SQL procedure populates, '
            . 'or use clients_source=table with your clients table/view.'
        );
    }

    /**
     * Fetch clients from ERP REST API.
     */
    protected function fetchFromHttp(?int $limit, int $offset): array
    {
        $url = config('erp.clients_http_url');
        if (empty($url)) {
            throw new \RuntimeException('ERP_CLIENTS_HTTP_URL is not configured.');
        }

        $params = [];
        if ($limit !== null) {
            $params['limit'] = $limit;
            $params['offset'] = $offset;
        }

        $response = Http::timeout(30)->get($url, $params);

        if (! $response->successful()) {
            throw new \RuntimeException("ERP API request failed: {$response->status()}");
        }

        $body = $response->json();
        $data = $body['data'] ?? $body['clients'] ?? $body['results'] ?? (is_array($body) ? $body : []);

        if (! is_array($data)) {
            $data = [];
        }

        $total = $body['total'] ?? $body['count'] ?? count($data);

        return [
            'data' => array_values($data),
            'total' => (int) $total,
        ];
    }

    /**
     * Map ERP row to API response using config column_map.
     */
    protected function mapRow(array $row): array
    {
        $map = config('erp.column_map', []);
        if (empty($map)) {
            return $this->normalizeKeys($row);
        }

        $mapped = [];
        foreach ($row as $key => $value) {
            $upperKey = strtoupper((string) $key);
            $apiKey = $map[$upperKey] ?? $this->snakeCase($key);
            $mapped[$apiKey] = $value;
        }

        return $mapped;
    }

    protected function normalizeKeys(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            $out[$this->snakeCase((string) $key)] = $value;
        }

        return $out;
    }

    protected function snakeCase(string $value): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $value));
    }

    /**
     * Search clients by policy number, name, phone, or email.
     * Used for customer service "Serve Client" flow.
     *
     * @param  string  $term  Search term
     * @param  int  $limit  Max results
     * @return array{data: array, total: int|null, error?: string}
     */
    public function searchClients(string $term, int $limit = 50): array
    {
        if (! config('erp.enabled', true)) {
            return ['data' => [], 'total' => null, 'error' => 'ERP integration is disabled (ERP_ENABLED=false).'];
        }

        $viewSource = config('erp.clients_view_source', 'crm');

        // Use cache when erp_sync – same clients as Customers list, used app-wide
        if ($viewSource === 'erp_sync') {
            $result = $this->getClientsFromCache($limit, 0, $term);
            $data = $result['data']->map(fn ($obj) => (array) $obj)->map(fn ($r) => $this->mapClientObjectToSearchResult($r))->values()->all();

            return ['data' => $data, 'total' => $result['total'], 'error' => $result['error']];
        }

        // Use HTTP API when erp_http - search BOTH group and individual (Group Life uses different view)
        if ($viewSource === 'erp_http') {
            $groupResult = $this->getClientsFromHttpApi((int) ceil($limit / 2), 0, $term, null, false, 'group');
            $indResult = $this->getClientsFromHttpApi((int) ceil($limit / 2), 0, $term, null, false, 'individual');
            $groupData = $groupResult['data']->map(fn ($obj) => (array) $obj)->map(fn ($r) => $this->mapClientObjectToSearchResult($r))->values()->all();
            $indData = $indResult['data']->map(fn ($obj) => (array) $obj)->map(fn ($r) => $this->mapClientObjectToSearchResult($r))->values()->all();
            $seen = [];
            $data = [];
            foreach (array_merge($groupData, $indData) as $r) {
                $key = trim((string) ($r['policy_no'] ?? $r['policy_number'] ?? json_encode($r)));
                if ($key !== '' && ! isset($seen[$key])) {
                    $seen[$key] = true;
                    $data[] = $r;
                }
            }
            $data = array_slice($data, 0, $limit);
            $error = $groupResult['error'] ?: $indResult['error'];
            $total = min(($groupResult['total'] ?? 0) + ($indResult['total'] ?? 0), $limit * 2);

            return ['data' => $data, 'total' => count($data) ?: $total, 'error' => $error];
        }

        try {
            $source = config('erp.clients_source', 'table');
            if ($source !== 'table') {
                return $this->getClients($limit, 0);
            }

            $table = config('erp.clients_table', 'CLIENTS');
            $searchColumns = config('erp.search_columns', 'POLICY_NO,POLICY_NUMBER,CLIENT_NAME,NAME,FIRST_NAME,LAST_NAME,PHONE,MOBILE,EMAIL');
            $columns = array_filter(array_map('trim', explode(',', $searchColumns)));

            $query = DB::connection('erp')->table($table);
            $term = trim($term);
            if ($term !== '' && count($columns) > 0) {
                $like = '%' . $term . '%';
                $query->where(function ($q) use ($columns, $like) {
                    foreach ($columns as $col) {
                        $q->orWhere($col, 'like', $like);
                    }
                });
            }

            $total = $query->count();
            $rows = $query->limit($limit)->get();
            $data = $rows->map(fn ($row) => $this->mapRow((array) $row))->values()->all();

            return ['data' => $data, 'total' => $total];
        } catch (\Throwable $e) {
            Log::error('ERP clients search failed', ['message' => $e->getMessage()]);

            return ['data' => [], 'total' => null, 'error' => $e->getMessage()];
        }
    }

    /** Map cached/HTTP client object to search result format. Reject receipt format (1/HO/xxx); use policy (GEMPPP0070). */
    protected function mapClientObjectToSearchResult(array $row): array
    {
        $get = fn (array $keys) => collect($keys)->map(fn ($k) => $row[$k] ?? null)->first(fn ($v) => $v !== null && $v !== '');
        // Policy: always prefer policy_number column first (never confuse with policy_no or others)
        $policyKeys = ['policy_number', 'policy_no', 'POLICY_NUMBER', 'ipol_policy_no', 'pol_policy_no', 'contract_no', 'scheme_no'];
        $policyNo = '';
        foreach ($policyKeys as $k) {
            $v = $row[$k] ?? null;
            $str = trim((string) ($v ?? ''));
            if ($str !== '' && ! $this->isReceiptFormat($str) && ! $this->isPinFormat($str)) {
                $policyNo = $str;
                break;
            }
        }
        if ($policyNo === '') {
            $v = trim((string) ($get($policyKeys) ?? ''));
            $policyNo = ($v !== '' && ! $this->isPinFormat($v)) ? $v : '';
        }
        $receipt = $get(['receipt_number', 'grct_receipt_no']);
        if ($receipt && $policyNo === trim((string) $receipt)) {
            $policyNo = '';
        }
        $lifeAssured = (string) ($get(['life_assur', 'life_assured', 'lifeAssur', 'lifeAssured', 'client_name', 'clientName', 'member_name', 'mem_surname']) ?? '');
        $name = trim($lifeAssured) ?: trim(($row['pol_prepared_by'] ?? '') . ' ' . ($row['intermediary'] ?? '')) ?: trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
        $emailRaw = $get(['email', 'email_adr', 'emailAdr', 'client_email', 'mem_email']);
        $email = personal_email_only($emailRaw);
        $mobile = $get(['mobile', 'phone', 'phone_no', 'phoneNo', 'client_contact', 'mem_teleph']);

        return [
            'policy_no' => $policyNo,
            'policy_number' => $policyNo,
            'name' => $name,
            'client_name' => $name,
            'life_assur' => $lifeAssured,
            'life_assured' => $lifeAssured,
            'pol_prepared_by' => $row['pol_prepared_by'] ?? '',
            'intermediary' => $row['intermediary'] ?? '',
            'product' => $this->resolveProductExcludingAgent($row),
            'status' => (string) ($get(['mendr_status', 'mendrStatus', 'MENDR_STATUS', 'endr_status', 'endrStatus', 'ENDR_STATUS', 'status', 'STATUS', 'policy_status', 'POLICY_STATUS']) ?? ''),
            'kra_pin' => $get(['kra_pin', 'kraPin']) ?? '',
            'id_no' => $get(['id_no', 'idNo', 'ID_NO']),
            'phone_no' => $get(['phone_no', 'phoneNo', 'phone', 'mobile']),
            'prp_dob' => $get(['prp_dob', 'prpDob', 'PRP_DOB']),
            'scheme_name' => $get(['scheme_name', 'schemeName', 'SCHEME_NAME']) ?? '',
            'maturity' => $get(['maturity', 'maturity_date', 'maturityDate', 'MATURITY_DATE']),
            'effective_date' => $get(['effective_date', 'effectiveDate', 'EFFECTIVE_DATE']),
            'paid_mat_amt' => $get(['bal', 'BAL', 'paid_mat_amt', 'paidMatAmt', 'PAID_MAT_AMT', 'production_amt']),
            'bal' => $get(['bal', 'BAL']),
            'checkoff' => $get(['checkoff', 'CHECKOFF']),
            'email_adr' => $email,
            'email' => $email,
            'mobile' => $mobile,
            'phone' => $mobile,
        ];
    }

    /**
     * Fetch all policies for a contact from the ERP.
     * Searches by policy number, phone, email, and name to find matching policies.
     *
     * @param  object  $contact  Contact with policy_number, mobile, phone, email, firstname, lastname
     * @param  int  $limit  Max policies to return
     * @return array{data: array, total: int|null, error?: string}
     */
    public function getPoliciesForContact(object $contact, int $limit = 100): array
    {
        if (! config('erp.enabled', true)) {
            return ['data' => [], 'total' => 0, 'error' => 'ERP integration is disabled (ERP_ENABLED=false).'];
        }

        // cf_852 = KRA PIN; use only policy fields (cf_860, cf_856, cf_872)
        $policyNo = $contact->policy_number ?? $contact->cf_860 ?? $contact->cf_856 ?? $contact->cf_872 ?? null;
        $name = trim(($contact->firstname ?? '') . ' ' . ($contact->lastname ?? ''));

        // Use cache when erp_sync – same clients used app-wide
        if (config('erp.clients_view_source') === 'erp_sync') {
            $searchTerms = array_filter([$policyNo, $name], fn ($v) => $v !== null && $v !== '');
            if (empty($searchTerms)) {
                return ['data' => [], 'total' => 0];
            }
            $term = $searchTerms[0];
            $result = $this->getClientsFromCache($limit, 0, $term);
            $data = $result['data']->map(fn ($o) => $this->mapClientObjectToSearchResult((array) $o))->values()->all();

            return ['data' => $data, 'total' => count($data)];
        }

        // Use HTTP API when erp_http — avoid direct Oracle (ORA-03113 connection drops)
        if (config('erp.clients_view_source') === 'erp_http') {
            $searchTerms = array_filter([
                $policyNo,
                trim($contact->mobile ?? $contact->phone ?? ''),
                trim($contact->email ?? ''),
                $name,
            ], fn ($v) => $v !== null && $v !== '');
            if (empty($searchTerms)) {
                return ['data' => [], 'total' => 0];
            }
            $term = (string) $searchTerms[0];
            $result = $this->getClientsFromHttpApi($limit, 0, $term);
            if ($result['error']) {
                return ['data' => [], 'total' => 0, 'error' => $result['error']];
            }
            $data = $result['data']->map(fn ($o) => $this->mapClientObjectToSearchResult((array) $o))->values()->all();

            return ['data' => $data, 'total' => count($data)];
        }

        $attempts = 0;
        $maxAttempts = 2;

        while (true) {
            try {
                $source = config('erp.clients_source', 'table');
                if ($source !== 'table') {
                    return ['data' => [], 'total' => 0];
                }

                $phone = trim($contact->mobile ?? $contact->phone ?? '');
                $email = trim($contact->email ?? '');
                $searchTerms = array_filter([$policyNo, $phone, $email, $name], fn ($v) => $v !== null && $v !== '');

                if (empty($searchTerms)) {
                    return ['data' => [], 'total' => 0];
                }

                $table = config('erp.clients_table', 'CLIENTS');
                $searchColumns = config('erp.search_columns', 'POLICY_NO,POLICY_NUMBER,CLIENT_NAME,NAME,FIRST_NAME,LAST_NAME,PHONE,MOBILE,EMAIL');
                $columns = array_filter(array_map('trim', explode(',', $searchColumns)));

                $seen = [];
                $data = [];

                foreach ($searchTerms as $term) {
                $term = trim((string) $term);
                if ($term === '') {
                    continue;
                }

                $like = '%' . $term . '%';
                $query = DB::connection('erp')->table($table);

                if (count($columns) > 0) {
                    $query->where(function ($q) use ($columns, $like) {
                        foreach ($columns as $col) {
                            $q->orWhere($col, 'like', $like);
                        }
                    });
                }

                $rows = $query->limit($limit)->get();
                foreach ($rows as $row) {
                    $mapped = $this->mapRow((array) $row);
                    $key = $mapped['policy_no'] ?? $mapped['policy_number'] ?? json_encode($mapped);
                    if (! isset($seen[$key])) {
                        $seen[$key] = true;
                        $data[] = $mapped;
                    }
                }
            }

                $data = array_slice($data, 0, $limit);

                return ['data' => $data, 'total' => count($data)];
            } catch (LostConnectionException $e) {
                $errMsg = $e->getMessage();
            } catch (\Throwable $e) {
                $errMsg = $e->getMessage();
                $lostConnection = (strpos($errMsg, 'Lost connection') !== false) || (strpos($errMsg, 'no reconnector') !== false);
                if (! $lostConnection) {
                    Log::error('ERP policies for contact failed', ['message' => $errMsg]);
                    return ['data' => [], 'total' => null, 'error' => $errMsg];
                }
            }
            $attempts++;
            if ($attempts >= $maxAttempts) {
                Log::error('ERP policies for contact failed', ['message' => $errMsg]);
                return ['data' => [], 'total' => null, 'error' => $errMsg];
            }
            DB::purge('erp');
        }
    }

    /**
     * Get a single policy's full details by policy number.
     *
     * @param  string  $policyNumber  Policy number to look up
     * @return array|null  Mapped policy row or null if not found
     */
    public function getPolicyDetails(string $policyNumber): ?array
    {
        try {
            if (config('erp.clients_view_source') === 'erp_http') {
                $url = config('erp.clients_http_url');
                if (empty($url)) {
                    return null;
                }
                $url = rtrim($url, '/');
                $sep = (strpos($url, '?') !== false) ? '&' : '?';
                $term = trim($policyNumber);
                if ($term === '') {
                    return null;
                }
                $systemsToTry = array_values(array_filter(
                    ['group', 'individual', 'mortgage', 'group_pension', null],
                    fn ($s) => $s === null || ! in_array($s, ['mortgage', 'group_pension'], true) || $this->optionalClientsSegmentConfigured($s)
                ));
                // First try exact policy= match, then try search= (LIKE %term%) if no results
                foreach (['policy', 'search'] as $matchMode) {
                    foreach ($systemsToTry as $system) {
                        $params = ($matchMode === 'policy' ? 'policy=' : 'search=') . urlencode($term) . '&limit=5';
                        if ($system) {
                            $params .= '&system=' . $system;
                        }
                        $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url . $sep . $params);
                        if (! $response->successful()) {
                            continue;
                        }
                        $body = $response->json();
                        $rows = $body['data'] ?? $body['clients'] ?? [];
                        if (! is_array($rows) || empty($rows)) {
                            continue;
                        }
                        // Find row where policy matches (or first row when we used search=)
                        $row = null;
                        foreach ($rows as $r) {
                            $r = is_array($r) ? $r : (array) $r;
                            $mapped = $this->mapClientObjectToSearchResult($r);
                            $returnedPolicy = trim((string) ($mapped['policy_no'] ?? $mapped['policy_number'] ?? $r['ipol_policy_no'] ?? $r['pol_policy_no'] ?? $r['policy_number'] ?? ''));
                            if ($returnedPolicy === $term || ($returnedPolicy === '' && $system === 'group' && $matchMode === 'policy')) {
                                $row = $r;
                                break;
                            }
                            if ($matchMode === 'search' && (str_contains($returnedPolicy, $term) || str_contains($term, $returnedPolicy))) {
                                $row = $r;
                                break;
                            }
                        }
                        if (! $row && $matchMode === 'search' && ! empty($rows)) {
                            $row = is_array($rows[0]) ? $rows[0] : (array) $rows[0];
                        }
                        if (! $row) {
                            continue;
                        }
                        $row = is_array($row) ? $row : (array) $row;
                        $mapped = $this->mapClientObjectToSearchResult($row);
                        // Merge: keep all API keys, overlay mapped values. Build complete client object for details view.
                        $merged = $row;
                        $merged['life_system'] = $system ?: $this->getLifeSystemFromProduct($merged['product'] ?? null);
                        foreach ($mapped as $k => $v) {
                            if ($v !== null && $v !== '') {
                                $merged[$k] = $v;
                            }
                        }
                        $merged['policy_no'] = $term;
                        $merged['policy_number'] = $term;
                        if (empty($merged['maturity']) && ! empty($merged['maturity_date'])) {
                            $merged['maturity'] = $merged['maturity_date'];
                        }
                        if (empty($merged['product']) && ! empty($merged['prod_desc'])) {
                            $merged['product'] = $merged['prod_desc'];
                        }
                        if (! empty($merged['intermediary']) && ! empty($merged['product']) && trim((string) $merged['product']) === trim((string) $merged['intermediary'])) {
                            $merged['product'] = $merged['prod_desc'] ?? '';
                        }
                        if (empty($merged['paid_mat_amt']) && ! empty($merged['production_amt'])) {
                            $merged['paid_mat_amt'] = $merged['production_amt'];
                        }
                        if (empty($merged['email_adr']) && ! empty($merged['client_email'])) {
                            $merged['email_adr'] = $merged['client_email'];
                        }
                        if (empty($merged['phone_no']) && ! empty($merged['client_contact'])) {
                            $merged['phone_no'] = $merged['client_contact'];
                            $merged['mobile'] = $merged['client_contact'];
                        }
                        if (empty($merged['life_assur']) && ! empty($merged['client_name'])) {
                            $merged['life_assur'] = $merged['client_name'];
                        }
                        return $merged;
                    }
                }
                return null;
            }

            if (config('erp.clients_view_source') === 'erp_sync') {
                $row = DB::table('erp_clients_cache')->where('policy_number', $policyNumber)->first();
                if (!$row) {
                    return null;
                }
                $mapped = $this->mapClientObjectToSearchResult((array) $row);
                $mapped['life_system'] = $this->getLifeSystemFromProduct($mapped['product'] ?? null);
                return $mapped;
            }

            $source = config('erp.clients_source', 'table');
            if ($source !== 'table') {
                return null;
            }

            $table = config('erp.clients_table', 'CLIENTS');
            $policyCols = ['POLICY_NO', 'POLICY_NUMBER'];
            $term = trim($policyNumber);
            if ($term === '') {
                return null;
            }

            $query = DB::connection('erp')->table($table);
            $query->where(function ($q) use ($policyCols, $term) {
                foreach ($policyCols as $col) {
                    $q->orWhere($col, '=', $term);
                }
            });

            $row = $query->first();
            if (!$row) {
                return null;
            }
            $mapped = $this->mapRow((array) $row);
            $mapped['life_system'] = $this->getLifeSystemFromProduct($mapped['product'] ?? ($row->PRODUCT ?? null));
            return $mapped;
        } catch (\Throwable $e) {
            Log::error('ERP policy details failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get total clients count (for dashboard). Uses same source and logic as Support > Clients page.
     * For erp_http: sums Group Life + Individual Life + Mortgage + Group Pension (when those views are configured).
     */
    public function getClientsCount(int $timeoutSeconds = 15): ?int
    {
        if (! config('erp.enabled', true)) {
            return null;
        }
        $source = config('erp.clients_view_source', 'crm');
        if ($source === 'erp_http') {
            $groupResult = $this->getClientsFromHttpApi(1, 0, null, $timeoutSeconds, true, 'group');
            $indResult = $this->getClientsFromHttpApi(1, 0, null, $timeoutSeconds, true, 'individual');
            $hadAnySuccess = (! $groupResult['error'] || ! $indResult['error']);
            $groupTotal = (int) ($groupResult['total'] ?? 0);
            $indTotal = (int) ($indResult['total'] ?? 0);
            $sum = $groupTotal + $indTotal;

            if ($this->optionalClientsSegmentConfigured('mortgage')) {
                $m = $this->getClientsFromHttpApi(1, 0, null, $timeoutSeconds, true, 'mortgage');
                if (! $m['error']) {
                    $hadAnySuccess = true;
                    $sum += (int) ($m['total'] ?? 0);
                }
            }
            if ($this->optionalClientsSegmentConfigured('group_pension')) {
                $gp = $this->getClientsFromHttpApi(1, 0, null, $timeoutSeconds, true, 'group_pension');
                if (! $gp['error']) {
                    $hadAnySuccess = true;
                    $sum += (int) ($gp['total'] ?? 0);
                }
            }

            if (! $hadAnySuccess && $groupResult['error'] && $indResult['error']) {
                return null;
            }

            return $sum;
        }
        if ($source === 'erp_sync') {
            $result = $this->getClientsFromCache(1, 0);
            return $result['error'] ? null : (int) $result['total'];
        }
        return null;
    }

    /**
     * Mortgage / Group Pension list tabs require explicit Oracle view names in .env.
     * When unset, those tabs show no rows (we do not fall back to the individual view).
     */
    public function optionalClientsSegmentConfigured(?string $system): bool
    {
        if ($system === 'mortgage') {
            return trim((string) config('erp.clients_mortgage_view')) !== '';
        }
        if ($system === 'group_pension') {
            return trim((string) config('erp.clients_group_pension_view')) !== '';
        }

        return true;
    }

    /**
     * Same eligibility as Support → Clients when loading from ERP / cache / HTTP API (not plain CRM-only).
     */
    public function isClientsViewBackedByErp(): bool
    {
        if (! config('erp.enabled', true)) {
            return false;
        }

        $source = config('erp.clients_view_source', 'crm');
        if (! in_array($source, ['erp', 'erp_sync', 'erp_http'], true)) {
            return false;
        }

        if ($source === 'erp_http') {
            return trim((string) config('erp.clients_http_url')) !== '';
        }

        if (in_array($source, ['erp_sync'], true)) {
            return true;
        }

        return $this->isConfigured();
    }

    /**
     * Policies that appear in the given life-system segment (same rules as Support → Clients pills).
     *
     * @param  list<string>  $policyNumbers
     * @return list<string>
     */
    public function filterPoliciesMatchingLifeSystemSegment(array $policyNumbers, string $system): array
    {
        $policyNumbers = array_values(array_unique(array_filter(array_map(
            static fn ($p) => trim(preg_replace('/\s+/', '', (string) $p)),
            $policyNumbers
        ))));
        if ($policyNumbers === []) {
            return [];
        }

        if (! in_array($system, ['group', 'individual', 'mortgage', 'group_pension'], true)) {
            return [];
        }

        if (in_array($system, ['mortgage', 'group_pension'], true) && ! $this->optionalClientsSegmentConfigured($system)) {
            return [];
        }

        $source = config('erp.clients_view_source', 'crm');

        if ($source === 'erp_sync' && Schema::hasTable('erp_clients_cache')) {
            $allowed = [];
            $rows = DB::table('erp_clients_cache')->whereIn('policy_number', $policyNumbers)->get();
            foreach ($rows as $row) {
                $pn = trim((string) ($row->policy_number ?? ''));
                if ($pn === '') {
                    continue;
                }
                if ($this->getLifeSystemFromProduct($row->product ?? null) === $system) {
                    $allowed[] = $pn;
                }
            }

            return array_values(array_unique($allowed));
        }

        if ($source === 'erp_http') {
            $allowed = [];
            foreach ($policyNumbers as $p) {
                if ($p === '') {
                    continue;
                }
                $res = $this->getClientsFromHttpApi(15, 0, $p, 10, false, $system);
                $hit = false;
                foreach ($res['data'] as $obj) {
                    $rowPol = trim((string) ($obj->policy_no ?? $obj->policy_number ?? ''));
                    if ($rowPol !== '' && strcasecmp($rowPol, $p) === 0) {
                        $hit = true;
                        break;
                    }
                }
                if ($hit) {
                    $allowed[] = $p;
                }
            }

            return $allowed;
        }

        if ($source === 'erp' && $this->isConfigured()) {
            try {
                $table = $this->resolveClientsListTable($system);
                $policyCol = 'POLICY_NUMBER';
                $found = DB::connection('erp')->table($table)->whereIn($policyCol, $policyNumbers)->pluck($policyCol)->all();

                return array_values(array_unique(array_map('strval', $found)));
            } catch (\Throwable $e) {
                Log::warning('ErpClientService::filterPoliciesMatchingLifeSystemSegment: Oracle failed', [
                    'message' => $e->getMessage(),
                    'system' => $system,
                ]);

                return [];
            }
        }

        return [];
    }

    /**
     * Family / prefix token (e.g. GL-GLA-): uses LIKE on ERP, not exact policy=.
     */
    protected function isPrefixStylePolicySearch(string $term): bool
    {
        $t = trim($term);
        if ($t === '') {
            return false;
        }

        return $this->searchTermLooksLikePolicyNumber($t)
            && ! $this->shouldSendExactPolicyParamForSegmentSearch($t);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function dedupeClientsByPolicyAndSystem(Collection $rows): Collection
    {
        $seen = [];
        $out = collect();
        foreach ($rows as $row) {
            $p = strtolower(trim((string) ($row->policy_number ?? $row->policy_no ?? '')));
            $ls = strtolower(trim((string) ($row->life_system ?? '')));
            if ($p === '') {
                continue;
            }
            $k = $ls.'|'.$p;
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out->push($row);
        }

        return $out;
    }

    /**
     * Resolve which table/view to use for clients list based on life system filter.
     *
     * @return string Schema-qualified table or view name
     */
    protected function resolveClientsListTable(?string $system): string
    {
        $default = config('erp.clients_list_table', config('erp.clients_table', 'CLIENTS'));
        if ($system === 'group') {
            return config('erp.clients_group_view', 'LMS_GROUP_CRM_VIEW');
        }
        if ($system === 'individual') {
            return config('erp.clients_individual_view', 'LMS_INDIVIDUAL_CRM_VIEW');
        }
        if ($system === 'mortgage') {
            return (string) config('erp.clients_mortgage_view');
        }
        if ($system === 'group_pension') {
            return (string) config('erp.clients_group_pension_view');
        }

        return $default;
    }

    /**
     * Get clients for Support > Customers list.
     * Source: erp = live Oracle, erp_sync = cached table.
     *
     * @return array{data: \Illuminate\Support\Collection, total: int, error: string|null, grand_total?: int|null}
     */
    public function getClientsForListView(int $limit, int $offset, ?string $search = null, ?string $system = null): array
    {
        if (! config('erp.enabled', true)) {
            return ['data' => collect(), 'total' => 0, 'error' => null];
        }

        $source = config('erp.clients_view_source', 'crm');
        if (in_array($system, ['mortgage', 'group_pension'], true) && ! $this->optionalClientsSegmentConfigured($system)) {
            return ['data' => collect(), 'total' => 0, 'error' => null];
        }
        if ($source === 'erp_sync') {
            return $this->getClientsFromCache($limit, $offset, $search, $system);
        }
        if ($source === 'erp_http') {
            // When "All" (no system filter): merge Group Life + Individual Life for proper differentiation
            if (! $system || $system === '') {
                return $this->getClientsForListViewMerged($limit, $offset, $search);
            }

            $result = $this->getClientsFromHttpApi($limit, $offset, $search, null, false, $system);
            // Group Life: when search returns 0, try Individual view (policy may be in either view)
            if (
                $system === 'group'
                && $result['data']->isEmpty()
                && $search && strlen(trim($search)) >= 2
            ) {
                $indResult = $this->getClientsFromHttpApi($limit, 0, $search, null, false, 'individual');
                if ($indResult['data']->isNotEmpty()) {
                    return [
                        'data' => $indResult['data'],
                        'total' => $indResult['total'],
                        'error' => $indResult['error'] ?: null,
                        '_fallback_individual' => true,
                    ];
                }
            }
            return $result;
        }

        $attempts = 0;
        $maxAttempts = 2;

        while (true) {
            try {
                $table = $this->resolveClientsListTable($system);
                $columns = config('erp.clients_list_columns', 'POLICY_NUMBER,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN,PRP_DOB,MATURITY');
                $orderCol = config('erp.clients_list_order', 'PRODUCT');
                $searchCols = array_filter(array_map('trim', explode(',', config('erp.clients_list_search_columns', 'POLICY_NUMBER,POL_PREPARED_BY,INTERMEDIARY,KRA_PIN'))));

                $selectCols = $columns === '*' ? '*' : array_map('trim', explode(',', $columns));
                $query = DB::connection('erp')->table($table);

                if (is_array($selectCols) && ! empty($selectCols)) {
                    $query->select($selectCols);
                } else {
                    $query->selectRaw($columns);
                }

                if ($search && trim($search) !== '' && ! empty($searchCols)) {
                    $term = '%' . trim($search) . '%';
                    $query->where(function ($q) use ($searchCols, $term) {
                        foreach ($searchCols as $col) {
                            $q->orWhere($col, 'like', $term);
                        }
                    });
                }
                // When system=group/individual we use dedicated views (LMS_GROUP_CRM_VIEW / LMS_INDIVIDUAL_CRM_VIEW) - no product filter needed

                // Fetch only 100 rows - skip COUNT(*) which drops connection on large views
                $fetchLimit = min($limit, 100);
                $rows = $query->orderBy($orderCol)->offset($offset)->limit($fetchLimit)->get();
                $data = $rows->map(fn ($row) => $this->mapRowToClientObject((array) $row));

                // No count query - use estimate for pagination (avoids connection drops)
                $total = $data->isEmpty() ? 0 : $offset + $data->count() + 100;

                return ['data' => $data, 'total' => $total, 'error' => null];
            } catch (\Throwable $e) {
                $errMsg = $e->getMessage();
                $isLostConn = (strpos($errMsg, 'ORA-03113') !== false) || (strpos($errMsg, 'Lost connection') !== false) || (strpos($errMsg, 'end-of-file') !== false);
                $attempts++;

                Log::error('ERP clients list failed', [
                    'message' => $errMsg,
                    'table' => config('erp.clients_list_table'),
                    'attempt' => $attempts,
                ]);

                if ($isLostConn && $attempts < $maxAttempts) {
                    DB::purge('erp');
                    usleep(800000);
                    continue;
                }

                return [
                    'data' => collect(),
                    'total' => 0,
                    'error' => 'Oracle connection failed. Ensure the app can reach the Oracle server (10.1.4.101). Try refreshing.',
                ];
            }
        }
    }

    /**
     * Stream keys for Support → Clients → "All" (browse): group, individual, optional mortgage & group_pension.
     *
     * @return list<string>
     */
    protected function mergedAllClientStreams(): array
    {
        $streams = ['group', 'individual'];
        if ($this->optionalClientsSegmentConfigured('mortgage')) {
            $streams[] = 'mortgage';
        }
        if ($this->optionalClientsSegmentConfigured('group_pension')) {
            $streams[] = 'group_pension';
        }

        return $streams;
    }

    /**
     * "All" tab with no search: round-robin across group, individual, mortgage, group_pension (each segment if configured).
     *
     * @return array{data: \Illuminate\Support\Collection, total: int, error: string|null, grand_total: int}
     */
    protected function getClientsForListViewMergedRoundRobin(int $limit, int $offset): array
    {
        $streams = $this->mergedAllClientStreams();
        $n = count($streams);
        $perStreamSkip = [];
        for ($s = 0; $s < $n; $s++) {
            $perStreamSkip[$s] = intdiv($offset + $n - 1 - $s, $n);
        }
        $fetchCount = min(100, max($limit, (int) ceil($limit / $n) + 6));

        $buffers = [];
        $firstError = null;
        foreach ($streams as $idx => $sys) {
            $res = $this->getClientsFromHttpApi($fetchCount, $perStreamSkip[$idx], null, null, false, $sys);
            $buffers[$idx] = $res['data']->values()->all();
            if ($firstError === null && ! empty($res['error'])) {
                $firstError = $res['error'];
            }
        }

        $merged = [];
        for ($g = $offset; $g < $offset + $limit; $g++) {
            $s = $g % $n;
            $localIdx = intdiv($g - $s, $n);
            $bufIdx = $localIdx - $perStreamSkip[$s];
            if ($bufIdx >= 0 && $bufIdx < count($buffers[$s])) {
                $merged[] = $buffers[$s][$bufIdx];
            }
        }
        $data = collect($merged);

        $total = 0;
        foreach ($streams as $sys) {
            $c = $this->getClientsFromHttpApi(1, 0, null, null, true, $sys);
            if (empty($c['error'])) {
                $total += (int) ($c['total'] ?? 0);
            }
        }

        return [
            'data' => $data,
            'total' => $total,
            'grand_total' => $total,
            'error' => $firstError,
        ];
    }

    /**
     * Get clients for "All" filter: merge segments so every configured life system appears (browse + search).
     *
     * @return array{data: \Illuminate\Support\Collection, total: int, error: string|null, grand_total: int}
     */
    protected function getClientsForListViewMerged(int $limit, int $offset, ?string $search): array
    {
        $searchTrim = trim((string) ($search ?? ''));
        if ($searchTrim === '') {
            return $this->getClientsForListViewMergedRoundRobin($limit, $offset);
        }

        // Prefix / family (e.g. GL-GLA-): load pension + mortgage first so Group Life / Individual never hide LMS-only rows.
        $prefixStyle = $this->isPrefixStylePolicySearch($searchTrim);
        $prefixBaseline = collect();
        if ($prefixStyle) {
            foreach (['group_pension', 'mortgage'] as $seg) {
                if (! $this->optionalClientsSegmentConfigured($seg)) {
                    continue;
                }
                $more = $this->getClientsFromHttpApi(100, 0, $searchTrim, null, false, $seg);
                if (empty($more['error']) && $more['data']->isNotEmpty()) {
                    $prefixBaseline = $prefixBaseline->merge($more['data']);
                }
            }
            $prefixBaseline = $this->dedupeClientsByPolicyAndSystem($prefixBaseline);
        }

        $groupOffset = (int) floor($offset / 2);
        $individualOffset = (int) floor(($offset + 1) / 2);
        $groupLimit = (int) (floor(($offset + $limit) / 2) - floor($offset / 2));
        $individualLimit = (int) (floor(($offset + $limit + 1) / 2) - floor(($offset + 1) / 2));

        $groupResult = $this->getClientsFromHttpApi($groupLimit, $groupOffset, $search, null, false, 'group');
        $indResult = $this->getClientsFromHttpApi($individualLimit, $individualOffset, $search, null, false, 'individual');

        $groupData = $groupResult['data']->values()->all();
        $indData = $indResult['data']->values()->all();

        // Interleave: g0, i0, g1, i1, ...
        $merged = [];
        $maxLen = max(count($groupData), count($indData));
        for ($i = 0; $i < $maxLen && count($merged) < $limit; $i++) {
            if ($i < count($groupData)) {
                $merged[] = $groupData[$i];
            }
            if (count($merged) < $limit && $i < count($indData)) {
                $merged[] = $indData[$i];
            }
        }
        $data = collect(array_slice($merged, 0, $limit));

        if ($prefixBaseline->isNotEmpty()) {
            $data = $this->dedupeClientsByPolicyAndSystem($prefixBaseline->merge($data))->values()->take($limit);
        }

        $skipExtraSegmentFetch = $prefixStyle && $prefixBaseline->isNotEmpty();
        if ($searchTrim !== '' && ! $skipExtraSegmentFetch) {
            $dedupe = [];
            foreach ($data as $row) {
                $p = strtolower(trim((string) ($row->policy_number ?? $row->policy_no ?? '')));
                $ls = strtolower(trim((string) ($row->life_system ?? '')));
                if ($p !== '') {
                    $dedupe[$ls.'|'.$p] = true;
                }
            }
            $append = collect();
            foreach (['mortgage', 'group_pension'] as $seg) {
                if (! $this->optionalClientsSegmentConfigured($seg)) {
                    continue;
                }
                $more = $this->getClientsFromHttpApi(100, 0, $searchTrim, null, false, $seg);
                if (! empty($more['error']) || $more['data']->isEmpty()) {
                    continue;
                }
                foreach ($more['data'] as $row) {
                    $p = strtolower(trim((string) ($row->policy_number ?? $row->policy_no ?? '')));
                    $ls = strtolower(trim((string) ($row->life_system ?? $seg)));
                    if ($p === '') {
                        continue;
                    }
                    $k = $ls.'|'.$p;
                    if (isset($dedupe[$k])) {
                        continue;
                    }
                    $dedupe[$k] = true;
                    $append->push($row);
                }
            }
            if ($append->isNotEmpty()) {
                // Policy-style tokens: show mortgage/pension hits first so take(limit) does not drop them.
                $data = $this->searchTermLooksLikePolicyNumber($searchTrim)
                    ? $append->merge($data)->values()->take($limit)
                    : $data->merge($append)->values()->take($limit);
            }
        }

        $groupTotal = (int) ($groupResult['total'] ?? 0);
        $indTotal = (int) ($indResult['total'] ?? 0);
        $extraSegTotal = 0;
        foreach (['mortgage', 'group_pension'] as $seg) {
            if (! $this->optionalClientsSegmentConfigured($seg)) {
                continue;
            }
            $c = $this->getClientsFromHttpApi(1, 0, $searchTrim, null, true, $seg);
            if (empty($c['error'])) {
                $extraSegTotal += (int) ($c['total'] ?? 0);
            }
        }
        // Total rows across segments for this search (pagination / stat); mortgage rows are not double-counted vs $append.
        $total = $groupTotal + $indTotal + $extraSegTotal;
        $grandTotal = $total;

        $error = $groupResult['error'] ?: $indResult['error'];

        return ['data' => $data, 'total' => $total, 'grand_total' => $grandTotal, 'error' => $error];
    }

    /**
     * Fetch product names for maturities filter dropdown (from ERP API /clients?products=1).
     */
    public function getProductsForMaturitiesFilter(): array
    {
        $url = rtrim((string) config('erp.clients_http_url'), '/');
        if ($url === '') {
            return [];
        }
        try {
            $sep = str_contains($url, '?') ? '&' : '?';
            $response = Http::timeout(15)->get($url . $sep . 'products=1');
            if (! $response->successful()) {
                return [];
            }
            $body = $response->json();
            $products = $body['products'] ?? [];
            return is_array($products) ? $products : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Fetch maturing policies from ERP HTTP API /clients/maturities endpoint.
     * Falls back to filtering /clients when maturities endpoint returns 404.
     */
    public function getMaturingPoliciesFromHttpApi(string $from, string $to, ?string $product = null): array
    {
        $baseUrl = rtrim(config('erp.clients_http_url', ''), '/');
        $maturitiesUrl = config('erp.maturities_http_url', '');
        if (empty($maturitiesUrl)) {
            if (empty($baseUrl)) {
                return ['data' => [], 'error' => 'ERP_CLIENTS_HTTP_URL not set.'];
            }
            $base = preg_replace('#/clients.*$#', '', $baseUrl);
            $maturitiesUrl = rtrim($base ?: $baseUrl, '/') . '/maturities';
        }
        $params = ['from' => $from, 'to' => $to];
        if (! empty(trim($product ?? ''))) {
            $params['product'] = trim($product);
        }
        try {
            $response = Http::timeout(30)->get($maturitiesUrl, $params);
            if (! $response->successful()) {
                if ($response->status() === 404) {
                    return $this->getMaturingPoliciesFromClientsFallback($from, $to, $product);
                }
                $body = $response->json();
                $err = $body['error'] ?? 'Maturities API error: ' . $response->status();
                Log::warning('ERP maturities API failed', ['url' => $maturitiesUrl, 'error' => $err]);
                return ['data' => [], 'error' => $err];
            }
            $body = $response->json();
            $rows = $body['data'] ?? [];
            if (! is_array($rows)) {
                $rows = [];
            }
            $data = collect($rows)->map(function ($row) {
                $r = is_array($row) ? $row : (array) $row;
                return (object) [
                    'policy_number' => $r['policy_number'] ?? $r['policy_no'] ?? null,
                    'maturity' => $r['maturity'] ?? $r['maturity_date'] ?? null,
                    'life_assured' => $r['life_assured'] ?? $r['life_assur'] ?? null,
                    'life_assur' => $r['life_assur'] ?? $r['life_assured'] ?? null,
                    'product' => $r['product'] ?? null,
                    'pol_prepared_by' => $r['pol_prepared_by'] ?? null,
                    'intermediary' => $r['intermediary'] ?? null,
                    'phone_no' => $r['phone_no'] ?? $r['mobile'] ?? null,
                    'email_adr' => $r['email_adr'] ?? $r['email'] ?? null,
                    ];
            })->all();
            return ['data' => $data, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('ERP maturities API request failed', ['error' => $e->getMessage()]);
            return $this->getMaturingPoliciesFromClientsFallback($from, $to, $product);
        }
    }

    /**
     * Fallback: fetch from /clients in batches and filter by maturity date.
     */
    protected function getMaturingPoliciesFromClientsFallback(string $from, string $to, ?string $product): array
    {
        $maturing = [];
        $fetched = 0;
        $maxBatches = 100;
        for ($offset = 0; $offset < $maxBatches * 100; $offset += 100) {
            $result = $this->getClientsFromHttpApi(100, $offset, null, 15, false, 'individual');
            if (! empty($result['error']) || empty($result['data'])) {
                break;
            }
            $data = $result['data'] instanceof \Illuminate\Support\Collection ? $result['data']->all() : (array) $result['data'];
            foreach ($data as $client) {
                $client = is_array($client) ? (object) $client : $client;
                $m = $client->maturity ?? $client->maturity_date ?? null;
                if (! $m) {
                    continue;
                }
                $mStr = is_object($m) && method_exists($m, 'format') ? $m->format('Y-m-d') : substr((string) $m, 0, 10);
                if ($mStr && $mStr >= $from && $mStr <= $to) {
                    $prod = trim($client->product ?? '');
                    if ($product && trim($product) !== '' && $prod !== trim($product)) {
                        continue;
                    }
                    $maturing[] = (object) [
                        'policy_number' => $client->policy_no ?? $client->policy_number ?? null,
                        'maturity' => $mStr,
                        'life_assured' => $client->life_assured ?? $client->life_assur ?? null,
                        'life_assur' => $client->life_assur ?? $client->life_assured ?? null,
                        'product' => $prod,
                        'pol_prepared_by' => $client->pol_prepared_by ?? null,
                        'intermediary' => $client->intermediary ?? null,
                        'phone_no' => $client->phone_no ?? $client->mobile ?? null,
                        'email_adr' => $client->email_adr ?? $client->email ?? null,
                    ];
                }
            }
            $fetched += count($data);
            if (count($data) < 100) {
                break;
            }
        }
        return ['data' => $maturing, 'error' => null];
    }

    /**
     * Get clients from ERP HTTP API (ERP_CLIENTS_HTTP_URL).
     * Fast: no Oracle, no cache; direct API fetch. Supports limit, offset, search.
     *
     * @param  ?string  $mendrRenewalOn  ISO date (Y-m-d); mortgage only. Exact day (API mendr_renewal_on) if from/to omitted.
     * @param  ?string  $mendrRenewalFrom  ISO start date inclusive (API mendr_renewal_from).
     * @param  ?string  $mendrRenewalTo  ISO end date inclusive (API mendr_renewal_to). From/to together filter a range.
     * @param  bool  $mortgageUpcomingRenewalsOnly  When true with system=mortgage, require from/to or mendrRenewalWindowDays. Sends mortgage_upcoming_renewals=1.
     * @param  ?int  $mendrRenewalWindowDays  With system=mortgage: days from Oracle TRUNC(SYSDATE) for MENDR_RENEWAL_DATE (API mendr_window_days). Preferred for renewal dashboard.
     */
    public function getClientsFromHttpApi(int $limit, int $offset, ?string $search = null, ?int $timeoutSeconds = null, bool $countOnly = false, ?string $system = null, ?string $mendrRenewalOn = null, ?string $mendrRenewalFrom = null, ?string $mendrRenewalTo = null, bool $mortgageUpcomingRenewalsOnly = false, ?int $mendrRenewalWindowDays = null): array
    {
        try {
            if (in_array($system, ['mortgage', 'group_pension'], true) && ! $this->optionalClientsSegmentConfigured($system)) {
                return ['data' => collect(), 'total' => 0, 'error' => null];
            }
            $url = config('erp.clients_http_url');
            if (empty($url)) {
                return ['data' => collect(), 'total' => 0, 'error' => 'ERP_CLIENTS_HTTP_URL is not set.'];
            }

            $renewalFromPre = $mendrRenewalFrom !== null && trim((string) $mendrRenewalFrom) !== '' ? trim((string) $mendrRenewalFrom) : '';
            $renewalToPre = $mendrRenewalTo !== null && trim((string) $mendrRenewalTo) !== '' ? trim((string) $mendrRenewalTo) : '';
            $rangeOkPre = $system === 'mortgage'
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewalFromPre)
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewalToPre);
            $windowDaysPre = $mendrRenewalWindowDays !== null ? max(1, min(120, (int) $mendrRenewalWindowDays)) : null;
            if ($mortgageUpcomingRenewalsOnly && $system === 'mortgage' && ! $rangeOkPre && $windowDaysPre === null) {
                return [
                    'data' => collect(),
                    'total' => 0,
                    'error' => 'Upcoming renewals require mendr_window_days or a valid mendr_renewal_from / mendr_renewal_to range. This list cannot show all mortgage policies.',
                ];
            }

            $params = ['limit' => min($limit, 100), 'offset' => $offset];
            if ($countOnly) {
                $params['count_only'] = '1';
            }
            $searchTrimmed = $search ? trim($search) : '';
            if ($searchTrimmed !== '') {
                $params['search'] = $searchTrimmed;
                // Group / Mortgage / Group pension: full policy ids also send policy= for exact match.
                // Do not send policy= for prefixes (e.g. GL-GLA-) — exact equality returns no rows.
                if (
                    in_array($system, ['group', 'mortgage', 'group_pension'], true)
                    && $this->shouldSendExactPolicyParamForSegmentSearch($searchTrimmed)
                ) {
                    $params['policy'] = $searchTrimmed;
                }
            }
            if ($system && in_array($system, ['group', 'individual', 'mortgage', 'group_pension'], true)) {
                $params['system'] = $system;
            }
            $renewalFrom = $mendrRenewalFrom !== null && trim((string) $mendrRenewalFrom) !== '' ? trim((string) $mendrRenewalFrom) : '';
            $renewalTo = $mendrRenewalTo !== null && trim((string) $mendrRenewalTo) !== '' ? trim((string) $mendrRenewalTo) : '';
            $renewalRangeOk = $system === 'mortgage'
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewalFrom)
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewalTo);
            $windowDays = $mendrRenewalWindowDays !== null ? max(1, min(120, (int) $mendrRenewalWindowDays)) : null;
            if ($windowDays !== null && $system === 'mortgage') {
                $params['mendr_window_days'] = $windowDays;
            }
            // Always send from/to when valid — APIs that ignore mendr_window_days still must filter (avoid full mortgage list).
            if ($renewalRangeOk) {
                $params['mendr_renewal_from'] = $renewalFrom;
                $params['mendr_renewal_to'] = $renewalTo;
            } elseif ($system === 'mortgage' && $windowDays === null) {
                $renewalOn = $mendrRenewalOn !== null && trim((string) $mendrRenewalOn) !== '' ? trim((string) $mendrRenewalOn) : '';
                if ($renewalOn !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewalOn)) {
                    $params['mendr_renewal_on'] = $renewalOn;
                }
            }
            if ($mortgageUpcomingRenewalsOnly && $system === 'mortgage') {
                $params['mortgage_upcoming_renewals'] = '1';
            }

            $timeout = $timeoutSeconds ?? (($search && trim($search) !== '') ? 30 : 15);
            $response = Http::timeout($timeout)->get($url, $params);

            if (! $response->successful()) {
                $body = $response->json();
                $apiError = $body['error'] ?? null;
                $errMsg = $apiError ? "ERP API: {$apiError}" : 'ERP API error: ' . $response->status();
                return ['data' => collect(), 'total' => 0, 'error' => $errMsg];
            }

            $body = $response->json();
            $rows = $body['data'] ?? $body['clients'] ?? $body['results'] ?? (is_array($body) ? $body : []);
            if (! is_array($rows)) {
                $rows = [];
            }

            $total = (int) ($body['total'] ?? $body['count'] ?? count($rows) + $offset);
            $data = collect($rows)->map(fn ($row) => $this->mapHttpRowToClientObject(is_array($row) ? $row : (array) $row, $system));

            $apiMsg = $body['error'] ?? $body['message'] ?? null;
            if (is_string($apiMsg) && $apiMsg !== '' && $data->isEmpty()) {
                return ['data' => $data, 'total' => $total, 'error' => 'ERP API: '.$apiMsg];
            }

            return ['data' => $data, 'total' => $total, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('ERP HTTP clients fetch failed', ['message' => $e->getMessage()]);

            return ['data' => collect(), 'total' => 0, 'error' => 'ERP API failed: ' . $e->getMessage()];
        }
    }

    /**
     * Product from prod_desc/product. Never return agent/intermediary as product.
     */
    /** Product from prod_desc (PROD_DESC column in view). */
    protected function resolveProductExcludingAgent(array $row): string
    {
        return trim((string) ($row['prod_desc'] ?? $row['PRODUCT'] ?? $row['product'] ?? $row['prod_sht_desc'] ?? $row['scheme_name'] ?? ''));
    }

    /**
     * True if value looks like receipt (1/HO/100024), not policy (GEMPPP0070).
     */
    protected function isReceiptFormat(?string $val): bool
    {
        if ($val === null || $val === '') {
            return false;
        }
        return (bool) preg_match('/^\d+\/[A-Za-z]+\/\d+$/', trim((string) $val));
    }

    /** KRA PIN format: letter + 9 digits + letter (e.g. A006533554X). Do not use as policy. */
    protected function isPinFormat(?string $val): bool
    {
        if ($val === null || trim((string) $val) === '') {
            return false;
        }
        return (bool) preg_match('/^[A-Z]\d{9}[A-Z]$/i', trim((string) $val));
    }

    /**
     * When true, HTTP query may include policy= for exact match on POL_POLICY_NO etc.
     * Prefix / family searches (e.g. "GL-GLA-") must NOT set policy= — the API would use equality
     * and return zero rows; use search= + LIKE only until the term includes a digit (full policy id).
     */
    protected function shouldSendExactPolicyParamForSegmentSearch(string $term): bool
    {
        $t = trim($term);
        if ($t === '' || $this->isReceiptFormat($t) || $this->isPinFormat($t)) {
            return false;
        }
        if (! preg_match('/^[A-Za-z0-9\-\/]+$/', $t)) {
            return false;
        }

        return (bool) preg_match('/\d/', $t);
    }

    /** Policy- or prefix-shaped tokens: surface mortgage/pension rows first on All-search. */
    protected function searchTermLooksLikePolicyNumber(string $term): bool
    {
        $t = trim($term);
        if ($t === '' || $this->isReceiptFormat($t) || $this->isPinFormat($t)) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9\-\/]+$/', $t);
    }

    /**
     * Map HTTP API row to client object (supports snake_case or camelCase keys).
     * When $system=group|individual, force life_system since we queried that view.
     * For Group: policy = IPOL_POLICY_NO (reject receipt format); product = PROD_DESC only.
     */
    protected function mapHttpRowToClientObject(array $row, ?string $system = null): object
    {
        $get = fn ($keys) => collect($keys)->map(fn ($k) => $row[$k] ?? null)->first(fn ($v) => $v !== null && $v !== '');
        $policyKeys = ['policy_number', 'policyNumber', 'POLICY_NUMBER', 'policy_no', 'ipol_policy_no', 'pol_policy_no', 'contract_no', 'scheme_no'];
        $policyNo = '';
        foreach ($policyKeys as $k) {
            $v = $row[$k] ?? null;
            $str = trim((string) ($v ?? ''));
            if ($str !== '' && ! $this->isReceiptFormat($str) && ! $this->isPinFormat($str)) {
                $policyNo = $str;
                break;
            }
        }
        if ($policyNo === '') {
            foreach ($policyKeys as $k) {
                $v = $row[$k] ?? null;
                $str = trim((string) ($v ?? ''));
                if ($str !== '' && ! $this->isPinFormat($str)) {
                    $policyNo = $str;
                    break;
                }
            }
        }
        $receipt = $get(['receipt_number', 'grct_receipt_no']);
        if ($receipt && $policyNo === trim((string) $receipt)) {
            $policyNo = '';
        }
        $lifeAssur = $get(['life_assur', 'life_assured', 'lifeAssur', 'lifeAssured', 'mem_surname', 'member_name', 'memberName', 'client_name', 'clientName']) ?? '';
        $polPreparedBy = $get(['pol_prepared_by', 'polPreparedBy', 'bra_manager', 'unit_manar']) ?? '';
        $intermediary = $get(['intermediary', 'intermediary_name', 'agency', 'agn_name', 'agnName']) ?? '';
        $productKeys = in_array($system, ['group', 'mortgage', 'group_pension'], true)
            ? ['prod_desc', 'product', 'PRODUCT', 'prod_sht_desc', 'scheme_name', 'schemeName']
            : ['PRODUCT', 'product', 'prod_desc', 'scheme_name', 'schemeName', 'prod_sht_desc'];
        $product = $get($productKeys) ?? null;
        $kraPin = $get(['kra_pin', 'kraPin']) ?? '';
        $prpDob = $get(['prp_dob', 'prpDob']) ?? null;
        $maturity = $get(['maturity', 'maturity_date', 'maturityDate']) ?? null;
        $mendrKeys = ['mendr_renewal_date', 'mendrRenewalDate', 'MENDR_RENEWAL_DATE', 'renewal_date', 'next_renewal_date', 'RENEWAL_DATE'];
        if ($system === 'mortgage') {
            $mendrKeys = array_merge($mendrKeys, ['maturity_date', 'maturityDate', 'maturity']);
        }
        $mendrRenewalDate = $get($mendrKeys) ?? null;
        $effectiveDate = $get(['effective_date', 'effectiveDate', 'authorization_date']) ?? null;
        $paidMatAmt = $get(['bal', 'BAL', 'paid_mat_amt', 'paidMatAmt', 'production_amt']) ?? null;
        $checkoff = $get(['checkoff']) ?? null;
        $emailAdr = $get(['email_adr', 'emailAdr', 'client_email', 'mem_email']) ?? null;
        $idNo = $get(['id_no', 'idNo', 'ID_NO']) ?? null;
        $phoneNo = $get(['phone_no', 'phoneNo', 'phone', 'mobile', 'client_contact', 'mem_teleph']) ?? null;
        $status = $get(['mendr_status', 'mendrStatus', 'MENDR_STATUS', 'endr_status', 'endrStatus', 'ENDR_STATUS', 'status', 'STATUS', 'policy_status', 'POLICY_STATUS']) ?? '';

        $clientName = trim($lifeAssur) ?: trim("{$polPreparedBy} {$intermediary}");
        $parts = explode(' ', $clientName, 2);

        $lifeSystem = in_array($system, ['group', 'individual', 'mortgage', 'group_pension'], true)
            ? $system
            : $this->getLifeSystemFromProduct($product);

        return (object) [
            'contactid' => $policyNo ?: ('erp-' . md5(json_encode($row))),
            'firstname' => $parts[0] ?? $clientName,
            'lastname' => $parts[1] ?? '',
            'policy_no' => $policyNo,
            'policy_number' => $policyNo,
            'client_name' => $clientName,
            'life_assur' => $lifeAssur,
            'pol_prepared_by' => $polPreparedBy,
            'intermediary' => $intermediary,
            'product' => $product,
            'life_system' => $lifeSystem,
            'status' => $status,
            'kra_pin' => $kraPin,
            'prp_dob' => $prpDob,
            'maturity' => $maturity,
            'mendr_renewal_date' => $mendrRenewalDate,
            'effective_date' => $effectiveDate,
            'paid_mat_amt' => $paidMatAmt,
            'bal' => $get(['bal', 'BAL']) ?? $paidMatAmt,
            'checkoff' => $checkoff,
            'email_adr' => personal_email_only($emailAdr),
            'id_no' => $idNo,
            'phone_no' => $phoneNo,
            'mobile' => $phoneNo,
            'phone' => $phoneNo,
            '_erp_source' => true,
        ];
    }

    /**
     * Get clients from local cache (erp_clients_cache).
     * Used when CLIENTS_VIEW_SOURCE=erp_sync to avoid direct Oracle connection.
     */
    public function getClientsFromCache(int $limit, int $offset, ?string $search = null, ?string $system = null): array
    {
        try {
            $query = DB::connection(config('database.default'))->table('erp_clients_cache');
            $searchCols = ['policy_number', 'pol_prepared_by', 'intermediary', 'kra_pin', 'product', 'id_no', 'phone_no'];
            if (Schema::hasColumn('erp_clients_cache', 'email_adr')) {
                $searchCols[] = 'email_adr';
            }

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($searchCols, $term) {
                    foreach ($searchCols as $col) {
                        $q->orWhere($col, 'like', $term);
                    }
                });
            }
            if (in_array($system, ['group', 'individual', 'mortgage', 'group_pension'], true)) {
                $this->applyLifeSystemFilter($query, $system, 'product');
            }

            // Use live count when searching or when filtering by system.
            // Otherwise cache total for performance (All view with no search).
            $useCachedTotal = ($search === null || trim($search) === '')
                && ! in_array($system, ['group', 'individual', 'mortgage', 'group_pension'], true);
            $total = $useCachedTotal
                ? \Illuminate\Support\Facades\Cache::remember('erp_clients_cache_total', 300, fn () => DB::table('erp_clients_cache')->count())
                : $query->count();

            $rows = $query->orderBy('policy_number')->orderBy('product')->offset($offset)->limit(min($limit, 100))->get();

            $data = $rows->map(fn ($row) => $this->mapCacheRowToClientObject((array) $row));

            return ['data' => $data, 'total' => $total, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('ERP clients cache read failed', ['message' => $e->getMessage()]);

            return ['data' => collect(), 'total' => 0, 'error' => 'Cache read failed: ' . $e->getMessage()];
        }
    }

    /**
     * Map cache row (snake_case) to client object for Customers table.
     */
    protected function mapCacheRowToClientObject(array $row): object
    {
        $clientName = trim($row['life_assur'] ?? '') ?: trim(($row['pol_prepared_by'] ?? '') . ' ' . ($row['intermediary'] ?? ''));
        $policyNo = $row['policy_number'] ?? '';
        $parts = explode(' ', $clientName, 2);

        $email = personal_email_only($row['email_adr'] ?? null) ?? '';

        return (object) [
            'contactid' => $policyNo ?: ('erp-' . md5(json_encode($row))),
            'firstname' => $parts[0] ?? $clientName,
            'lastname' => $parts[1] ?? '',
            'client_name' => $clientName,
            'email' => $email,
            'mobile' => '',
            'phone' => '',
            'owner_first' => null,
            'owner_last' => null,
            'owner_username' => null,
            'policy_no' => $policyNo,
            'product' => $row['product'] ?? null,
            'pol_prepared_by' => $row['pol_prepared_by'] ?? null,
            'intermediary' => $row['intermediary'] ?? null,
            'life_system' => $this->getLifeSystemFromProduct($row['product'] ?? null),
            'status' => $row['status'] ?? null,
            'kra_pin' => $row['kra_pin'] ?? null,
            'prp_dob' => $row['prp_dob'] ?? null,
            'maturity' => $row['maturity'] ?? $row['maturity_date'] ?? null,
            'paid_mat_amt' => $row['paid_mat_amt'] ?? null,
            'checkoff' => $row['checkoff'] ?? null,
            'effective_date' => $row['effective_date'] ?? null,
            'email_adr' => $email ?: null,
            'id_no' => $row['id_no'] ?? null,
            'phone_no' => $row['phone_no'] ?? $row['mobile'] ?? $row['phone'] ?? null,
            'mobile' => $row['phone_no'] ?? $row['mobile'] ?? $row['phone'] ?? '',
            'phone' => $row['phone_no'] ?? $row['mobile'] ?? $row['phone'] ?? '',
            '_erp_source' => true,
        ];
    }

    /**
     * Map ERP row to object compatible with Customers view (firstname, lastname, email, mobile, contactid).
     */
    protected function mapRowToClientObject(array $row): object
    {
        $mapped = $this->mapRow($row);
        $name = $mapped['name'] ?? trim(($mapped['first_name'] ?? '') . ' ' . ($mapped['last_name'] ?? ''));
        $name = $name ?: ($mapped['pol_prepared_by'] ?? $mapped['intermediary'] ?? '');
        $first = $mapped['first_name'] ?? explode(' ', $name, 2)[0] ?? '';
        $last = $mapped['last_name'] ?? (explode(' ', $name, 2)[1] ?? '');
        $policyNo = $mapped['policy_no'] ?? $mapped['policy_number'] ?? '';

        $email = personal_email_only($mapped['email'] ?? null) ?? '';

        return (object) [
            'contactid' => $policyNo ?: ('erp-' . md5(json_encode($row))),
            'firstname' => $first ?: $name,
            'lastname' => $last,
            'email' => $email,
            'mobile' => $mapped['mobile'] ?? $mapped['phone'] ?? '',
            'phone' => $mapped['phone'] ?? $mapped['mobile'] ?? '',
            'owner_first' => null,
            'owner_last' => null,
            'owner_username' => null,
            'policy_no' => $policyNo,
            'product' => $mapped['product'] ?? ($row['PRODUCT'] ?? null),
            'life_system' => $this->getLifeSystemFromProduct($mapped['product'] ?? $row['PRODUCT'] ?? null),
            '_erp_source' => true,
        ];
    }

    /**
     * Check if ERP connection is configured and available.
     */
    public function isConfigured(): bool
    {
        if (! config('erp.enabled', true)) {
            return false;
        }

        return ! empty(config('database.connections.erp.username'))
            && ! empty(config('database.connections.erp.password'))
            && (! empty(config('database.connections.erp.host')) || ! empty(config('database.connections.erp.tns')));
    }
}
