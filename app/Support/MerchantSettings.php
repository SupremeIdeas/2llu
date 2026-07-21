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

    // Eligibility to migrate to a merchant — meet ANY one (ROADMAP §Layer 3).
    public const MIN_SPEND = 'merchants.min_spend_usd';        // lifetime spend

    public const ENROLLMENT_FEE = 'merchants.enrollment_fee_usd'; // one-time fast route

    public const MIN_REFERRALS = 'merchants.min_referrals';    // referred users

    public static function enabled(): bool
    {
        return (bool) Setting::getValue(self::FLAG, false);
    }

    /** Global reseller margin % applied over retail (per-merchant can override). */
    public static function resellerMarginPct(): float
    {
        return (float) Setting::getValue(self::MARGIN, 10.0);
    }

    public static function minSpendUsd(): float
    {
        return (float) Setting::getValue(self::MIN_SPEND, 75.0);
    }

    public static function enrollmentFeeUsd(): float
    {
        return (float) Setting::getValue(self::ENROLLMENT_FEE, 50.0);
    }

    public static function minReferrals(): int
    {
        return (int) Setting::getValue(self::MIN_REFERRALS, 1000);
    }
}
