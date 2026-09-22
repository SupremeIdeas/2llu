# 2LLU — Batch 2: Fee Math, Idempotent Wallet, Debt Engine, AI Underwriting, FX
### Depends on Batch 1's schema (including `fee_settings`). Give this whole file to Claude Code as one prompt.

---

## Step 1 — Income-based eligibility (no AI needed for this part)

At onboarding, after `income_cycle`/`income_amount` are collected (Step 5b
of Batch 1), filter which plans are even shown:

```php
// app/Services/2LLU/EligibilityService.php
public function affordablePlans(User $user): Collection
{
    return CirclePlan::where('status', 'active')
        ->where('contribution_amount', '<=', $user->income_amount * config('llu.min_income_multiplier', 0.5))
        ->get();
}
```
Plans 1-2 per category need only this check + basic KYC. Plans 3-8 also
need the bank-statement path below before they unlock.

## Step 2 — Bank statement upload + Claude API underwriting

```php
// app/Services/2LLU/UnderwritingService.php
class UnderwritingService
{
    public function analyze(UserBankStatement $statement): array
    {
        $pdfBase64 = base64_encode(Storage::get($statement->pdf_url));

        $response = Http::withToken(config('services.anthropic.key'))
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 1024,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'document', 'source' => [
                            'type' => 'base64', 'media_type' => 'application/pdf', 'data' => $pdfBase64,
                        ]],
                        ['type' => 'text', 'text' => $this->prompt($statement->plan)],
                    ],
                ]],
            ]);

        $result = json_decode($response->json('content.0.text'), true);
        $statement->update([
            'ai_analysis' => $result,
            'status' => $result['eligible'] ? 'approved' : 'rejected',
        ]);
        return $result;
    }

    private function prompt(CirclePlan $plan): string
    {
        return <<<PROMPT
You are a financial underwriter for 2LLU, an Ajo/Esusu-style contribution
platform. Analyze this bank statement PDF and return ONLY JSON, no preamble.

Tasks:
1. Detect country and currency.
2. Calculate total credit volume over the statement period.
3. Detect a weekly, biweekly, or monthly recurring credit pattern.
4. This user wants plan "{$plan->name}": {$plan->contribution_amount} {$plan->currency} per {$plan->cycle_type}.
5. Eligible if credits of at least 2x the contribution amount appear in the
   matching cycle pattern for at least 50% of the statement period.
6. If eligible for higher tiers too, list them as recommendations only —
   never auto-upgrade the user.

Return exactly:
{"eligible": bool, "country": str, "currency": str, "avg_credit_per_cycle": number,
 "reason": str, "recommended_plan_ids": []}
PROMPT;
    }
}
```

Upload constraint (from Batch 1 Step 5b): **PDF only, max 2MB**. Reject
anything else client-side before it ever reaches storage or the API call —
don't burn a Claude API call validating a file type check could catch.

Flow: `pending` → `UnderwritingService::analyze()` → `approved`/`rejected`.
Approved unlocks that specific plan for that user. Store the full
`ai_analysis` JSON — it's your audit trail if a decision is ever disputed.

**Anti-fraud copy at upload time** (per your instruction): *"Uploading a
fabricated or altered statement is fraud. Accounts caught doing this are
permanently banned and may be reported."* Don't soften this — say it once,
clearly, before the upload field.

## Step 3 — Fee math: where every contribution actually goes

Depends on Batch 1's `fee_settings`. Each contribution splits three ways
the moment it's debited — not at payout time — so the pot always holds
exactly the net amount and there's no reconciliation guesswork later.

```php
Schema::table('circle_contributions', function (Blueprint $table) {
    $table->unsignedBigInteger('fee_amount')->default(0);          // → platform earnings
    $table->unsignedBigInteger('guardian_pool_amount')->default(0); // → guardian pool
    $table->unsignedBigInteger('net_amount')->default(0);           // → group pot, this is what actually funds a payout
});

// app/Services/2LLU/FeeCalculator.php
class FeeCalculator
{
    public function splitContribution(int $amount): array
    {
        $fee = (int) round($amount * FeeSetting::value('contribution_fee_percent') / 100);
        $guardianShare = (int) round($amount * FeeSetting::value('guardian_pool_fee_percent') / 100);
        return [
            'fee_amount' => $fee,
            'guardian_pool_amount' => $guardianShare,
            'net_amount' => $amount - $fee - $guardianShare,
        ];
    }

    public function payoutDeductions(int $pot): array
    {
        $processingFee = (int) FeeSetting::value('payout_processing_fee_flat');
        $securityFee = (int) round($pot * FeeSetting::value('platform_security_fee_percent') / 100);
        return [
            'processing_fee' => $processingFee,
            'security_fee' => $securityFee,
            'final_payout' => $pot - $processingFee - $securityFee,
        ];
    }
}
```

