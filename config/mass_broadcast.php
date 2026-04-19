<?php

return [

    'max_recipients' => (int) env('BROADCAST_MAX_RECIPIENTS', 1000),

    /** Delay between outbound emails (Graph/SMTP throttling). */
    'email_throttle_ms' => (int) env('BROADCAST_EMAIL_THROTTLE_MS', 120),

    /** Reduced throttle used automatically for large bulk sends. */
    'email_throttle_ms_bulk' => (int) env('BROADCAST_EMAIL_THROTTLE_MS_BULK', 25),

    /**
     * Cache ERP policy detail lookups (policy -> details) to avoid repeated slow API/Oracle calls
     * while browsing/sending broadcasts.
     */
    'erp_policy_detail_cache_seconds' => (int) env('BROADCAST_ERP_POLICY_DETAIL_CACHE_SECONDS', 900),

    /** Enrich intermediary/email/phone eagerly only up to this many list rows. */
    'enrichment_eager_max_rows' => (int) env('BROADCAST_ENRICHMENT_EAGER_MAX_ROWS', 80),

    /** Max policy lookups during list enrichment for default (no search) view. */
    'enrichment_lookup_limit_default' => (int) env('BROADCAST_ENRICHMENT_LOOKUP_LIMIT_DEFAULT', 60),

    /** Max policy lookups during list enrichment while searching / life-segment filtering. */
    'enrichment_lookup_limit_search' => (int) env('BROADCAST_ENRICHMENT_LOOKUP_LIMIT_SEARCH', 120),

    /** SMTP retry attempts for transient socket disconnects (e.g. errno=10054). */
    'smtp_retry_attempts' => (int) env('BROADCAST_SMTP_RETRY_ATTEMPTS', 3),

    /** Delay between SMTP retries in milliseconds. */
    'smtp_retry_delay_ms' => (int) env('BROADCAST_SMTP_RETRY_DELAY_MS', 800),

    /**
     * Max total attachment bytes to send directly via Graph sendMail with fileAttachment.
     * Larger files still fall back to SMTP/Laravel Mail.
     */
    'graph_attachment_max_bytes' => (int) env('BROADCAST_GRAPH_ATTACHMENT_MAX_BYTES', 3145728),

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
