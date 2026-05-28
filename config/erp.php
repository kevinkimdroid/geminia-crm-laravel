<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ERP Enabled
    |--------------------------------------------------------------------------
    |
    | Set to false to disable Oracle ERP integration (e.g. when credentials
    | are invalid or Oracle is unreachable). Policies tab and Serve Client
    | will show a disabled message instead of connection errors.
    |
    */

    /*
    | Keep ERP enabled for erp_http / erp_sync even when OCI8 is not installed.
    | Only direct Oracle mode (CLIENTS_VIEW_SOURCE=erp) requires OCI8.
    */
    'enabled' => (function () {
        $enabled = (bool) env('ERP_ENABLED', true);
        if (! $enabled) {
            return false;
        }

        $viewSource = (string) env('CLIENTS_VIEW_SOURCE', 'crm');
        if (in_array($viewSource, ['erp_http', 'erp_sync', 'crm'], true)) {
            return true;
        }

        return extension_loaded('oci8');
    })(),

    /*
    |--------------------------------------------------------------------------
    | ERP Clients Source
    |--------------------------------------------------------------------------
    |
    | How to fetch clients from the ERP (Oracle/PL-SQL):
    |
    | 'table'     - Direct SELECT from a table or view (default)
    | 'procedure' - Call a PL/SQL stored procedure (returns cursor)
    | 'http'      - Fetch from ERP REST API (set ERP_CLIENTS_HTTP_URL)
    |
    */

    'clients_source' => env('ERP_CLIENTS_SOURCE', 'table'),

    /*
    |--------------------------------------------------------------------------
    | Table/View for Clients (when clients_source = 'table')
    |--------------------------------------------------------------------------
    |
    | Schema and table/view name. Use schema.table or just table.
    | Example: ERP.CLIENTS_VIEW or CLIENTS
    |
    */

    'clients_table' => env('ERP_CLIENTS_TABLE', 'CLIENTS'),

    /*
    |--------------------------------------------------------------------------
    | Clients List (Support > Customers view) - use LMS_INDIVIDUAL_CRM_VIEW
    |--------------------------------------------------------------------------
    |
    | CLIENTS_VIEW_SOURCE:
    |   'crm'      = vtiger contacts
    |   'erp'      = live Oracle LMS_INDIVIDUAL_CRM_VIEW (direct connection)
    |   'erp_sync' = local cache (erp_clients_cache, populated by sync script/API)
    |   'erp_http' = fetch directly from ERP_CLIENTS_HTTP_URL (fast, no Oracle/cache)
    |
    | When filtering by life system, separate views are used:
    |   system=group          → ERP_CLIENTS_GROUP_VIEW (LMS_GROUP_CRM_VIEW)
    |   system=individual     → ERP_CLIENTS_INDIVIDUAL_VIEW (LMS_INDIVIDUAL_CRM_VIEW)
    |   system=mortgage       → ERP_CLIENTS_MORTGAGE_VIEW (your mortgage CRM view)
    |   system=group_pension  → ERP_CLIENTS_GROUP_PENSION_VIEW (your group pension CRM view)
    |   no filter             → clients_list_table (default)
    |
    | If MORTGAGE / GROUP_PENSION views are not set in .env, those tabs return no rows
    | until ERP_CLIENTS_MORTGAGE_VIEW / ERP_CLIENTS_GROUP_PENSION_VIEW are defined.
    |
    */
    'clients_view_source' => env('CLIENTS_VIEW_SOURCE', 'crm'),

    'clients_group_view' => env('ERP_CLIENTS_GROUP_VIEW', 'LMS_GROUP_CRM_VIEW'),
    'clients_individual_view' => env('ERP_CLIENTS_INDIVIDUAL_VIEW', 'LMS_INDIVIDUAL_CRM_VIEW'),
    'clients_mortgage_view' => env('ERP_CLIENTS_MORTGAGE_VIEW'),
    'clients_group_pension_view' => env('ERP_CLIENTS_GROUP_PENSION_VIEW'),

    /*
    |--------------------------------------------------------------------------
    | Group Life vs Individual Life - Product Keywords
    |--------------------------------------------------------------------------
    |
    | Keywords (case-insensitive) in PRODUCT to classify clients:
    | - group_life: product contains any of these → "Group Life System"
    | - individual_life: product contains any of these → "Individual Life System"
    | If both match, group takes precedence. If none match, defaults to individual.
    |
    */
    'group_life_keywords' => array_map('trim', explode(',', env('ERP_GROUP_LIFE_KEYWORDS', 'GROUP,GL,CREDIT LIFE'))),
    'individual_life_keywords' => array_map('trim', explode(',', env('ERP_INDIVIDUAL_LIFE_KEYWORDS', 'INDIVIDUAL,IL,LIFE'))),
    /* Product substring match (UPPER) for erp_sync cache filtering & badges when no dedicated view row tag */
    'mortgage_keywords' => array_map('trim', explode(',', env('ERP_MORTGAGE_KEYWORDS', 'MORTGAGE'))),
    'group_pension_keywords' => array_map('trim', explode(',', env('ERP_GROUP_PENSION_KEYWORDS', 'GROUP PENSION,PENSION,PPP'))),
    'clients_list_table' => env('ERP_CLIENTS_LIST_TABLE', env('ERP_CLIENTS_TABLE', 'CLIENTS')),
    'clients_list_columns' => env('ERP_CLIENTS_LIST_COLUMNS', 'POLICY_NUMBER,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN'),
    'clients_list_order' => env('ERP_CLIENTS_LIST_ORDER', 'PRODUCT'),
    'clients_list_search_columns' => env('ERP_CLIENTS_LIST_SEARCH_COLUMNS', 'POLICY_NUMBER,LIFE_ASSURED,POL_PREPARED_BY,INTERMEDIARY,KRA_PIN,ID_NO,PHONE_NO'),

    /*
    |--------------------------------------------------------------------------
    | Columns to select (when using table source)
    |--------------------------------------------------------------------------
    |
    | Comma-separated column names. Use * for all columns.
    | Map ERP columns to a consistent structure in the service.
    |
    */

    'clients_columns' => env('ERP_CLIENTS_COLUMNS', '*'),

    /*
    |--------------------------------------------------------------------------
    | Search columns (for Serve Client search)
    |--------------------------------------------------------------------------
    |
    | Comma-separated column names to search when customer service looks up
    | a client by policy, name, phone, or email.
    |
    */
    'search_columns' => env('ERP_SEARCH_COLUMNS', 'POLICY_NO,POLICY_NUMBER,CLIENT_NAME,NAME,FIRST_NAME,LAST_NAME,PHONE,MOBILE,EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Stored Procedure (when clients_source = 'procedure')
    |--------------------------------------------------------------------------
    |
    | Package.Procedure format. The procedure should accept an OUT cursor
    | parameter. Example: ERP_PKG.GET_ALL_CLIENTS
    |
    */

    'clients_procedure' => env('ERP_CLIENTS_PROCEDURE', 'ERP_PKG.GET_CLIENTS'),

    /*
    |--------------------------------------------------------------------------
    | View wrapping procedure (optional)
    |--------------------------------------------------------------------------
    |
    | If your procedure populates a view or you have a view that selects from
    | the procedure, set this to use table-based fetch instead of raw procedure.
    |
    */

    'clients_view' => env('ERP_CLIENTS_VIEW', null),

    /*
    |--------------------------------------------------------------------------
    | HTTP API (when clients_source = 'http')
    |--------------------------------------------------------------------------
    |
    | URL of the ERP REST endpoint that returns client list.
    |
    */

    'clients_http_url' => env('ERP_CLIENTS_HTTP_URL', ''),

    'maturities_http_url' => env('ERP_MATURITIES_HTTP_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Clients list lazy load
    |--------------------------------------------------------------------------
    |
    | When true, the Clients page loads rows via JS after first paint.
    | Keep false on servers where /api/support/clients may be blocked.
    |
    */
    'clients_lazy_load' => env('ERP_CLIENTS_LAZY_LOAD', false),

    /*
    |--------------------------------------------------------------------------
    | Column mapping (ERP -> API response)
    |--------------------------------------------------------------------------
    |
    | Map ERP column names (uppercase) to API response keys.
    | Leave empty to return raw columns.
    |
    */

    'column_map' => [
        'CLIENT_ID' => 'id',
        'CLIENT_CODE' => 'code',
        'CLIENT_NAME' => 'name',
        'NAME' => 'name',
        'EMAIL' => 'email',
        'PHONE' => 'phone',
        'MOBILE' => 'mobile',
        'ADDRESS' => 'address',
        'STATUS' => 'status',
        'MENDR_STATUS' => 'status',
        'ENDR_STATUS' => 'status',
        'POLICY_NO' => 'policy_no',
        'POLICY_NUMBER' => 'policy_number',
        'PRODUCT' => 'product',
        'FIRST_NAME' => 'first_name',
        'LAST_NAME' => 'last_name',
        'POL_PREPARED_BY' => 'pol_prepared_by',
        'INTERMEDIARY' => 'intermediary',
    ],

    /*
    |--------------------------------------------------------------------------
    | Agency advances notification (finance:notify-agency-advances)
    |--------------------------------------------------------------------------
    |
    | When FINANCE_AGENCY_ADVANCES_NOTIFY_ENABLED=true, the scheduler runs
    | finance:notify-agency-advances daily and emails the recipient only if
    | ERP returns at least one AGNADV row (cqr_bbr_code NULL, active status).
    |
    */

    'agency_advances_notify_enabled' => filter_var(env('FINANCE_AGENCY_ADVANCES_NOTIFY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'agency_advances_notify_recipient' => env('FINANCE_AGENCY_ADVANCES_NOTIFY_RECIPIENT', 'kelvin.kimutai@geminialife.co.ke'),

    /*
    |--------------------------------------------------------------------------
    | ERP SMS life messaging (erp:send-sms-messages)
    |--------------------------------------------------------------------------
    |
    | Pending messages are fetched from tq_crm.tqc_smslife_messages using the
    | supplied ERP query where sms_status = D. Successful sends are marked with
    | ERP_MESSAGES_SENT_STATUS (default OK). Failed sends remain pending for retry.
    |
    */

    'messages_table' => env('ERP_MESSAGES_TABLE', 'tq_crm.tqc_smslife_messages'),
    'messages_status_column' => env('ERP_MESSAGES_STATUS_COLUMN', 'sms_status'),
    'messages_pending_status' => env('ERP_MESSAGES_PENDING_STATUS', 'D'),
    'messages_sent_status' => env('ERP_MESSAGES_SENT_STATUS', 'OK'),
    'messages_failed_status' => env('ERP_MESSAGES_FAILED_STATUS', 'E'),
    'messages_sent_date_column' => env('ERP_MESSAGES_SENT_DATE_COLUMN', ''),
    'messages_auto_send_enabled' => filter_var(env('ERP_MESSAGES_AUTO_SEND_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'messages_auto_send_limit' => (int) env('ERP_MESSAGES_AUTO_SEND_LIMIT', 50),
    'messages_http_timeout' => (int) env('ERP_MESSAGES_HTTP_TIMEOUT', 45),
    'messages_http_connect_timeout' => (int) env('ERP_MESSAGES_HTTP_CONNECT_TIMEOUT', 5),
    'messages_mark_batch_size' => max(10, min(200, (int) env('ERP_MESSAGES_MARK_BATCH_SIZE', 100))),
    'messages_send_via_queue' => filter_var(env('ERP_MESSAGES_SEND_VIA_QUEUE', true), FILTER_VALIDATE_BOOLEAN),
    'messages_failure_notify_enabled' => filter_var(env('ERP_MESSAGES_FAILURE_NOTIFY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'messages_failure_notify_recipient' => env('ERP_MESSAGES_FAILURE_NOTIFY_RECIPIENT', 'kelvin.kimutai@geminialife.co.ke'),
    'messages_failure_notify_cc' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ERP_MESSAGES_FAILURE_NOTIFY_CC', ''))
    ))),

    /*
    | When false (default), erp:resend-sms is blocked — only draft/pending ERP rows are sent.
    */
    'messages_allow_history_resend' => filter_var(env('ERP_SMS_ALLOW_HISTORY_RESEND', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | How CRM loads / updates ERP SMS rows:
    |   auto   — use erp-clients-api when a messages/finance HTTP base is set (recommended; avoids ORA-03113 from PHP OCI8)
    |   http   — always use erp-clients-api (fails if base URL missing)
    |   oracle — always use direct Oracle (erp connection + OCI8)
    */
    'messages_http' => strtolower(trim((string) env('ERP_MESSAGES_HTTP', 'auto'))),

    /*
    | Optional: ERP SMS API base only (scheme + host + port). When empty, uses finance_http_base
    | (FINANCE_ERP_HTTP_BASE or host derived from ERP_CLIENTS_HTTP_URL).
    */
    'messages_http_base' => (function () {
        $m = rtrim(trim((string) env('ERP_MESSAGES_HTTP_BASE', '')), '/');
        if ($m === '') {
            return '';
        }

        return \App\Support\ErpHttpBaseUrl::normalizeBase($m);
    })(),

    /*
    | Optional Bearer for /messages/* only; when empty uses finance_http_token.
    */
    'messages_http_token' => env('ERP_MESSAGES_HTTP_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Finance (FMS cheques) via HTTP — no PHP OCI8 on the CRM server
    |--------------------------------------------------------------------------
    |
    | When set, Finance cheques and agency advances load from erp-clients-api
    | Base URL only (scheme + host + port), e.g. http://10.1.4.101:5000 — not …/clients.
    | If FINANCE_ERP_HTTP_BASE accidentally ends with /clients, it is stripped automatically.
    |
    | If empty, the base URL is derived from ERP_CLIENTS_HTTP_URL when possible
    | (path prefix before …/api/clients is preserved — see App\Support\ErpHttpBaseUrl).
    |
    */

    'finance_http_base' => (function () {
        $explicit = rtrim(trim((string) env('FINANCE_ERP_HTTP_BASE', '')), '/');
        if ($explicit !== '') {
            return \App\Support\ErpHttpBaseUrl::normalizeBase($explicit);
        }

        return \App\Support\ErpHttpBaseUrl::deriveFromClientsHttpUrl((string) env('ERP_CLIENTS_HTTP_URL', ''));
    })(),

    'finance_http_token' => env('FINANCE_ERP_HTTP_TOKEN', env('ERP_API_TOKEN', '')),

];