## Step 4 — Idempotent wallet + debt/penalty engine

Same contribution cron as before, now routing through `FeeCalculator` so
every paid contribution is split and recorded correctly, and every missed
one still creates debt on the full face-value amount — the fee split only
ever applies to money that actually arrives.

```php
// app/Jobs/ProcessCircleContributionsJob.php — updated handle()
public function handle(WalletService $wallet, FeeCalculator $fees): void
{
    CircleGroup::where('status', 'active')->whereDue()->each(function (CircleGroup $group) use ($wallet, $fees) {
        $collector = $group->members()->where('turn_number', $group->current_round)->first();

        $group->members()->where('status', '!=', 'suspended')->each(function ($member) use ($group, $wallet, $collector, $fees) {
            if (!$group->plan->collector_pays_on_own_round && $member->id === $collector?->id) return;

            $amount = $group->plan->contribution_amount;
            $contribution = CircleContribution::firstOrCreate(
                ['group_id' => $group->id, 'user_id' => $member->user_id, 'round_number' => $group->current_round],
                ['amount' => $amount, 'status' => 'pending']
            );
            if ($contribution->status !== 'pending') return; // idempotency guard

            try {
                $wallet->debit($member->user, $amount, $group->plan->currency, [
                    'reason' => 'circle_contribution', 'group_id' => $group->id,
                ]);

                $split = $fees->splitContribution($amount);
                $contribution->update(array_merge($split, ['status' => 'paid', 'paid_at' => now()]));

                CreditLedger::create([
                    'type' => 'platform_fee', 'source' => PayoutRequest::BUCKET_CIRCLE_PLATFORM_FEES,
                    'withdrawable' => true, 'amount' => $split['fee_amount'],
                    'reference' => "contrib-fee-{$contribution->id}",
                ]);
                GuardianPoolLedger::create([ // new, simple accumulator table — Batch 3 draws from it
                    'amount' => $split['guardian_pool_amount'], 'source' => "contrib-{$contribution->id}",
                ]);
            } catch (InsufficientBalanceException $e) {
                $contribution->update(['status' => 'missed']);
                $this->createDebt($member, $collector, $group, $contribution, $amount, $wallet);
            }
        });
    });
}

private function createDebt($member, $collector, $group, $contribution, $amount, WalletService $wallet): void
{
    $penalty = (int) round($amount * 0.05);
    $total = $amount + $penalty;

    CircleDebt::create([
        'debtor_user_id' => $member->user_id,
        'creditor_user_id' => $collector->user_id,
        'group_id' => $group->id,
        'contribution_id' => $contribution->id,
        'original_amount' => $amount,
        'penalty_amount' => $penalty,
        'total_amount' => $total,
        'status' => 'active',
    ]);

    // Compensate the collector immediately with the 5% — they still get the
    // rest once the debtor pays the debt. This is what makes it "no one
    // waits" even when someone defaults.
    if ($collector) {
        $wallet->credit($collector->user, $penalty, $group->plan->currency, [
            'reason' => 'circle_debt_compensation', 'group_id' => $group->id,
        ]);
    }

    $member->increment('warning_count');
    $member->update(['status' => $member->warning_count >= 2 ? 'suspended' : 'warning']);

    CircleDebtCreated::dispatch($member, $collector, $total); // in-app notification only
}
```

**Idempotency note**: the `firstOrCreate` + `status !== 'pending'` guard
above is what makes this job safe to retry — a re-queued job never
double-charges or double-debts the same round.

