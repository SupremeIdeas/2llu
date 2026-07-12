<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds the default pricing configuration (blueprint Section 13.3). These are
 * the values the PricingEngine reads via Setting::getValue(); they are safe,
 * profitable defaults and are fully admin-editable later. Idempotent —
 * existing values are left untouched so a re-seed never clobbers admin edits.
 */
class PricingSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Global + per-type eSIM markup
            ['pricing.default_markup_pct', 30, 'pricing', 'Default global markup %'],
            ['pricing.esim_local_markup_pct', 30, 'pricing', 'Local plan markup %'],
            ['pricing.esim_regional_markup_pct', 35, 'pricing', 'Regional plan markup %'],
            ['pricing.esim_global_markup_pct', 40, 'pricing', 'Global plan markup %'],

            // Per-provider SMS / number markup
            ['pricing.sms_markup_pct.getatext', 50, 'pricing', 'Getatext (US) markup %'],
            ['pricing.sms_markup_pct.fivesim', 55, 'pricing', '5sim (global) markup %'],
            ['pricing.sms_markup_pct.smsactivate', 55, 'pricing', 'SMS-Activate markup %'],
            ['pricing.sms_markup_pct.twilio', 45, 'pricing', 'Twilio number markup %'],
            ['pricing.sms_markup_pct.telnyx', 45, 'pricing', 'Telnyx number markup %'],

            // Profit floors (MarginGuard)
            ['pricing.minimum_profit_usd', 0.50, 'pricing', 'MarginGuard absolute floor (USD)'],
            ['pricing.sms_min_profit', 0.01, 'pricing', 'Per-SMS profit floor (USD)'],

            // Currency display
            ['pricing.currency_display', 'USD', 'pricing', 'User-facing currency'],
            ['pricing.ngn_rate_source', 'auto', 'pricing', 'NGN rate source (auto|manual)'],

            // Low-balance alerts
            ['pricing.low_balance_alert.esimgo', 100, 'pricing', 'eSIM Go wallet alert (USD)'],
            ['pricing.low_balance_alert.getatext', 10, 'pricing', 'Getatext wallet alert (USD)'],
            ['pricing.low_balance_alert.fivesim', 10, 'pricing', '5sim wallet alert (USD)'],
        ];

        foreach ($defaults as [$key, $value, $group, $description]) {
            if (Setting::query()->where('key', $key)->exists()) {
                continue;
            }
            Setting::setValue($key, $value, $group, $description, isPublic: false);
        }
    }
}
