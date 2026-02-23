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
        'Loan Statement,Disability Claim,Other,Policy Document,Premium Adjustment,Premium Refund,Stale Cheque'
    ))))) ?: [
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

];
