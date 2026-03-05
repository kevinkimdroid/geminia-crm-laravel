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

    'enabled' => env('ERP_ENABLED', true),

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
    |   system=group      → ERP_CLIENTS_GROUP_VIEW (LMS_GROUP_CRM_VIEW)
    |   system=individual → ERP_CLIENTS_INDIVIDUAL_VIEW (LMS_INDIVIDUAL_CRM_VIEW)
    |   no filter         → clients_list_table (default)
    |
    */
    'clients_view_source' => env('CLIENTS_VIEW_SOURCE', 'crm'),

    'clients_group_view' => env('ERP_CLIENTS_GROUP_VIEW', 'LMS_GROUP_CRM_VIEW'),
    'clients_individual_view' => env('ERP_CLIENTS_INDIVIDUAL_VIEW', 'LMS_INDIVIDUAL_CRM_VIEW'),

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
        'POLICY_NO' => 'policy_no',
        'POLICY_NUMBER' => 'policy_number',
        'PRODUCT' => 'product',
        'FIRST_NAME' => 'first_name',
        'LAST_NAME' => 'last_name',
        'POL_PREPARED_BY' => 'pol_prepared_by',
        'INTERMEDIARY' => 'intermediary',
    ],

];
