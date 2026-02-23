<?php

namespace App\Services;

use Illuminate\Database\LostConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErpClientService
{
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

        // Use HTTP API when erp_http
        if ($viewSource === 'erp_http') {
            $result = $this->getClientsFromHttpApi($limit, 0, $term);
            $data = $result['data']->map(fn ($obj) => (array) $obj)->map(fn ($r) => $this->mapClientObjectToSearchResult($r))->values()->all();

            return ['data' => $data, 'total' => $result['total'], 'error' => $result['error']];
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

    /** Map cached/HTTP client object to search result format (policy_no, name, maturity, etc.) */
    protected function mapClientObjectToSearchResult(array $row): array
    {
        $get = fn (array $keys) => collect($keys)->map(fn ($k) => $row[$k] ?? null)->first(fn ($v) => $v !== null);
        $policyNo = (string) ($get(['policy_no', 'policy_number', 'POLICY_NUMBER']) ?? '');
        $lifeAssured = (string) ($get(['life_assur', 'life_assured', 'lifeAssur', 'lifeAssured']) ?? '');
        $name = trim($lifeAssured) ?: trim(($row['pol_prepared_by'] ?? '') . ' ' . ($row['intermediary'] ?? '')) ?: trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
        $email = $get(['email', 'email_adr', 'emailAdr']);
        $mobile = $get(['mobile', 'phone', 'phone_no', 'phoneNo']);

        return [
            'policy_no' => $policyNo,
            'policy_number' => $policyNo,
            'name' => $name,
            'client_name' => $name,
            'life_assur' => $lifeAssured,
            'life_assured' => $lifeAssured,
            'pol_prepared_by' => $row['pol_prepared_by'] ?? '',
            'intermediary' => $row['intermediary'] ?? '',
            'product' => $row['product'] ?? '',
            'status' => $row['status'] ?? '',
            'kra_pin' => $get(['kra_pin', 'kraPin']) ?? '',
            'id_no' => $get(['id_no', 'idNo', 'ID_NO']),
            'phone_no' => $get(['phone_no', 'phoneNo', 'phone', 'mobile']),
            'prp_dob' => $get(['prp_dob', 'prpDob', 'PRP_DOB']),
            'maturity' => $get(['maturity', 'maturity_date', 'maturityDate', 'MATURITY_DATE']),
            'effective_date' => $get(['effective_date', 'effectiveDate', 'EFFECTIVE_DATE']),
            'paid_mat_amt' => $get(['bal', 'BAL', 'paid_mat_amt', 'paidMatAmt', 'PAID_MAT_AMT']),
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

        $policyNo = $contact->policy_number ?? $contact->cf_860 ?? $contact->cf_856 ?? $contact->cf_852 ?? $contact->cf_872 ?? null;
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
                $lostConnection = str_contains($errMsg, 'Lost connection') || str_contains($errMsg, 'no reconnector');
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
                $sep = str_contains($url, '?') ? '&' : '?';
                $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url . $sep . 'policy=' . urlencode($policyNumber) . '&limit=1');
                if (! $response->successful()) {
                    return null;
                }
                $body = $response->json();
                $rows = $body['data'] ?? $body['clients'] ?? [];
                $row = is_array($rows) && isset($rows[0]) ? $rows[0] : null;
                if (! $row) {
                    return null;
                }

                $row = is_array($row) ? $row : (array) $row;
                $mapped = $this->mapClientObjectToSearchResult($row);
                $returned = trim((string) ($mapped['policy_no'] ?? $mapped['policy_number'] ?? ''));

                // Reject wrong client - API may return first row when filter fails
                if ($returned !== '' && $returned !== trim($policyNumber)) {
                    return null;
                }

                // Merge: keep all API keys, overlay mapped values (never overwrite API data with null)
                $merged = $row;
                foreach ($mapped as $k => $v) {
                    if ($v !== null && $v !== '') {
                        $merged[$k] = $v;
                    }
                }
                // Ensure maturity is set from maturity_date when API uses that key
                if (empty($merged['maturity']) && ! empty($merged['maturity_date'])) {
                    $merged['maturity'] = $merged['maturity_date'];
                }
                return $merged;
            }

            if (config('erp.clients_view_source') === 'erp_sync') {
                $row = DB::table('erp_clients_cache')->where('policy_number', $policyNumber)->first();
                if (!$row) {
                    return null;
                }

                return $this->mapClientObjectToSearchResult((array) $row);
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

            return $this->mapRow((array) $row);
        } catch (\Throwable $e) {
            Log::error('ERP policy details failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get total clients count (for dashboard). Uses same source as clients list.
     */
    public function getClientsCount(int $timeoutSeconds = 15): ?int
    {
        if (! config('erp.enabled', true)) {
            return null;
        }
        $source = config('erp.clients_view_source', 'crm');
        if ($source === 'erp_http') {
            $result = $this->getClientsFromHttpApi(1, 0, null, $timeoutSeconds);
            return $result['error'] ? null : (int) $result['total'];
        }
        if ($source === 'erp_sync') {
            $result = $this->getClientsFromCache(1, 0);
            return $result['error'] ? null : (int) $result['total'];
        }
        return null;
    }

    /**
     * Get clients for Support > Customers list.
     * Source: erp = live Oracle, erp_sync = cached table.
     *
     * @return array{data: \Illuminate\Support\Collection, total: int, error: string|null}
     */
    public function getClientsForListView(int $limit, int $offset, ?string $search = null): array
    {
        if (! config('erp.enabled', true)) {
            return ['data' => collect(), 'total' => 0, 'error' => null];
        }

        $source = config('erp.clients_view_source', 'crm');
        if ($source === 'erp_sync') {
            return $this->getClientsFromCache($limit, $offset, $search);
        }
        if ($source === 'erp_http') {
            return $this->getClientsFromHttpApi($limit, $offset, $search);
        }

        $attempts = 0;
        $maxAttempts = 2;

        while (true) {
            try {
                $table = config('erp.clients_list_table', config('erp.clients_table', 'CLIENTS'));
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

                // Fetch only 100 rows - skip COUNT(*) which drops connection on large views
                $fetchLimit = min($limit, 100);
                $rows = $query->orderBy($orderCol)->offset($offset)->limit($fetchLimit)->get();
                $data = $rows->map(fn ($row) => $this->mapRowToClientObject((array) $row));

                // No count query - use estimate for pagination (avoids connection drops)
                $total = $data->isEmpty() ? 0 : $offset + $data->count() + 100;

                return ['data' => $data, 'total' => $total, 'error' => null];
            } catch (\Throwable $e) {
                $errMsg = $e->getMessage();
                $isLostConn = str_contains($errMsg, 'ORA-03113') || str_contains($errMsg, 'Lost connection') || str_contains($errMsg, 'end-of-file');
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
     * Get clients from ERP HTTP API (ERP_CLIENTS_HTTP_URL).
     * Fast: no Oracle, no cache; direct API fetch. Supports limit, offset, search.
     */
    public function getClientsFromHttpApi(int $limit, int $offset, ?string $search = null, ?int $timeoutSeconds = null): array
    {
        try {
            $url = config('erp.clients_http_url');
            if (empty($url)) {
                return ['data' => collect(), 'total' => 0, 'error' => 'ERP_CLIENTS_HTTP_URL is not set.'];
            }

            $params = ['limit' => min($limit, 100), 'offset' => $offset];
            if ($search && trim($search) !== '') {
                $params['search'] = trim($search);
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
            $data = collect($rows)->map(fn ($row) => $this->mapHttpRowToClientObject(is_array($row) ? $row : (array) $row));

            return ['data' => $data, 'total' => $total, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('ERP HTTP clients fetch failed', ['message' => $e->getMessage()]);

            return ['data' => collect(), 'total' => 0, 'error' => 'ERP API failed: ' . $e->getMessage()];
        }
    }

    /**
     * Map HTTP API row to client object (supports snake_case or camelCase keys).
     */
    protected function mapHttpRowToClientObject(array $row): object
    {
        $get = fn ($keys) => $row[$keys[0]] ?? $row[$keys[1] ?? ''] ?? $row[$keys[2] ?? ''] ?? $row[$keys[3] ?? ''] ?? null;
        $policyNo = trim((string) ($get(['policy_number', 'policyNumber', 'POLICY_NUMBER', 'policy_no']) ?? ''));
        $lifeAssur = $get(['life_assur', 'life_assured', 'lifeAssur', 'lifeAssured']) ?? '';
        $polPreparedBy = $get(['pol_prepared_by', 'polPreparedBy']) ?? '';
        $product = $get(['product']) ?? null;
        $intermediary = $get(['intermediary']) ?? '';
        $status = $get(['status']) ?? null;
        $kraPin = $get(['kra_pin', 'kraPin']) ?? '';
        $prpDob = $get(['prp_dob', 'prpDob']) ?? null;
        $maturity = $get(['maturity', 'maturity_date', 'maturityDate']) ?? null;
        $effectiveDate = $get(['effective_date', 'effectiveDate']) ?? null;
        $paidMatAmt = $get(['bal', 'BAL', 'paid_mat_amt', 'paidMatAmt']) ?? null;
        $checkoff = $get(['checkoff']) ?? null;
        $emailAdr = $get(['email_adr', 'emailAdr']) ?? null;
        $idNo = $get(['id_no', 'idNo', 'ID_NO']) ?? null;
        $phoneNo = $get(['phone_no', 'phoneNo', 'phone', 'mobile']) ?? null;

        $clientName = trim($lifeAssur) ?: trim("{$polPreparedBy} {$intermediary}");
        $parts = explode(' ', $clientName, 2);

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
            'status' => $status,
            'kra_pin' => $kraPin,
            'prp_dob' => $prpDob,
            'maturity' => $maturity,
            'effective_date' => $effectiveDate,
            'paid_mat_amt' => $paidMatAmt,
            'bal' => $get(['bal', 'BAL']) ?? $paidMatAmt,
            'checkoff' => $checkoff,
            'email_adr' => $emailAdr,
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
    public function getClientsFromCache(int $limit, int $offset, ?string $search = null): array
    {
        try {
            $query = DB::connection(config('database.default'))->table('erp_clients_cache');
            $searchCols = ['policy_number', 'pol_prepared_by', 'intermediary', 'kra_pin', 'product', 'id_no', 'phone_no'];

            if ($search && trim($search) !== '') {
                $term = '%' . trim($search) . '%';
                $query->where(function ($q) use ($searchCols, $term) {
                    foreach ($searchCols as $col) {
                        $q->orWhere($col, 'like', $term);
                    }
                });
            }

            // Cache total count when not searching (avoids repeated COUNT on large table)
            $total = $search && trim($search) !== ''
                ? $query->count()
                : \Illuminate\Support\Facades\Cache::remember('erp_clients_cache_total', 300, fn () => DB::table('erp_clients_cache')->count());

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

        return (object) [
            'contactid' => $policyNo ?: ('erp-' . md5(json_encode($row))),
            'firstname' => $parts[0] ?? $clientName,
            'lastname' => $parts[1] ?? '',
            'client_name' => $clientName,
            'email' => $row['email_adr'] ?? '',
            'mobile' => '',
            'phone' => '',
            'owner_first' => null,
            'owner_last' => null,
            'owner_username' => null,
            'policy_no' => $policyNo,
            'product' => $row['product'] ?? null,
            'pol_prepared_by' => $row['pol_prepared_by'] ?? null,
            'intermediary' => $row['intermediary'] ?? null,
            'status' => $row['status'] ?? null,
            'kra_pin' => $row['kra_pin'] ?? null,
            'prp_dob' => $row['prp_dob'] ?? null,
            'maturity' => $row['maturity'] ?? $row['maturity_date'] ?? null,
            'paid_mat_amt' => $row['paid_mat_amt'] ?? null,
            'checkoff' => $row['checkoff'] ?? null,
            'effective_date' => $row['effective_date'] ?? null,
            'email_adr' => $row['email_adr'] ?? null,
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

        return (object) [
            'contactid' => $policyNo ?: ('erp-' . md5(json_encode($row))),
            'firstname' => $first ?: $name,
            'lastname' => $last,
            'email' => $mapped['email'] ?? '',
            'mobile' => $mapped['mobile'] ?? $mapped['phone'] ?? '',
            'phone' => $mapped['phone'] ?? $mapped['mobile'] ?? '',
            'owner_first' => null,
            'owner_last' => null,
            'owner_username' => null,
            'policy_no' => $policyNo,
            'product' => $mapped['product'] ?? ($row['PRODUCT'] ?? null),
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
