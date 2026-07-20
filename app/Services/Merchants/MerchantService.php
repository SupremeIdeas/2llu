<?php

namespace App\Services\Merchants;

use App\Models\KycVerification;
use App\Models\Merchant;
use App\Models\User;
use App\Services\Kyc\KycService;
use App\Support\Auditor;
use App\Support\MerchantSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single owner of merchant lifecycle (ROADMAP §Layer 3.1). A KYB-verified
 * (KYC L3) user applies to become a merchant; an admin approves, which activates
 * the storefront and grants the additive `merchant` role. Suspend/reject flip the
 * status and role accordingly. The reseller margin is never set here — it's
 * admin-owned pricing config.
 */
class MerchantService
{
    public function __construct(private KycService $kyc)
    {
    }

    /**
     * Apply to become a merchant. Requires the programme to be on and the user
     * KYB-verified (L3). Returns the existing application if one is in flight.
     *
     * @param  array{business_name: string, brand_color?: string|null}  $data
     *
     * @throws MerchantException
     */
    public function apply(User $user, array $data): Merchant
    {
        if (! MerchantSettings::enabled()) {
            throw new MerchantException('The merchant programme is not open right now.');
        }
        if (! $this->kyc->hasLevel($user, KycVerification::L3)) {
            throw new MerchantException('Complete business (KYB) verification first.');
        }

        $existing = Merchant::query()->where('owner_user_id', $user->id)
            ->whereIn('status', [Merchant::PENDING, Merchant::ACTIVE])->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $data) {
            $merchant = Merchant::create([
                'owner_user_id' => $user->id,
                'business_name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'brand_color' => $data['brand_color'] ?? null,
                'status' => Merchant::PENDING,
            ]);

            Auditor::log('merchant.applied', 'Merchant', $merchant->id, ['user_id' => $user->id]);

            return $merchant;
        });
    }

    /** Admin approves an application — activates it and grants the merchant role. */
    public function approve(Merchant $merchant, User $admin): Merchant
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']), 403);

        if ($merchant->status === Merchant::PENDING || $merchant->status === Merchant::SUSPENDED) {
            DB::transaction(function () use ($merchant, $admin) {
                $merchant->forceFill([
                    'status' => Merchant::ACTIVE,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                    'reason' => null,
                ])->save();
                $merchant->owner->assignRole('merchant');
            });
            Auditor::log('merchant.approved', 'Merchant', $merchant->id, ['by' => $admin->id]);
        }

        return $merchant;
    }

    /** Admin suspends an active merchant — storefront off, role removed. */
    public function suspend(Merchant $merchant, User $admin, string $reason = 'Suspended'): Merchant
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']), 403);

        DB::transaction(function () use ($merchant, $admin, $reason) {
            $merchant->forceFill([
                'status' => Merchant::SUSPENDED,
                'reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
            $merchant->owner->removeRole('merchant');
        });
        Auditor::log('merchant.suspended', 'Merchant', $merchant->id, ['by' => $admin->id, 'reason' => $reason]);

        return $merchant;
    }

    /** Admin rejects a pending application. */
    public function reject(Merchant $merchant, User $admin, string $reason = 'Not approved'): Merchant
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']), 403);

        if ($merchant->status === Merchant::PENDING) {
            $merchant->forceFill([
                'status' => Merchant::REJECTED,
                'reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
            Auditor::log('merchant.rejected', 'Merchant', $merchant->id, ['by' => $admin->id, 'reason' => $reason]);
        }

        return $merchant;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'merchant';
        $slug = $base;
        $i = 1;
        while (Merchant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
