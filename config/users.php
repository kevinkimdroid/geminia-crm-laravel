<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Departments (predefined list)
    |--------------------------------------------------------------------------
    |
    | All users must be categorized into one of these departments.
    | Shown in user edit, users list, and audit reports.
    |
    */
    'departments_list' => [
        'Customer Service',
        'Underwriting',
        'Operations',
        'Executive',
        'Control Functions',
        'Finance',
        'IT',
        'Business Development',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Departments (fallback map)
    |--------------------------------------------------------------------------
    |
    | Map vtiger user IDs to department names. Used when user_departments
    | table and vtiger_users.department are empty.
    |
    */
    'departments' => [],
];
