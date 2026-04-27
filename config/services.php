<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/social-auth/facebook/callback',
    ],

    'instagram' => [
        'client_id' => env('INSTAGRAM_CLIENT_ID', env('FACEBOOK_CLIENT_ID')),
        'client_secret' => env('INSTAGRAM_CLIENT_SECRET', env('FACEBOOK_CLIENT_SECRET')),
        'redirect' => env('APP_URL') . '/social-auth/instagram/callback',
    ],

    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_REDIRECT_URI', env('APP_URL') . '/social-auth/twitter/callback'),
        'oauth2' => true,
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/social-auth/youtube/callback',
    ],

    'tiktok' => [
        'client_key' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect' => env('TIKTOK_REDIRECT_URI', env('APP_URL') . '/social-auth/tiktok/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Media Webhook URLs (for platform developer consoles)
    |--------------------------------------------------------------------------
    | Meta: use the same /webhooks/social/meta URL for Facebook Page + Instagram.
    */
    'social_webhooks' => [
        'facebook' => env('SOCIAL_WEBHOOK_FACEBOOK', rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/webhooks/social/meta'),
        'instagram' => env('SOCIAL_WEBHOOK_INSTAGRAM', rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/webhooks/social/meta'),
        'tiktok' => env('SOCIAL_WEBHOOK_TIKTOK', rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/webhooks/social/ingest'),
        'twitter' => env('SOCIAL_WEBHOOK_TWITTER', rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/webhooks/social/ingest'),
        'google' => env('SOCIAL_WEBHOOK_GOOGLE', rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/webhooks/social/ingest'),
    ],

    'social_webhook' => [
        'meta_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'meta_app_secret' => env('META_APP_SECRET', env('FACEBOOK_CLIENT_SECRET')),
        'skip_signature_verify' => env('SOCIAL_WEBHOOK_SKIP_SIGNATURE_VERIFY', false),
        'enrich_with_graph' => env('SOCIAL_WEBHOOK_ENRICH_GRAPH', false),
        'ingest_bearer_token' => env('SOCIAL_WEBHOOK_INGEST_TOKEN'),
    ],

    'pbx' => [
        'api_url' => env('PBX_API_URL'),
        'api_key' => env('PBX_API_KEY'),
        'default_extension' => env('PBX_DEFAULT_EXTENSION', ''),
        // Duration: totalduration is usually populated; billduration often 0. Add columns if your schema differs.
        'duration_columns' => array_filter(array_map('trim', explode(',', env('PBX_DURATION_COLUMNS', 'totalduration,billduration')))),
        'webapp_url' => env('PBX_WEBAPP_URL', ''),
        'monitor_public_base_url' => env('PBX_MONITOR_PUBLIC_BASE_URL', ''),
        'make_call_url' => env('PBX_MAKE_CALL_URL', ''),
        'secret_key' => env('PBX_SECRET_KEY', ''),
        'outbound_context' => env('PBX_OUTBOUND_CONTEXT', ''),
        'outbound_trunk' => env('PBX_OUTBOUND_TRUNK', ''),
        'debug' => env('PBX_DEBUG', false),
        'trusted_callback_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('PBX_TRUSTED_CALLBACK_IPS', '127.0.0.1,::1,10.1.1.86'))))),
        // Kenya: add 254 prefix when number starts with 7 and has 9 digits (e.g. 712345678 -> 254712345678)
        // Set to false if trunk expects 0XXXXXXXXX (e.g. 0712345678)
        'number_country_code' => env('PBX_NUMBER_COUNTRY_CODE', '254'),
        'number_add_prefix' => env('PBX_NUMBER_ADD_PREFIX', true),
    ],

    'erp' => [
        'api_token' => env('ERP_API_TOKEN', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset
    |--------------------------------------------------------------------------
    | url: If set, forgot-password form redirects here instead of sending email.
    | base_url: Base URL for reset links in emails (default: APP_URL). Use when
    |           the app is accessed via a different address, e.g. http://10.1.1.65
    | connection: DB connection for password_reset_tokens (null = default).
    */
    'password_reset' => [
        'url' => env('PASSWORD_RESET_URL'),
        'base_url' => env('PASSWORD_RESET_BASE_URL'),
        'connection' => env('PASSWORD_RESET_DB_CONNECTION'),
    ],

];
