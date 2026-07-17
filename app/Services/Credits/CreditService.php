<?php

namespace App\Services\Credits;

use App\Models\CreditLedger;
use App\Models\User;
use App\Models\UserWallet;
use App\Support\CreditSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * NaaraCredits ledger service (loyalty module). The single owner of credit
 * balance changes, with the same discipline as the money WalletService:
 *   - every change runs under a per-user lock + DB transaction,
 *   - writes a credit_ledger row with balance_after in the same transaction,
 *   - is idempotent by reference (an earn/spend never double-applies),
 * so a replayed ad postback, a double-tapped check-in, or a retried job all
 * move credits exactly once.
 *
 * Credits are NOT money — they never touch the money wallet columns.
 */
class CreditService
{
    private const SCALE = 2;

    public function balance(User $user): float
    {
        return (float) ($user->wallet?->naara_credits ?? 0);
    }

    /**
     * Grant credits. `reference` makes it idempotent per earning event
     * (e.g. "checkin:{userId}:{date}", "ad:{externalTxnId}").
     */
    public function earn(User $user, float $credits, string $source, ?string $reference = null, ?string $description = null): CreditLedger
    {
        return $this->apply($user, 'earn', $credits, $source, $reference, $description);
    }

    /** Spend credits (e.g. redeemed at checkout). Throws if the balance is short. */
    public function spend(User $user, float $credits, string $source, ?string $reference = null, ?string $description = null): CreditLedger
    {
        return $this->apply($user, 'spend', $credits, $source, $reference, $description);
    }

    private function apply(User $user, string $type, float $credits, string $source, ?string $reference, ?string $description): CreditLedger
    {
        $credits = round($credits, self::SCALE);
        if ($credits <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return Cache::lock("credits:{$user->id}", 10)->block(5, function () use ($user, $type, $credits, $source, $reference, $description) {
            return DB::transaction(function () use ($user, $type, $credits, $source, $reference, $description) {
                if ($reference !== null) {
                    $existing = CreditLedger::where('user_id', $user->id)->where('reference', $reference)->first();
                    if ($existing !== null) {
                        return $existing; // idempotent replay
                    }
                }

                /** @var UserWallet $wallet */
                $wallet = UserWallet::where('user_id', $user->id)->lockForUpdate()->first()
                    ?? UserWallet::create(['user_id' => $user->id]);
                $wallet = UserWallet::whereKey($wallet->getKey())->lockForUpdate()->first();

                $before = round((float) $wallet->naara_credits, self::SCALE);
                $after = round($type === 'spend' ? $before - $credits : $before + $credits, self::SCALE);
                if ($after < 0) {
                    throw new InsufficientCreditsException($user->id, $credits, $before);
                }

                $wallet->naara_credits = $after;
                $wallet->save();

                return CreditLedger::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'source' => $source,
                    'amount' => $credits,
                    'balance_after' => $after,
                    'reference' => $reference ?? (string) Str::uuid(),
                    'description' => $description,
                ]);
            });
        });
    }

    // ---- earn helpers -------------------------------------------------------

    /** Daily check-in. Returns the credits granted, or 0 if still on cooldown. */
    public function checkIn(User $user): float
    {
        if (! CreditSettings::enabled()) {
            return 0.0;
        }
        $amount = (float) CreditSettings::get('checkin_daily', 5);
        if ($amount <= 0) {
            return 0.0;
        }

        $wallet = $user->wallet ?? UserWallet::create(['user_id' => $user->id]);
        $cooldown = (int) CreditSettings::get('checkin_cooldown_hours', 24);
        if ($wallet->last_checkin_at && $wallet->last_checkin_at->diffInHours(now()) < $cooldown) {
            return 0.0; // still on cooldown
        }

        $this->earn($user, $amount, 'checkin', 'checkin:'.$user->id.':'.now()->format('Y-m-d-H'), 'Daily check-in reward');
        $wallet->forceFill(['last_checkin_at' => now()])->save();

        return $amount;
    }

    /** Whether the user may check in right now. */
    public function canCheckIn(User $user): bool
    {
        if (! CreditSettings::enabled() || (float) CreditSettings::get('checkin_daily', 5) <= 0) {
            return false;
        }
        $last = $user->wallet?->last_checkin_at;
        $cooldown = (int) CreditSettings::get('checkin_cooldown_hours', 24);

        return $last === null || $last->diffInHours(now()) >= $cooldown;
    }

    /** One-time bonuses (signup, first purchase) — idempotent by their reference. */
    public function grantOnce(User $user, float $credits, string $source, string $description): void
    {
        if (! CreditSettings::enabled() || $credits <= 0) {
            return;
        }
        try {
            $this->earn($user, $credits, $source, "{$source}:{$user->id}", $description);
        } catch (\Throwable) {
            // best-effort — a loyalty bonus must never break the flow that triggered it
        }
    }
}
