# 2LLU — Batch 10: Stripe Connect Rail (Stripe-Supported Countries Only)
### Paystack/Flutterwave circles are completely untouched by this batch — different rail, different code path, routed by country. Give this whole file to Claude Code as one prompt.

---

## The routing decision — made once, at group creation, never mixed

Matching already locks every group to a single country (never crosses
borders). That means the payment rail can be decided once, at the group
level, and every member in that group uses the same rail — no per-member
branching, no hybrid groups.

```php
Schema::table('circle_groups', function (Blueprint $table) {
    $table->enum('payment_rail', ['wallet', 'stripe_connect'])->default('wallet');
});
```

```php
// MatchingService::joinOrCreateGroup — one line added when creating a new group
'payment_rail' => in_array($user->country, config('llu.stripe_connect_countries'))
    ? 'stripe_connect' : 'wallet',
```

`config('llu.stripe_connect_countries')` is a curated subset of Batch 5's
44 Stripe-supported countries — **verify Connect account availability
specifically against Stripe's own Connect documentation before finalizing
this list**, since which countries can hold a Connect account is a
narrower, separately-published list from general Stripe payments
availability. Don't assume the two lists are identical.

Everything from Batches 1-9 — wallet, debt engine, fee splitting, KYC,
underwriting, referral priority, Guardians — **continues to run exactly as
built, unchanged, for every `payment_rail = 'wallet'` group.** This batch
only adds a second, parallel path for `payment_rail = 'stripe_connect'`
groups. Nothing here modifies the existing services.

## Step 1 — Connect onboarding gate, required before joining

Since the rotation means everyone eventually collects, every member needs
a working Connect account *before* they're allowed to join — not
discovered as broken when it's finally their turn.

```php
// MatchingService — extra check for stripe_connect rail groups only
if ($railFor === 'stripe_connect') {
    abort_unless(
        $user->stripe_connect_id && $this->connectAccountReady($user),
        403, 'Complete your payout account setup before joining this circle.'
    );
}

private function connectAccountReady(User $user): bool
{
    $account = Cashier::stripe()->accounts->retrieve($user->stripe_connect_id);
    return $account->charges_enabled && $account->payouts_enabled;
}
```

```php
// app/Livewire/StripeConnectOnboarding.php
public function onboard()
{
    $user = Auth::user();
    if (!$user->stripe_connect_id) {
        $account = Cashier::stripe()->accounts->create([
            'type' => 'express',
            'country' => $user->country, // dynamic — the pasted reference hardcoded 'GB', that breaks for every other country
            'capabilities' => ['card_payments' => ['requested' => true], 'transfers' => ['requested' => true]],
        ]);
        $user->update(['stripe_connect_id' => $account->id]);
    }

    $link = Cashier::stripe()->accountLinks->create([
        'account' => $user->stripe_connect_id,
        'refresh_url' => route('stripe.connect.retry'),
        'return_url' => route('stripe.connect.return'),
        'type' => 'account_onboarding',
    ]);
    return redirect()->to($link->url);
}
```
`account.updated` webhook (Step 4) keeps `charges_enabled`/`payouts_enabled`
in sync so the gate above always reflects Stripe's current truth, not a
stale local flag.

## Step 2 — Autopilot charging, not a manual "click to pay" button

The reference flow you found required the user to click "Fund My Turn"
every cycle — that breaks the autopilot design the rest of 2LLU already
promises (the wallet system debits automatically; this rail should feel
the same to the user, even though the money moves completely differently
underneath). Use a saved payment method with off-session confirmation
instead of a checkout-session-per-cycle:

