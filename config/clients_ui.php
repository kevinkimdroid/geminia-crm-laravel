<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support > Customers — filter pill & "System" column labels
    |--------------------------------------------------------------------------
    |
    | Override in .env: CLIENTS_TAB_LABEL_GROUP, CLIENTS_TAB_LABEL_GROUP_PENSION, etc.
    | URLs still use system=group / system=group_pension (only the visible text changes).
    |
    */
    'tab_labels' => [
        'group' => env('CLIENTS_TAB_LABEL_GROUP', 'Group Life'),
        'individual' => env('CLIENTS_TAB_LABEL_INDIVIDUAL', 'Individual Life'),
        'mortgage' => env('CLIENTS_TAB_LABEL_MORTGAGE', 'Mortgage'),
        'group_pension' => env('CLIENTS_TAB_LABEL_GROUP_PENSION', 'Group Pension'),
    ],

];
