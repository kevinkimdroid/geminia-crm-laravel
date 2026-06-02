<?php

return [

    'discharge_voucher' => [
        'document_title' => env('DISCHARGE_VOUCHER_TITLE', 'Discharge Voucher'),
        'subtitle' => env('DISCHARGE_VOUCHER_SUBTITLE', 'Policy maturity — discharge of obligations under the policy contract'),
        'issuer_line' => env('DISCHARGE_VOUCHER_ISSUER', null),
        'signatory_label' => env('DISCHARGE_VOUCHER_SIGNATORY_LABEL', 'Authorised signatory'),
        'extra_paragraph' => env('DISCHARGE_VOUCHER_EXTRA', ''),
    ],

    'investment_notifications' => [
        'to' => env('INVESTMENT_MATURITY_NOTIFY_TO', 'douglas.nyakwara@geminialife.co.ke'),
        'cc' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('INVESTMENT_MATURITY_NOTIFY_CC', 'kelvin.kimutai@geminialife.co.ke,caroline.njogu@geminialife.co.ke'))
        ))),
        'products' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('INVESTMENT_MATURITY_PRODUCTS', ''))
        ))),
        'days' => max(1, min(30, (int) env('INVESTMENT_MATURITY_NOTIFY_DAYS', 14))),
    ],

];
