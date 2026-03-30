<?php

return [

    'discharge_voucher' => [
        'document_title' => env('DISCHARGE_VOUCHER_TITLE', 'Discharge Voucher'),
        'subtitle' => env('DISCHARGE_VOUCHER_SUBTITLE', 'Policy maturity — discharge of obligations under the policy contract'),
        'issuer_line' => env('DISCHARGE_VOUCHER_ISSUER', null),
        'signatory_label' => env('DISCHARGE_VOUCHER_SIGNATORY_LABEL', 'Authorised signatory'),
        'extra_paragraph' => env('DISCHARGE_VOUCHER_EXTRA', ''),
    ],

];
