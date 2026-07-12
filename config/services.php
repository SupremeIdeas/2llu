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

    // -- eSIM providers (blueprint Sections 5.2-5.4) ----------------------
    // Keys are sandbox-only until Module 12 sign-off. cost/net prices returned
    // by these APIs are PRIVATE and never surfaced to users.

    'esimgo' => [
        'api_key' => env('ESIMGO_API_KEY'),
        'webhook_secret' => env('ESIMGO_WEBHOOK_SECRET'),
        'sandbox' => env('ESIMGO_SANDBOX', false),
        'base_url' => env('ESIMGO_BASE_URL', 'https://api.esim-go.com/v2.5'),
    ],

    'airalo' => [
        'client_id' => env('AIRALO_CLIENT_ID'),
        'client_secret' => env('AIRALO_CLIENT_SECRET'),
        'sandbox' => env('AIRALO_SANDBOX', false),
        // Production: https://partners-api.airalo.com/v2
        // Sandbox:    https://sandbox-partners-api.airalo.com/v2
        'base_url' => env('AIRALO_BASE_URL', 'https://partners-api.airalo.com/v2'),
    ],

    'quibity' => [
        'api_key' => env('QUIBITY_API_KEY'),
        'sandbox' => env('QUIBITY_SANDBOX', false),
        'base_url' => env('QUIBITY_BASE_URL', 'https://esim.sm/api/reseller/v1'),
    ],

];
