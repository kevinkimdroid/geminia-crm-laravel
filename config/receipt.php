<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Oracle connection used for all receipt lookups
    |--------------------------------------------------------------------------
    | Reuses the CRM's existing ERP (Oracle/PL-SQL) connection defined in
    | config/database.php. The receipt repository reads from the FMS receipts
    | table and the same PL/SQL helper packages used by the official receipt
    | report (fms_rcts_pkg, fms_gen_pkg, tqc_interfaces_pkg).
    */
    'connection' => env('RECEIPT_CONNECTION', 'receipts'),

    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    | When true, built-in sample receipts are served instead of querying Oracle.
    | Useful to exercise the UI before the Oracle connection is available.
    */
    'demo' => filter_var(env('RECEIPT_DEMO', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | FMS receipts table
    |--------------------------------------------------------------------------
    | The live receipts table keyed by the composite (rct_no, rct_brh_code).
    |
    | IMPORTANT: use the SCHEMA-QUALIFIED name (e.g. TQ_FMS.FMS_RECEIPTS). On
    | this database, unqualified object names resolve through a broken synonym
    | path that drops the session (ORA-03113); qualifying with the owner schema
    | avoids it. The PL/SQL helper packages, by contrast, must stay UNqualified.
    */
    'table' => env('RECEIPT_TABLE', 'TQ_FMS.FMS_RECEIPTS'),

    /*
    |--------------------------------------------------------------------------
    | Transient-error retries
    |--------------------------------------------------------------------------
    | The Oracle link intermittently drops a session mid-statement with
    | ORA-03113. Reads are retried on a fresh connection up to this many times.
    */
    'retry_attempts' => (int) env('RECEIPT_RETRY', 12),

    'company_name' => env('RECEIPT_COMPANY_NAME', 'Geminia Life Assurance Company Ltd'),

    /*
    |--------------------------------------------------------------------------
    | RTF template
    |--------------------------------------------------------------------------
    | Absolute path, or a filename/relative path resolved against
    | resources/templates. Header tokens use [[FIELD]] and the repeating line
    | region is delimited by [[#LINES]] ... [[/LINES]] containing [[LINE.FIELD]].
    */
    'template' => env('RECEIPT_RTF_TEMPLATE', 'receipt.rtf'),

    'templates_path' => resource_path('templates'),

    /*
    |--------------------------------------------------------------------------
    | PDF conversion
    |--------------------------------------------------------------------------
    | Full path to the LibreOffice "soffice" binary. When set, the merged RTF
    | is converted to PDF. When blank, the merged RTF is served as-is (it still
    | opens and prints in Word / WordPad).
    */
    'soffice_path' => env('RECEIPT_SOFFICE_PATH', ''),

    'output_path' => storage_path('app/receipts'),
];
