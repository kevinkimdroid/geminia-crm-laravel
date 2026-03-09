<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-Create Complaints from Inbound Email
    |--------------------------------------------------------------------------
    |
    | When emails are fetched (Microsoft Graph or IMAP), automatically create
    | a complaint in the Complaint Register for IRA compliance. Runs alongside
    | the auto-ticket-from-email flow - each qualifying email creates both
    | a ticket and a complaint.
    |
    | Set COMPLAINTS_AUTO_FROM_EMAIL_ENABLED=true
    |
    */
    'auto_from_email' => [
        'enabled' => filter_var(env('COMPLAINTS_AUTO_FROM_EMAIL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'nature' => env('COMPLAINTS_AUTO_FROM_EMAIL_NATURE', 'Other'),
        'priority' => env('COMPLAINTS_AUTO_FROM_EMAIL_PRIORITY', 'Medium'),
    ],

];
