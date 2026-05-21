<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard Show All Stats
    |--------------------------------------------------------------------------
    |
    | When true, the dashboard shows organization-wide stats (Pipeline, Leads,
    | Active Deals, Contacts, Clients) for all users, regardless of role.
    | When false, non-admins see only their own records.
    |
    | Set to true if you want everyone to see the full company picture.
    |
    */

    'show_all_stats' => env('DASHBOARD_SHOW_ALL_STATS', true),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Staff Banner
    |--------------------------------------------------------------------------
    |
    | Optional floating notice on the dashboard home page. Set
    | DASHBOARD_STAFF_BANNER_ENABLED=true and DASHBOARD_STAFF_BANNER_MESSAGE
    | in .env. Change DASHBOARD_STAFF_BANNER_ID when you publish a new notice
    | so staff who dismissed an older banner see the update.
    |
    */

    'staff_banner' => [
        'enabled' => filter_var(env('DASHBOARD_STAFF_BANNER_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'id' => env('DASHBOARD_STAFF_BANNER_ID', 'default'),
        'title' => env('DASHBOARD_STAFF_BANNER_TITLE', 'Notice for staff'),
        'message' => env('DASHBOARD_STAFF_BANNER_MESSAGE', ''),
        'variant' => env('DASHBOARD_STAFF_BANNER_VARIANT', 'info'),
        'link_url' => env('DASHBOARD_STAFF_BANNER_LINK_URL'),
        'link_label' => env('DASHBOARD_STAFF_BANNER_LINK_LABEL', 'Learn more'),
    ],

];
