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
    'apikey' => env('ADVANTA_API_KEY', ''),
    'partner_id' => env('ADVANTA_PARTNER_ID', ''),
    'shortcode' => env('ADVANTA_SHORTCODE', ''),
];
