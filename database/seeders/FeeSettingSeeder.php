<?php

namespace Database\Seeders;

use App\Models\FeeSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds the default fee_settings rows (2LLU Batch 1 §5d) that later
 * batches' FeeCalculator reads via FeeSetting::value(). Idempotent —
 * existing values are left untouched so a re-seed never clobbers an admin
 * edit made through the (not-yet-built) admin panel.
 */
class FeeSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // key => [value, min, max]
            'contribution_fee_percent' => [0.5, 0, 2],
            'guardian_pool_fee_percent' => [1.5, 0.5, 3],
            'payout_processing_fee_flat' => [100, 50, 500],
            'platform_security_fee_percent' => [0.2, 0, 1],
        ];

        foreach ($defaults as $key => [$value, $min, $max]) {
            FeeSetting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'min_value' => $min, 'max_value' => $max]
            );
        }
    }
}
