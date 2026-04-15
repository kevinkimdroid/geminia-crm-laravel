<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Service API (HTTP)
    |--------------------------------------------------------------------------
    | When set, Mail Manager fetches via HTTP API instead of IMAP.
    | Used when mail server is behind a gateway at 10.10.1.111:8080.
    */
    'url' => rtrim(env('EMAIL_SERVICE_URL', ''), '/'),
    'username' => env('MAIL_USERNAME', env('EMAIL_SERVICE_SENDER', '')),
    'password' => env('MAIL_PASSWORD', ''),
    'sender' => env('EMAIL_SERVICE_SENDER', env('MAIL_USERNAME', '')),
    'fetch_limit' => (int) env('EMAIL_SERVICE_FETCH_LIMIT', 25),
    // Mail health is marked stale when no successful fetch within N minutes.
    'health_stale_minutes' => (int) env('MAIL_FETCH_STALE_MINUTES', 15),
    // Optional: override endpoint path (e.g. /api/v1/emails) if API uses different paths
    'fetch_endpoint' => env('EMAIL_SERVICE_FETCH_ENDPOINT', ''),
    // When true, fall back to IMAP if HTTP service is unreachable (connection timeout)
    'fallback_to_imap' => filter_var(env('EMAIL_SERVICE_FALLBACK_TO_IMAP', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Excluded Sender Domains (organizations, not clients)
    |--------------------------------------------------------------------------
    | Emails FROM these domains will NOT create tickets or complaints.
    | Use for partner organizations (e.g. GABNet@gab.co.ke) - only individual
    | client emails (Gmail, Yahoo, etc.) will be processed.
    | Comma-separated: EMAIL_EXCLUDED_SENDER_DOMAINS=geminialife.co.ke,gab.co.ke,centralbank.go.ke
    */
    'excluded_sender_domains' => array_filter(array_map('strtolower', array_map('trim', explode(',', env('EMAIL_EXCLUDED_SENDER_DOMAINS', 'geminialife.co.ke,gab.co.ke,centralbank.go.ke'))))),
];
