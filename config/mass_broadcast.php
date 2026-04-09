<?php

return [

    'max_recipients' => (int) env('BROADCAST_MAX_RECIPIENTS', 500),

    /** Delay between outbound emails (Graph/SMTP throttling). */
    'email_throttle_ms' => (int) env('BROADCAST_EMAIL_THROTTLE_MS', 120),

    /** Delay between SMS API calls. */
    'sms_throttle_ms' => (int) env('BROADCAST_SMS_THROTTLE_MS', 80),

    /** Max rows read from an uploaded recipient list (Excel/CSV). */
    'excel_max_rows' => (int) env('BROADCAST_EXCEL_MAX_ROWS', 5000),

    /**
     * Optional vtiger custom field on vtiger_contactscf for "client type" (e.g. cf_912).
     * When set, broadcast UI shows distinct values as filters (picklist-style).
     */
    'contact_type_cf' => env('BROADCAST_CONTACT_TYPE_CF'),

    /**
     * Skip contacts who already received a mass broadcast on the same channel (email/SMS)
     * within this many days, when "skip recent" is enabled on send.
     */
    'skip_recent_days' => (int) env('BROADCAST_SKIP_RECENT_DAYS', 14),

];