```php
// app/Jobs/ProcessStripeConnectContributionsJob.php — scheduled, parallel to the wallet cron
class ProcessStripeConnectContributionsJob implements ShouldQueue
{
    public function handle(FeeCalculator $fees): void
    {
        CircleGroup::where('payment_rail', 'stripe_connect')
            ->where('status', 'active')->whereDue()->each(function (CircleGroup $group) use ($fees) {
                $collector = $group->members()->where('turn_number', $group->current_round)->first();

                $group->members()->where('status', '!=', 'suspended')->each(function ($member) use ($group, $collector, $fees) {
                    if (!$group->plan->collector_pays_on_own_round && $member->id === $collector?->id) return;

                    $contribution = CircleContribution::firstOrCreate(
                        ['group_id' => $group->id, 'user_id' => $member->user_id, 'round_number' => $group->current_round],
                        ['amount' => $group->plan->contribution_amount, 'status' => 'pending']
                    );
                    if ($contribution->status !== 'pending') return;

                    $amount = $group->plan->contribution_amount;
                    $applicationFee = $fees->splitContribution($amount)['fee_amount'] + $fees->splitContribution($amount)['guardian_pool_amount'];

                    try {
                        $intent = Cashier::stripe()->paymentIntents->create([
                            'amount' => (int) round($amount * 100),
                            'currency' => strtolower($group->plan->currency),
                            'customer' => $member->user->stripe_customer_id,
                            'payment_method' => $member->user->default_payment_method_id,
                            'off_session' => true,
                            'confirm' => true,
                            'application_fee_amount' => (int) round($applicationFee * 100),
                            'transfer_data' => ['destination' => $collector->user->stripe_connect_id],
                            'metadata' => ['contribution_id' => $contribution->id, 'group_id' => $group->id],
                        ]);
                        $contribution->update(['status' => 'paid', 'stripe_payment_intent_id' => $intent->id]);
                        // final 'paid' confirmation still comes from the webhook in Step 4 — this just records the attempt
                    } catch (\Stripe\Exception\CardException $e) {
                        // Same debt mechanism as the wallet rail — reused, not duplicated.
                        $this->createDebt($member, $collector, $group, $contribution, $amount);
                    }
                });
            });
    }
}
```
`circle_debts` and the 5%-penalty/compensation logic from Batch 2 apply
identically here — a failed off-session charge is functionally the same
"missed contribution" event as a failed wallet debit, so it reuses the
exact same debt ledger and retry cron, just triggered from a card decline
instead of an insufficient wallet balance.

## Step 3 — Fee handling: Stripe does the split, you record it for parity

`application_fee_amount` lands in 2LLU's own Stripe balance automatically
at charge time — no separate transfer step needed, unlike the wallet rail
where `FeeCalculator` splits money that's already sitting in your system.
Still record it identically to the wallet rail so your books read the same
regardless of which rail a group used:

```php
// triggered from the webhook in Step 4, on payment_intent.succeeded
CreditLedger::create([
    'type' => 'platform_fee', 'source' => PayoutRequest::BUCKET_CIRCLE_PLATFORM_FEES,
    'amount' => $split['fee_amount'], 'reference' => "stripe-contrib-fee-{$contribution->id}",
]);
GuardianPoolLedger::create(['amount' => $split['guardian_pool_amount'], 'source' => "stripe-contrib-{$contribution->id}"]);
```

**No `WithdrawalService`/payout-request step for the disbursement leg on
this rail** — the collector's share already landed directly in their
Connect account via `transfer_data.destination`, and Stripe's own
automatic daily payout schedule moves it to their real bank account from
there. Your `WithdrawalService`/`PayoutRequest` engine stays exactly as
built, used only for the wallet rail and for admin's own platform-fee
withdrawals (which now pulls from 2LLU's Stripe balance too, alongside
Paystack/Flutterwave — same admin-payout-parity pattern from Batch 1,
no new code needed there).

## Step 4 — Webhooks, reusing the existing gateway-agnostic controller

Your `PaymentWebhookController` already routes by `{gateway}` in the URL —
add `stripe_connect` handling inside it rather than a new controller:
```php
match ($event->type) {
    'payment_intent.succeeded' => $this->confirmContribution($event->data->object),
    'payment_intent.payment_failed' => $this->handleFailedContribution($event->data->object),
    'account.updated' => $this->syncConnectAccountStatus($event->data->object),
    default => null,
};
```
Signature verification, idempotency-by-event-id, and queued processing —
same discipline as every other webhook in this build, not a new standard.

## What stays exactly the same for stripe_connect groups

Turn sorting (Batch 9), matching's country-lock, renewal voting, Guardians
mediation, KYC/underwriting eligibility — none of it cares which rail moves
the money. Only contribution collection and disbursement differ.

## Done when
- A group's `payment_rail` is set correctly at creation and never changes
  mid-cycle
- A user can't join a `stripe_connect` group without `charges_enabled` and
  `payouts_enabled` both true on their Connect account
- Contributions charge automatically off-session, no manual "pay" click,
  matching the autopilot feel of the wallet rail
- A declined card creates a debt through the exact same `circle_debts`
  mechanism as a wallet-rail miss — verified as genuinely shared code, not
  a second implementation
- Platform + Guardian-pool fee amounts recorded from Stripe rail
  contributions reconcile against `application_fee_amount` exactly
- Zero changes to any `payment_rail = 'wallet'` code path — verified by
  diff, not assumption