**Guardian pool accumulator** (simple ledger, Batch 3's earnings engine reads from it):
```php
Schema::create('guardian_pool_ledger', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('amount');
    $table->string('source'); // e.g. "contrib-{id}"
    $table->timestamp('created_at');
});
```

**Debt collection cron** — tries to clear debt before new contributions:
```php
// app/Jobs/RetryDebtCollectionJob.php — scheduled hourly
CircleDebt::where('status', 'active')->each(function ($debt) use ($wallet) {
    try {
        $wallet->debit($debt->debtor, $debt->total_amount, 'NGN', ['reason' => 'debt_repayment']);
        $wallet->credit($debt->creditor, $debt->total_amount, 'NGN', ['reason' => 'debt_repayment_received']);
        $debt->update(['status' => 'paid']);
    } catch (InsufficientBalanceException $e) { /* try again next run */ }
});
```

**Max-2-plans + zero-debt gate**, enforced at registration:
```php
abort_if(
    CircleMember::where('user_id', $user->id)->whereHas('group', fn($q) => $q->whereIn('status',['open','active']))->count() >= 2,
    422, 'You can only be in 2 active plans at a time.'
);
abort_if(
    CircleDebt::where('debtor_user_id', $user->id)->where('status', 'active')->exists(),
    422, 'Clear your outstanding debt before joining a new plan.'
);
```

## Step 5 — Payout: the transparent breakdown

Pot is the sum of `net_amount` across the round — never the raw
`contribution_amount` — since fees were already skimmed off at contribution
time. Two more deductions happen here, at collection time.

```php
// app/Services/2LLU/CirclePayoutService.php — requestMemberPayout, updated
public function requestMemberPayout(CircleMember $member, FeeCalculator $fees): array
{
    $group = $member->group;
    abort_unless($member->turn_number === $group->current_round, 422, 'Not your turn.');

    $roundContribs = CircleContribution::where('group_id', $group->id)
        ->where('round_number', $group->current_round)->where('status', 'paid');
    $expected = $group->plan->collector_pays_on_own_round ? $group->max_members : $group->max_members - 1;
    abort_unless($roundContribs->count() >= $expected, 422, 'Round not fully collected yet.');

    $pot = $roundContribs->sum('net_amount');
    $breakdown = $fees->payoutDeductions($pot);

    return array_merge($breakdown, ['pot' => $pot]); // shown to user as "Projected Payout" before they confirm
}

public function confirmMemberPayout(CircleMember $member, string $payoutAccountId, WithdrawalService $withdrawals, FeeCalculator $fees): PayoutRequest
{
    $projection = $this->requestMemberPayout($member, $fees);

    CreditLedger::create([
        'type' => 'platform_fee', 'source' => PayoutRequest::BUCKET_CIRCLE_PLATFORM_FEES,
        'withdrawable' => true, 'amount' => $projection['processing_fee'] + $projection['security_fee'],
        'reference' => "payout-fee-{$member->id}-{$member->group->current_round}",
    ]);

    $request = $withdrawals->initiate(
        user: $member->user, payeeType: 'user', sourceBucket: 'circle_pot',
        amount: $projection['final_payout'], payoutAccountId: $payoutAccountId,
    );

    $member->group->increment('current_round');
    return $request;
}
```

**User-facing payout screen** shows exactly this, before the "Collect"
button is even enabled — no surprises at the point they're expecting money:
```
Your Pot: ₦165,000
- Contribution Fees: ₦825
- Guardian Pool: ₦2,475
- Platform Security: ₦323
- Payout Fee: ₦100
= You Receive: ₦161,277
```
(Contribution Fees and Guardian Pool already left the pot at contribution
time — shown here for transparency on the full picture, not as a new
deduction at this step. Only Platform Security + Payout Fee are deducted
right now.)

## Step 6 — Multi-currency + FX

```php
// app/Jobs/SyncFxRatesJob.php — scheduled daily
class SyncFxRatesJob implements ShouldQueue
{
    public function handle(): void
    {
        $base = collect(['NGN','GHS','KES','ZAR','UGX','USD','GBP','EUR']);
        $response = Http::timeout(5)->get('https://api.exchangerate-api.com/v4/latest/USD')->json();

        foreach ($base as $currency) {
            FxRate::updateOrCreate(
                ['from_currency' => 'USD', 'to_currency' => $currency],
                ['rate' => $response['rates'][$currency] ?? null, 'updated_at' => now()]
            );
        }
    }
}
```
Explicit timeout again — same discipline as every other outbound HTTP call
in this build. A user only ever sees their own `circle_plans.currency` (or
their wallet's display currency) — never NGN-by-default for a Kenyan or
UK-based user. Paystack/Flutterwave countries use local currency directly;
Stripe countries default to USD/GBP/EUR.

## Done when
- A user under the income threshold never sees a plan they can't afford
- A rejected bank statement blocks that specific plan, approved unlocks it,
  and higher-tier eligibility shows as a recommendation, never an auto-join
- Every paid contribution splits correctly into fee/guardian-pool/net per
  `fee_settings`, and a missed one still creates debt on the full face value
- A missed contribution creates a debt with 5% penalty, compensates the
  collector immediately, and blocks new-plan joining until debt clears
- `ProcessCircleContributionsJob` is provably idempotent under retry
- The payout screen shows the exact breakdown (contribution fees, guardian
  pool, security fee, payout fee, final amount) before the user can collect
- FX rates sync daily with a bounded timeout; no currency ever defaults
  incorrectly for a non-Nigerian user
