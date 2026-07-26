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

    // Zendit — Naara Connect line (Full eSIMs: calls + data). Bearer auth.
    // Sandbox and production are DIFFERENT hosts; the base_url follows the
    // sandbox flag so a test key never hits the live wallet. The catalogue
    // `cost.fixed / currencyDivisor` is the WHOLESALE cost (PRIVATE).
    'zendit' => [
        'api_key' => env('ZENDIT_API_KEY'),
        'sandbox' => env('ZENDIT_SANDBOX', false),
        'base_url' => env('ZENDIT_BASE_URL', env('ZENDIT_SANDBOX', false)
            ? 'https://test-api.zendit.io/v1'
            : 'https://api.zendit.io/v1'),
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
        // In-browser dialer (Live Voice — Part B). A standalone API Key (NOT the
        // auth token) signs the short-lived WebRTC access tokens; the TwiML App
        // owns the outbound-call webhook. caller_id is the verified NaaraSim
        // number shown to the party being dialled. default_voice_cost is the
        // per-minute wholesale fallback when the live Pricing API is unreachable.
        'api_key_sid' => env('TWILIO_API_KEY_SID'),
        'api_key_secret' => env('TWILIO_API_KEY_SECRET'),
        'twiml_app_sid' => env('TWILIO_TWIML_APP_SID'),
        'caller_id' => env('TWILIO_CALLER_ID'),
        'default_voice_cost' => env('TWILIO_DEFAULT_VOICE_COST', 0.02),
        'default_monthly_cost' => env('TWILIO_DEFAULT_MONTHLY_COST', 1.15),
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

    // PayPal (Orders v2). client_id/secret gate it Active; webhook_id verifies
    // inbound webhooks via PayPal's verify-webhook-signature API.
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.paypal.com'),
    ],

    // Binance Pay (merchant API v3, crypto rail). HMAC-SHA512 signed requests.
    'binance' => [
        'api_key' => env('BINANCE_PAY_API_KEY'),
        'api_secret' => env('BINANCE_PAY_API_SECRET'),
        'base_url' => env('BINANCE_PAY_BASE_URL', 'https://bpay.binanceapi.com'),
    ],

    // NOWPayments (crypto). api_key gates it; ipn_secret verifies webhooks.
    'nowpayments' => [
        'api_key' => env('NOWPAYMENTS_API_KEY'),
        'ipn_secret' => env('NOWPAYMENTS_IPN_SECRET'),
        'base_url' => env('NOWPAYMENTS_BASE_URL', 'https://api.nowpayments.io'),
    ],

    // Cryptomus (crypto). merchant_id + api_key sign every request + webhook.
    'cryptomus' => [
        'merchant_id' => env('CRYPTOMUS_MERCHANT_ID'),
        'api_key' => env('CRYPTOMUS_API_KEY'),
        'base_url' => env('CRYPTOMUS_BASE_URL', 'https://api.cryptomus.com'),
    ],

    // CoinPayments (crypto). public/private keys sign requests; ipn_secret +
    // merchant_id verify IPNs. pay_currency is the coin the buyer pays in.
    'coinpayments' => [
        'public_key' => env('COINPAYMENTS_PUBLIC_KEY'),
        'private_key' => env('COINPAYMENTS_PRIVATE_KEY'),
        'ipn_secret' => env('COINPAYMENTS_IPN_SECRET'),
        'merchant_id' => env('COINPAYMENTS_MERCHANT_ID'),
        'pay_currency' => env('COINPAYMENTS_PAY_CURRENCY', 'USDT.TRC20'),
    ],

    // Payssion (local payment methods). api_key + secret_key sign + verify.
    'payssion' => [
        'api_key' => env('PAYSSION_API_KEY'),
        'secret_key' => env('PAYSSION_SECRET_KEY'),
        'pm_id' => env('PAYSSION_PM_ID', 'alipay_cn'),
        'base_url' => env('PAYSSION_BASE_URL', 'https://www.payssion.com'),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
    ],

    // Identity / KYC providers (ROADMAP §Layer 0.3). Admin-managed via the
    // API-keys page; a provider is used only once its keys are present, else
    // NaaraSim falls back to manual admin review.
    'smileid' => [
        'partner_id' => env('SMILEID_PARTNER_ID'),
        'api_key' => env('SMILEID_API_KEY'),
        'base_url' => env('SMILEID_BASE_URL', 'https://api.smileidentity.com'),
    ],

    'dojah' => [
        'app_id' => env('DOJAH_APP_ID'),
        'api_key' => env('DOJAH_API_KEY'),
        'base_url' => env('DOJAH_BASE_URL', 'https://api.dojah.io'),
    ],

    // Social login (Module 23). Keys are admin-managed via the API-keys page;
    // redirect defaults to our callback route on the current APP_URL.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    // Additional social sign-in providers (owner request). Each lights up only
    // once both credentials are saved; the callback route defaults per provider.
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],
    'twitter' => [ // X (OAuth 2.0)
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_REDIRECT_URI', '/auth/twitter/callback'),
    ],
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI', '/auth/apple/callback'),
    ],
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', '/auth/microsoft/callback'),
    ],
    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI', '/auth/discord/callback'),
    ],

    // Voice replies for support (Module 25). Admin-managed via the API-keys page.
    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'voice_id' => env('ELEVENLABS_VOICE_ID'),
        'model' => env('ELEVENLABS_MODEL', 'eleven_v3'),
        'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io/v1'),
    ],

    // Claude-assisted maintenance loop (blueprint Section 29) + the AI Pricing
    // Architect (Plan Price with Claude). Both light up only when the key is set.
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],

    // Fine-grained GitHub token scoped to THIS repo only, for opening
    // CI-gated maintenance PRs. Store encrypted at rest.
    'github_maintenance' => [
        'token' => env('GITHUB_MAINTENANCE_TOKEN'),
        'repo' => env('GITHUB_MAINTENANCE_REPO'), // owner/name
        'base_branch' => env('GITHUB_MAINTENANCE_BASE_BRANCH', 'main'),
    ],

    // Cloudflare Turnstile bot protection (blueprint Section 33).
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
    ],

    // Rewarded-ad / offerwall network for the NaaraCredits rewards area (loyalty
    // module). Rewards are granted ONLY via the network's server-to-server
    // postback, HMAC-verified with this secret — never self-reported by the
    // browser. Use a compliant rewarded/offerwall provider (AdGate, AdGem,
    // BitLabs, CPX, etc.), NOT AdSense (which forbids incentivised views).
    'offerwall' => [
        'postback_secret' => env('OFFERWALL_POSTBACK_SECRET'),
    ],

];
