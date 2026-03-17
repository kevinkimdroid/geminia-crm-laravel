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

    /*
    |--------------------------------------------------------------------------
    | Allow All Authenticated Users (Workaround for 403)
    |--------------------------------------------------------------------------
    | Set TICKET_ACCESS_ALL_AUTHENTICATED=true in .env to allow any logged-in
    | user to access any ticket. Use only as temporary workaround if assigned
    | users get 403. Normal permission (assignee only) applies when false.
    */
    'allow_all_authenticated' => filter_var(env('TICKET_ACCESS_ALL_AUTHENTICATED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Ticket Access – Permission Bypass
    |--------------------------------------------------------------------------
    | Set TICKET_ACCESS_ALL_AUTHENTICATED=true to allow any logged-in user
    | to access any ticket (bypasses assignee check). Use as workaround if
    | you get 403 for tickets assigned to you. Set to false for proper checks.
    */
    'allow_all_authenticated' => filter_var(env('TICKET_ACCESS_ALL_AUTHENTICATED', false), FILTER_VALIDATE_BOOLEAN),

    'auto_ticket_from_email' => [
        'enabled' => filter_var(env('TICKET_AUTO_FROM_EMAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'assign_to_user_id' => (int) env('TICKET_AUTO_FROM_EMAIL_ASSIGN_TO', 1),
        'category' => env('TICKET_AUTO_FROM_EMAIL_CATEGORY', 'Other'),
        'source' => env('TICKET_AUTO_FROM_EMAIL_SOURCE', 'Email'),
        // Only Gmail, Yahoo, Hotmail create tickets from email. Set TICKET_EMAIL_ALLOWED_DOMAINS= to allow all (except excluded).
        'allowed_sender_domains' => array_filter(array_map('strtolower', array_map('trim', explode(',', env('TICKET_EMAIL_ALLOWED_DOMAINS', 'gmail.com,googlemail.com,yahoo.com,yahoo.co.ke,yahoo.co.uk,ymail.com,rocketmail.com,hotmail.com,hotmail.co.ke,hotmail.co.uk,live.com,outlook.com,outlook.co.ke,msn.com'))))),
        'auto_reply_enabled' => filter_var(env('TICKET_AUTO_REPLY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'auto_reply_subject' => env('TICKET_AUTO_REPLY_SUBJECT', ''),
        'auto_reply_body' => env('TICKET_AUTO_REPLY_BODY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notify on Ticket Creation
    |--------------------------------------------------------------------------
    | TICKET_NOTIFY_ON_CREATION_ENABLED - Master switch for creation emails.
    | TICKET_NOTIFY_ASSIGNED_USER - Email the assigned staff (default: true).
    | TICKET_NOTIFY_CONTACT - Email the client/contact (default: false).
    |   Set TICKET_NOTIFY_CONTACT=true to turn on client emails.
    */
    'notify_on_creation' => [
        'enabled' => filter_var(env('TICKET_NOTIFY_ON_CREATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'notify_assigned_user' => filter_var(env('TICKET_NOTIFY_ASSIGNED_USER', true), FILTER_VALIDATE_BOOLEAN),
        'notify_contact' => false, // Client emails off. Set TICKET_NOTIFY_CONTACT=true in .env to enable.
    ],

    /*
    |--------------------------------------------------------------------------
    | Notify on Reassignment
    |--------------------------------------------------------------------------
    | When a ticket is reassigned (assigned_to changes on edit), email the new
    | assignee. Set TICKET_NOTIFY_ON_REASSIGNMENT=true (default: true).
    */
    'notify_on_reassignment' => [
        'enabled' => filter_var(env('TICKET_NOTIFY_ON_REASSIGNMENT', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'sla_violation_reminders' => [
        'enabled' => filter_var(env('TICKET_SLA_REMINDERS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'cc_emails' => array_filter(array_map('trim', explode(',', env('TICKET_SLA_REMINDERS_CC', '')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feedback Request on Ticket Close
    |--------------------------------------------------------------------------
    | When a ticket is closed, send an email to the contact asking them to rate
    | the service (Were you happy? Yes/No). TICKET_FEEDBACK_REQUEST_ENABLED=true
    |
    | When feedback is submitted, optionally email it to life@geminialife.co.ke
    | (or TICKET_FEEDBACK_NOTIFY_EMAIL) so the team is notified.
    */
    /*
    |--------------------------------------------------------------------------
    | Ticket Access Permission
    |--------------------------------------------------------------------------
    | allow_all_authenticated: When true, any logged-in user can access any
    | ticket (bypasses assignee check). Use only as a temporary workaround if
    | you get 403 on tickets assigned to you. Set TICKET_ACCESS_ALL_AUTHENTICATED=true
    */
    'allow_all_authenticated' => filter_var(env('TICKET_ACCESS_ALL_AUTHENTICATED', false), FILTER_VALIDATE_BOOLEAN),

    'feedback_request' => [
        'enabled' => filter_var(env('TICKET_FEEDBACK_REQUEST_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'notify_email' => env('TICKET_FEEDBACK_NOTIFY_EMAIL', 'life@geminialife.co.ke'),
        // Public URL for feedback links in emails (clients must reach this). E.g. https://geminialife.co.ke/feedback
        'public_url' => rtrim(env('FEEDBACK_PUBLIC_URL', ''), '/'),
        // CRM API URL for the standalone feedback app to call (server-to-server). E.g. http://10.1.1.65
        'crm_api_url' => rtrim(env('FEEDBACK_CRM_API_URL', env('APP_URL', 'http://localhost')), '/'),
    ],
];
