<?php

return [
    /*
    |--------------------------------------------------------------------------
    | App modules mapped to Vtiger tab names
    |--------------------------------------------------------------------------
    | Keys are our route/module identifiers. Values are vtiger_tab.name values.
    | Use null for modules that don't map to a Vtiger tab (e.g. dashboard, settings).
    */
    'app_to_vtiger' => [
        'dashboard' => 'Home',
        'contacts' => 'Contacts',
        'leads' => 'Leads',
        'tickets' => 'HelpDesk',
        'deals' => 'Potentials',
        'marketing' => 'Campaigns',
        'marketing.social-media' => null, // Custom module - always visible (not in standard Vtiger profiles)
        'marketing.campaigns' => 'Campaigns',
        'support' => 'HelpDesk',
    'support.tickets' => 'HelpDesk',
    'support.serve-client' => null,
    'support.faq' => null,       // Sub-module - visible when Support is visible
        'support.sms-notifier' => null,
        'support.customers' => null,
        'tools' => null,
        'tools.email-templates' => null,
        'tools.recycle-bin' => null,
        'tools.pbx-manager' => null,
        'tools.pdf-maker' => null,
        'tools.mail-manager' => null,
        'calendar' => null,
        'reports' => 'Reports',
        'settings' => null,
        'settings.crm' => null,
        'settings.manage-users' => null,
        'compliance.complaints' => null, // IRA complaint register - compliance requirement
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar navigation structure
    |--------------------------------------------------------------------------
    */
    'sidebar' => [
        'main' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-house-door-fill', 'route' => 'dashboard'],
            ['key' => 'deals', 'label' => 'Deals', 'icon' => 'bi-briefcase-fill', 'route' => 'deals.index'],
            ['key' => 'contacts', 'label' => 'Contacts', 'icon' => 'bi-person-lines-fill', 'route' => 'contacts.index'],
        ],
        'modules' => [
            [
                'key' => 'marketing',
                'label' => 'Marketing',
                'icon' => 'bi-megaphone-fill',
                'children' => [
                    ['key' => 'marketing.social-media', 'label' => 'Social Media', 'icon' => 'bi-facebook', 'route' => 'marketing.social-media'],
                    ['key' => 'marketing.campaigns', 'label' => 'Campaigns', 'icon' => 'bi-megaphone', 'route' => 'marketing.campaigns.index'],
                    ['key' => 'leads', 'label' => 'Leads', 'icon' => 'bi-table', 'route' => 'leads.index'],
                ],
            ],
            ['key' => 'support', 'label' => 'Support', 'icon' => 'bi-headset', 'route' => 'support'],
            ['key' => 'tickets', 'label' => 'Tickets', 'icon' => 'bi-ticket-perforated-fill', 'route' => 'tickets.index'],
            ['key' => 'compliance.complaints', 'label' => 'Complaint Register', 'icon' => 'bi-clipboard2-data', 'route' => 'compliance.complaints.index'],
        ],
        'tools' => [
            ['key' => 'tools', 'label' => 'Tools', 'icon' => 'bi-tools', 'route' => 'tools'],
            ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'bi-calendar3', 'route' => 'activities.index'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'bi-file-text', 'route' => 'reports'],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'bi-gear-fill', 'route' => 'settings'],
        ],
    ],
];
