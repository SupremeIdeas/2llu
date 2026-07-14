<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed API credentials (blueprint Sections 15 & 17.4, money-safety
 * rule 10). Every provider/gateway/integration secret can be pasted and saved
 * from the admin panel instead of hand-editing .env — the operator has zero
 * coding knowledge and shared cPanel makes editing .env awkward.
 *
 * Storage: one encrypted Setting row (`providers.keys`) holding a
 * config-path => value map. At boot, applyToConfig() overlays any saved value
 * on top of config() so EVERY service keeps reading config('services.*') with
 * no code change, and ProviderStatus flips Active the moment a key is saved.
 *
 * Precedence: an admin-saved value WINS over the .env value (an operator who
 * pastes a key in the panel expects it to take effect). A blank field is
 * treated as "not set" and falls through to whatever .env provides, so the
 * two mechanisms coexist. Values are encrypted at rest by the Setting cast and
 * NEVER exposed — the admin UI shows a masked preview, not the raw secret.
 */
class ProviderKeys
{
    /** The single Setting row that holds the config-path => value map. */
    public const SETTING_KEY = 'providers.keys';

    private const CACHE_KEY = 'providers.keys.resolved';

    /**
     * The full credential schema, grouped for the admin UI. Each field maps a
     * human label to the exact config path it overrides and the .env variable
     * it mirrors. `secret` fields are masked in the UI and never echoed back.
     *
     * @return array<string, array{label: string, fields: array<string, array{label: string, config: string, env: string, secret: bool, hint: string}>}>
     */
    public static function schema(): array
    {
        return [
            'esim' => [
                'label' => 'eSIM data providers',
                'fields' => [
                    'esimgo_api_key' => ['label' => 'eSIM Go — API Key', 'config' => 'services.esimgo.api_key', 'env' => 'ESIMGO_API_KEY', 'secret' => true, 'hint' => 'portal.esim-go.com → Account → API Keys (PRIMARY).'],
                    'esimgo_webhook_secret' => ['label' => 'eSIM Go — Webhook Secret', 'config' => 'services.esimgo.webhook_secret', 'env' => 'ESIMGO_WEBHOOK_SECRET', 'secret' => true, 'hint' => 'Account → Webhooks — verifies the HMAC signature.'],
                    'airalo_client_id' => ['label' => 'Airalo — Client ID', 'config' => 'services.airalo.client_id', 'env' => 'AIRALO_CLIENT_ID', 'secret' => false, 'hint' => 'app.partners.airalo.com → Developer (SECONDARY).'],
                    'airalo_client_secret' => ['label' => 'Airalo — Client Secret', 'config' => 'services.airalo.client_secret', 'env' => 'AIRALO_CLIENT_SECRET', 'secret' => true, 'hint' => 'Same page — shown once.'],
                    'quibity_api_key' => ['label' => 'Quibity / eSIM.sm — API Key', 'config' => 'services.quibity.api_key', 'env' => 'QUIBITY_API_KEY', 'secret' => true, 'hint' => 'esim.sm reseller dashboard (TERTIARY).'],
                ],
            ],
            'numbers' => [
                'label' => 'Number & SMS providers',
                'fields' => [
                    'getatext_api_key' => ['label' => 'Getatext — API Key', 'config' => 'services.getatext.api_key', 'env' => 'GETATEXT_API_KEY', 'secret' => true, 'hint' => 'getatext.com → Profile → API key. WALLET key — guard it.'],
                    'fivesim_api_key' => ['label' => '5sim — API Key', 'config' => 'services.fivesim.api_key', 'env' => 'FIVESIM_API_KEY', 'secret' => true, 'hint' => '5sim.net → Profile → Settings. JWT. WALLET key.'],
                    'smsactivate_api_key' => ['label' => 'SMS-Activate — API Key', 'config' => 'services.smsactivate.api_key', 'env' => 'SMSACTIVATE_API_KEY', 'secret' => true, 'hint' => 'sms-activate.org → Profile → API (backup).'],
                    'twilio_account_sid' => ['label' => 'Twilio — Account SID', 'config' => 'services.twilio.account_sid', 'env' => 'TWILIO_ACCOUNT_SID', 'secret' => false, 'hint' => 'twilio.com/console (permanent numbers + voice).'],
                    'twilio_auth_token' => ['label' => 'Twilio — Auth Token', 'config' => 'services.twilio.auth_token', 'env' => 'TWILIO_AUTH_TOKEN', 'secret' => true, 'hint' => 'Same page — reveal Auth Token.'],
                    'telnyx_api_key' => ['label' => 'Telnyx — API Key', 'config' => 'services.telnyx.api_key', 'env' => 'TELNYX_API_KEY', 'secret' => true, 'hint' => 'portal.telnyx.com → API Keys (permanent/voice backup).'],
                ],
            ],
            'payments' => [
                'label' => 'Payment gateways',
                'fields' => [
                    'paystack_secret_key' => ['label' => 'Paystack — Secret Key', 'config' => 'services.paystack.secret_key', 'env' => 'PAYSTACK_SECRET_KEY', 'secret' => true, 'hint' => 'dashboard.paystack.com → Settings → API Keys. Also signs webhooks.'],
                    'flutterwave_secret_key' => ['label' => 'Flutterwave — Secret Key', 'config' => 'services.flutterwave.secret_key', 'env' => 'FLUTTERWAVE_SECRET_KEY', 'secret' => true, 'hint' => 'dashboard.flutterwave.com → Settings → API.'],
                    'flutterwave_secret_hash' => ['label' => 'Flutterwave — Secret Hash', 'config' => 'services.flutterwave.secret_hash', 'env' => 'FLUTTERWAVE_SECRET_HASH', 'secret' => true, 'hint' => 'The verif-hash header used to verify webhooks.'],
                    'stripe_secret_key' => ['label' => 'Stripe — Secret Key', 'config' => 'services.stripe.secret_key', 'env' => 'STRIPE_SECRET_KEY', 'secret' => true, 'hint' => 'dashboard.stripe.com → Developers → API keys.'],
                    'stripe_webhook_secret' => ['label' => 'Stripe — Webhook Secret', 'config' => 'services.stripe.webhook_secret', 'env' => 'STRIPE_WEBHOOK_SECRET', 'secret' => true, 'hint' => 'Developers → Webhooks → signing secret (whsec_…).'],
                ],
            ],
            'integrations' => [
                'label' => 'Integrations',
                'fields' => [
                    'anthropic_api_key' => ['label' => 'Anthropic — API Key', 'config' => 'services.anthropic.api_key', 'env' => 'ANTHROPIC_API_KEY', 'secret' => true, 'hint' => 'console.anthropic.com → API Keys (maintenance loop, Section 29).'],
                    'github_maintenance_token' => ['label' => 'GitHub — Maintenance Token', 'config' => 'services.github_maintenance.token', 'env' => 'GITHUB_MAINTENANCE_TOKEN', 'secret' => true, 'hint' => 'Fine-grained PAT scoped to THIS repo only (opens CI-gated PRs).'],
                ],
            ],
        ];
    }

