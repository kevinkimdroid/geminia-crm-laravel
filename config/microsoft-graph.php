<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Microsoft Graph API (Office 365)
    |--------------------------------------------------------------------------
    | Best option for Office 365 - no IMAP quirks, OAuth2, works with MFA.
    | Requires Azure AD app registration with Mail.Read application permission.
    */
    'enabled' => filter_var(env('MSGRAPH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'tenant_id' => env('MSGRAPH_TENANT_ID', 'common'),
    'client_id' => env('MSGRAPH_CLIENT_ID', ''),
    'client_secret' => env('MSGRAPH_CLIENT_SECRET', ''),

    // Mailbox to read (user principal name, e.g. life@geminialife.co.ke)
    'mailbox' => env('MSGRAPH_MAILBOX', env('MAIL_FROM_ADDRESS', '')),

    'fetch_limit' => (int) env('MSGRAPH_FETCH_LIMIT', 25),
];
