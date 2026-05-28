<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Advanta SMS API
    |--------------------------------------------------------------------------
    | Get API Key and Partner ID from: https://www.advantasms.com
    | Kenya shortcode format: e.g. GEMINIA or your registered shortcode
    */
    'api_url' => env('ADVANTA_API_URL', 'https://quicksms.advantasms.com/api/services/sendsms/'),
    'dlr_url' => env('ADVANTA_DLR_URL', 'https://quicksms.advantasms.com/api/services/getdlr/'),
    'apikey' => env('ADVANTA_API_KEY', ''),
    'partner_id' => env('ADVANTA_PARTNER_ID', ''),
    'shortcode' => env('ADVANTA_SHORTCODE', ''),
    'http_timeout' => (int) env('ADVANTA_HTTP_TIMEOUT', 15),
    'connect_timeout' => (int) env('ADVANTA_CONNECT_TIMEOUT', 5),
    'send_max_attempts' => (int) env('ADVANTA_SEND_MAX_ATTEMPTS', 3),
    'send_retry_delay_ms' => (int) env('ADVANTA_SEND_RETRY_DELAY_MS', 500),
];