    /** Flat field-name => config-path map, for validation and lookups. */
    public static function fieldMap(): array
    {
        $map = [];
        foreach (self::schema() as $group) {
            foreach ($group['fields'] as $name => $meta) {
                $map[$name] = $meta['config'];
            }
        }

        return $map;
    }

    /**
     * The saved config-path => value map (only non-empty values are kept).
     * Cached forever; busted on save via flush().
     *
     * @return array<string, string>
     */
    public static function saved(): array
    {
        // Wrap the whole cache call, not just the DB read: this runs at boot on
        // every request, so a broken/unreachable cache or DB backend (e.g.
        // pre-install, or Redis momentarily down) must degrade to "nothing to
        // overlay" instead of taking the whole app down. .env still fills keys.
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                $stored = Setting::getValue(self::SETTING_KEY, []);

                return is_array($stored) ? $stored : [];
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Overlay every admin-saved credential on top of config() so all services
     * keep reading config('services.*') unchanged. Called once per request/job
     * from AppServiceProvider::boot(). Blank values are skipped so .env still
     * fills any field the admin left empty.
     */
    public static function applyToConfig(): void
    {
        foreach (self::saved() as $configPath => $value) {
            if ($value !== null && $value !== '') {
                config([$configPath => $value]);
            }
        }
    }

    /**
     * Persist a field-name => value map from the admin form. Blank fields are
     * removed (fall back to .env); non-blank fields override .env. Stored as a
     * config-path => value map so applyToConfig() can overlay it directly.
     *
     * @param  array<string, string|null>  $values  keyed by schema field name
     */
    public static function save(array $values): void
    {
        $fieldMap = self::fieldMap();
        $map = self::saved(); // start from what's already stored (config-path keyed)

        foreach ($values as $field => $value) {
            if (! isset($fieldMap[$field])) {
                continue; // ignore anything not in the schema
            }
            $configPath = $fieldMap[$field];
            $value = is_string($value) ? trim($value) : $value;

            if ($value === null || $value === '') {
                unset($map[$configPath]);
            } else {
                $map[$configPath] = $value;
            }
        }

        Setting::setValue(self::SETTING_KEY, $map, 'providers', 'Admin-managed API credentials (encrypted).');
        self::flush();
        self::applyToConfig(); // take effect within this same request
    }

    /** True if this field currently has an admin-saved value (any source). */
    public static function hasValue(string $field): bool
    {
        $config = self::fieldMap()[$field] ?? null;

        return $config !== null && ! empty(config($config));
    }

    /** A masked preview of a saved value for display (never the raw secret). */
    public static function preview(string $field): ?string
    {
        $config = self::fieldMap()[$field] ?? null;
        $value = $config ? (string) config($config) : '';

        if ($value === '') {
            return null;
        }

        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('•', $len);
        }

        return str_repeat('•', min($len - 4, 12)).substr($value, -4);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isProviderKey(string $key): bool
    {
        return $key === self::SETTING_KEY;
    }
}
