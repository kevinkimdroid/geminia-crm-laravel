<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pension Administration
    |--------------------------------------------------------------------------
    | Hub for group pension workflows: dedicated mailbox and ERP client segment.
    */
    'mailbox' => env('PENSIONS_MAILBOX', 'pensions@geminialife.co.ke'),

    // Internal: Exchange mailbox Graph can read when pensions@ is a distribution list (never shown in UI).
    'graph_fetch_mailbox' => env('PENSIONS_GRAPH_FETCH_MAILBOX', env('MSGRAPH_PENSIONS_MAILBOX', 'pensions.support@geminialife.co.ke')),

    // @deprecated use graph_fetch_mailbox
    'msgraph_mailbox' => env('PENSIONS_GRAPH_FETCH_MAILBOX', env('MSGRAPH_PENSIONS_MAILBOX', 'pensions.support@geminialife.co.ke')),

    // Microsoft Graph: Azure AD Object ID of the M365 *group* for pensions@ (no password needed).
    // IT can copy this from Azure Portal → Groups → pensions → Object ID.
    'graph_group_id' => env('MSGRAPH_PENSIONS_GROUP_ID'),

    // Extra mailboxes to scan for pension-related mail (inbox + sent items). Assignee is always included.
    'graph_scan_mailboxes' => array_values(array_filter(array_map('strtolower', array_map('trim', explode(',', (string) env('PENSION_GRAPH_SCAN_MAILBOXES', '')))))),

    // How many inbox messages to pull from Graph for the pension mailbox (paginated).
    'fetch_limit' => (int) env('PENSION_FETCH_LIMIT', 250),

    // Only fetch pension mail received within this many days (speeds up Graph sync).
    'fetch_since_days' => (int) env('PENSION_FETCH_SINCE_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Auto-create tickets from pensions@ client emails
    |--------------------------------------------------------------------------
    */
        'auto_ticket' => [
        'enabled' => filter_var(env('PENSION_AUTO_TICKET_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'first_email_only' => filter_var(env('PENSION_AUTO_TICKET_FIRST_EMAIL_ONLY', true), FILTER_VALIDATE_BOOLEAN),
        'assign_to_email' => env('PENSION_AUTO_TICKET_ASSIGN_TO', 'edith.kamau@geminialife.co.ke'),
        'escalate_to_email' => env('PENSION_AUTO_TICKET_ESCALATE_TO', 'daniel.wangendo@geminialife.co.ke'),
        'tat_hours' => (int) env('PENSION_AUTO_TICKET_TAT_HOURS', 3),
        'category' => env('PENSION_AUTO_TICKET_CATEGORY', 'Pension Administration'),
        'source' => env('PENSION_AUTO_TICKET_SOURCE', 'Pension Email'),
        'priority' => env('PENSION_AUTO_TICKET_PRIORITY', 'Normal'),
        'auto_reply_enabled' => filter_var(env('PENSION_AUTO_REPLY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'auto_reply_subject' => env('PENSION_AUTO_REPLY_SUBJECT', ''),
        'auto_reply_body' => env('PENSION_AUTO_REPLY_BODY', ''),
    ],

    'client_system' => 'group_pension',

    'ticket_organization' => env('PENSION_TICKET_ORGANIZATION', 'line:Group Pension'),

    // Pension Inbox: only show mail addressed to pensions@; never show pensions.support@ anywhere.
    'inbox_require_canonical_recipient' => filter_var(env('PENSION_INBOX_REQUIRE_CANONICAL', true), FILTER_VALIDATE_BOOLEAN),

    'inbox_exclude_addresses' => array_values(array_filter(array_map('strtolower', array_unique(array_merge(
        [
            'pensionsandinvestments@geminialife.co.ke',
        ],
        explode(',', (string) env('PENSION_INBOX_EXCLUDE_FROM', ''))
    ))))),

    // Outbound senders never shown in pension inbox.
    'inbox_outbound_exclude_addresses' => array_values(array_filter(array_map('strtolower', array_unique(array_merge(
        [
            'life@geminialife.co.ke',
            'servicinglife@geminialife.co.ke',
        ],
        array_filter([env('PENSIONS_GRAPH_FETCH_MAILBOX', env('MSGRAPH_PENSIONS_MAILBOX', ''))]),
        explode(',', (string) env('PENSION_INBOX_OUTBOUND_EXCLUDE', ''))
    ))))),

    // Hide life@ traffic from the pension inbox (sender or primary recipient).
    'inbox_exclude_life_mailbox' => filter_var(env('PENSION_INBOX_EXCLUDE_LIFE', true), FILTER_VALIDATE_BOOLEAN),
    'life_mailbox' => env('PENSION_LIFE_MAILBOX', 'life@geminialife.co.ke'),
];
