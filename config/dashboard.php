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

];
