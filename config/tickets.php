<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ticket Categories
    |--------------------------------------------------------------------------
    |
    | Categories shown when creating tickets. Customize via TICKET_CATEGORIES
    | env (comma-separated) or keep these defaults.
    |
    */

    'categories' => array_values(array_filter(array_map('trim', explode(',', env(
        'TICKET_CATEGORIES',
        'Group Life - Claim,Group Life - Premium,Group Life - Policy,Group Life - Other,Loan Statement,Disability Claim,Other,Policy Document,Premium Adjustment,Premium Refund,Stale Cheque'
    ))))) ?: [
        'Group Life - Claim',
        'Group Life - Premium',
        'Group Life - Policy',
        'Group Life - Other',
        'Loan Statement',
        'Disability Claim',
        'Other',
        'Policy Document',
        'Premium Adjustment',
        'Premium Refund',
        'Stale Cheque',
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Line / Account Sort Order
    |--------------------------------------------------------------------------
    |
    | Preferred order for Organization (Product Line) dropdown. Names matching
    | these (case-insensitive) appear first in this order; others alphabetically.
    |
    */
    'organization_sort' => array_filter(array_map('trim', explode(',', env(
        'TICKET_ORGANIZATION_SORT',
        'INDIVIDUAL LIFE,GROUP LIFE,CREDIT LIFE,MORTGAGE,GROUP LAST EXPENSE'
    )))),

    /*
    |--------------------------------------------------------------------------
    | Auto-Create Tickets: Maturity Reminders
    |--------------------------------------------------------------------------
    |
    | Create tickets automatically for policies maturing within N days.
    | Assigned to a specific user (e.g. Customer Service).
    | Set TICKET_AUTO_MATURITY_ENABLED=true and TICKET_AUTO_MATURITY_ASSIGN_TO=userId.
    |
    */
    'auto_maturity' => [
        'enabled' => filter_var(env('TICKET_AUTO_MATURITY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'days_before' => (int) env('TICKET_AUTO_MATURITY_DAYS_BEFORE', 30),
        'assign_to_user_id' => (int) env('TICKET_AUTO_MATURITY_ASSIGN_TO', 1),
        'category' => env('TICKET_AUTO_MATURITY_CATEGORY', 'Policy Document'),
        'source' => env('TICKET_AUTO_MATURITY_SOURCE', 'Auto'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Ticket from Inbound Email
    |--------------------------------------------------------------------------
    |
    | When Fetch Emails runs, automatically create a ticket for each new email
    | from external senders, link it to the email, and send an auto-reply with
    | the ticket number.
    | Set TICKET_AUTO_FROM_EMAIL_ENABLED=true
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Inactive Status
    |--------------------------------------------------------------------------
    | Tickets are never deleted; they can only be inactivated. This status
    | value must exist in vtiger HelpDesk status picklist. Add via Vtiger
    | Settings > Module Manager > HelpDesk > Picklist if missing.
    */
    'inactive_status' => env('TICKET_INACTIVE_STATUS', 'Inactive'),

    'auto_ticket_from_email' => [
        'enabled' => filter_var(env('TICKET_AUTO_FROM_EMAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'assign_to_user_id' => (int) env('TICKET_AUTO_FROM_EMAIL_ASSIGN_TO', 1),
        'category' => env('TICKET_AUTO_FROM_EMAIL_CATEGORY', 'Other'),
        'source' => env('TICKET_AUTO_FROM_EMAIL_SOURCE', 'Email'),
        'auto_reply_enabled' => filter_var(env('TICKET_AUTO_REPLY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'auto_reply_subject' => env('TICKET_AUTO_REPLY_SUBJECT', ''),
        'auto_reply_body' => env('TICKET_AUTO_REPLY_BODY', ''),
    ],

];
