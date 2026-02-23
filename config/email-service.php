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
    // Optional: override endpoint path (e.g. /api/v1/emails) if API uses different paths
    'fetch_endpoint' => env('EMAIL_SERVICE_FETCH_ENDPOINT', ''),
    // When true, fall back to IMAP if HTTP service is unreachable (connection timeout)
    'fallback_to_imap' => filter_var(env('EMAIL_SERVICE_FALLBACK_TO_IMAP', true), FILTER_VALIDATE_BOOLEAN),
];
