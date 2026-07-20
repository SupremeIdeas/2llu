<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-toggled merchant-programme settings (ROADMAP §Layer 3). Off by default;
 * the reseller margin is admin-owned (a merchant never prices their own goods)
 * and MarginGuard still floors every resulting price.
 */
class MerchantSettings
{
    public const FLAG = 'merchants.enabled';

    public const MARGIN = 'merchants.reseller_margin_pct';

    public static function enabled(): bool
    {
        return (bool) Setting::getValue(self::FLAG, false);
    }

    /** Global reseller margin % applied over retail (per-merchant can override). */
    public static function resellerMarginPct(): float
    {
        return (float) Setting::getValue(self::MARGIN, 10.0);
    }
}
