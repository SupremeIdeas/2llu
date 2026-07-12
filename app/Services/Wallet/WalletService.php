<?php

namespace App\Services\Wallet;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\OrphanChargeRefundedException;
use App\Jobs\AlertAdminJob;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletTransaction;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * WalletService — the single owner of wallet balance changes
 * (blueprint Sections 1.2 & 14.2). Every debit/credit is:
 *
 *   - Atomic: wrapped in a DB transaction with a pessimistic lockForUpdate on
 *     the wallet row, and always writes a wallet_transactions row recording
 *     balance_before / balance_after in the SAME transaction.
 *   - Serialized across processes: guarded by an atomic cache lock per wallet
 *     (Redis in production) so concurrent debits can never double-spend, even
 *     on a driver whose SELECT ... FOR UPDATE is a no-op.
 *   - Idempotent: passing a reference that already exists returns the existing
 *     transaction instead of charging again (money actions never blind-retry).
 *
 * Nothing else in the app mutates wallet balances directly.
 */
class WalletService
{
    /** Supported wallet currencies mapped to their balance column. */
    private const CURRENCY_COLUMNS = [
        'NGN' => 'ngn_balance',
        'USD' => 'usd_balance',
    ];

    private const SCALE = 4;

    /**
     * Debit a user's wallet. Throws InsufficientBalanceException if the
     * balance cannot cover the amount.
     *
     * @param  array<string, mixed>  $meta  Optional description/reference/idempotency_key.
     */
    public function debit(User $user, float $amount, string $currency = 'NGN', array $meta = []): WalletTransaction
    {
        return $this->apply($user, 'debit', $amount, $currency, $meta);
    }

    /** Credit a user's wallet (deposit/top-up). */
    public function credit(User $user, float $amount, string $currency = 'NGN', array $meta = []): WalletTransaction
    {
        return $this->apply($user, 'credit', $amount, $currency, $meta);
    }

    /** Refund a previous charge back to the wallet. */
    public function refund(User $user, float $amount, string $currency = 'NGN', array $meta = []): WalletTransaction
    {
        return $this->apply($user, 'refund', $amount, $currency, $meta);
    }

    /** Credit a referral profit-share reward (store credit). */
    public function reward(User $user, float $amount, string $currency = 'NGN', array $meta = []): WalletTransaction
    {
        return $this->apply($user, 'referral', $amount, $currency, $meta);
    }

    /**
     * Charge-then-deliver with an orphan-charge guard: debit the wallet, run
     * the delivery callback, and if delivery throws, AUTO-REFUND and alert —
     * never charge without delivering (money-safety rule 1.2). The refund is
     * itself idempotent so this can't double-refund.
     *
     * @template T
     * @param  Closure(WalletTransaction): T  $deliver
     * @return T
     */
    public function charge(User $user, float $amount, string $currency, Closure $deliver, array $meta = []): mixed
    {
        $debit = $this->debit($user, $amount, $currency, $meta);

        try {
            return $deliver($debit);
        } catch (Throwable $e) {
            $this->refund($user, $amount, $currency, [
                'description' => 'Auto-refund: downstream delivery failed',
                'reference' => 'refund:'.$debit->reference,
            ]);

            AlertAdminJob::dispatch(
                code: 'orphan_charge_refunded',
                message: "Charge to user {$user->id} was auto-refunded after delivery failed: {$e->getMessage()}",
                context: [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'debit_reference' => $debit->reference,
                    'exception' => $e::class,
                ],
            );

            throw new OrphanChargeRefundedException(
                "Charge to user {$user->id} auto-refunded after delivery failure.",
                $e,
            );
        }
    }

    /**
     * The atomic core. Locks the wallet (cache lock + row lock), reads the
     * balance, applies the delta, and writes the paired transaction row.
     */
    private function apply(User $user, string $type, float $amount, string $currency, array $meta): WalletTransaction
    {
        $currency = strtoupper($currency);
        $column = self::CURRENCY_COLUMNS[$currency]
            ?? throw new \InvalidArgumentException("Unsupported wallet currency [$currency].");

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Wallet amount must be positive.');
        }
        $amount = round($amount, self::SCALE);

        $reference = $meta['reference'] ?? $meta['idempotency_key'] ?? null;

        // Cross-process mutual exclusion per wallet (Redis in prod; array store
        // is per-process which is sufficient for single-process test runs).
        return Cache::lock("wallet:{$user->id}", 10)->block(5, function () use ($user, $type, $amount, $currency, $column, $meta, $reference) {
            return DB::transaction(function () use ($user, $type, $amount, $currency, $column, $meta, $reference) {
                // Idempotency: a matching reference means this action already
                // ran — return it rather than moving money twice.
                if ($reference !== null) {
                    $existing = WalletTransaction::query()
                        ->where('user_id', $user->id)
                        ->where('reference', $reference)
                        ->first();
                    if ($existing !== null) {
                        return $existing;
                    }
                }

                /** @var UserWallet $wallet */
                $wallet = UserWallet::query()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first()
                    ?? UserWallet::create(['user_id' => $user->id]);

                // Re-fetch with the lock if the row was just created.
                $wallet = UserWallet::query()->whereKey($wallet->getKey())->lockForUpdate()->first();

                $before = round((float) $wallet->{$column}, self::SCALE);
                $isDebit = $type === 'debit';
                $delta = $isDebit ? -$amount : $amount;
                $after = round($before + $delta, self::SCALE);

                if ($isDebit && $after < 0) {
                    throw new InsufficientBalanceException($user->id, $currency, $amount, $before);
                }

                $wallet->{$column} = $after;
                if ($isDebit) {
                    $wallet->total_spent = round((float) $wallet->total_spent + $amount, 2);
                } elseif ($type === 'credit') {
                    // Only genuine deposits/top-ups grow lifetime deposits;
                    // refunds and referral rewards do not.
                    $wallet->total_deposits = round((float) $wallet->total_deposits + $amount, 2);
                }
                $wallet->save();

                return WalletTransaction::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'amount' => $amount,
                    'currency' => $currency,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'reference' => $reference ?? (string) Str::uuid(),
                    'description' => $meta['description'] ?? null,
                    'status' => 'completed',
                ]);
            });
        });
    }
}
