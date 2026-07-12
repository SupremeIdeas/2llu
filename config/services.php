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

    // -- Number / SMS providers (blueprint Sections 8-11) -----------------
    // Costs from these APIs are PRIVATE. Never cross lanes (country+type).

    'getatext' => [
        'api_key' => env('GETATEXT_API_KEY'),
        'webhook_url' => env('GETATEXT_WEBHOOK_URL'),
        'webhook_token' => env('GETATEXT_WEBHOOK_TOKEN'), // optional shared secret
        'base_url' => env('GETATEXT_BASE_URL', 'https://getatext.com/api/v1'),
    ],

    'fivesim' => [
        'api_key' => env('FIVESIM_API_KEY'), // Bearer JWT
        'base_url' => env('FIVESIM_BASE_URL', 'https://5sim.net/v1'),
    ],

    'smsactivate' => [
        'api_key' => env('SMSACTIVATE_API_KEY'),
        'base_url' => env('SMSACTIVATE_BASE_URL', 'https://sms-activate.org/stubs/handler_api.php'),
    ],

    'telnyx' => [
        'api_key' => env('TELNYX_API_KEY'),
        'base_url' => env('TELNYX_BASE_URL', 'https://api.telnyx.com/v2'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'base_url' => env('TWILIO_BASE_URL', 'https://api.twilio.com/2010-04-01'),
    ],

    // -- Payment gateways (blueprint Sections 14.2 & 19.3) ----------------
    // Sandbox keys only until Module 12. Webhooks are signature-verified.

    'flutterwave' => [
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'), // verif-hash header
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
    ],

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'), // also signs webhooks (HMAC-SHA512)
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
    ],

];
