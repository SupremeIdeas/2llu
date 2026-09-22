# 2LLU — Batch 12: International Plans, Rail-Scoped Fees, Payment Method Setup
### Depends on Batch 10/11. Give this whole file to Claude Code as one prompt.

---

## Part A — International plans: structurally different, not just recolored NGN numbers

### The insight worth building around: pay cycles differ by market

Nigeria's hustle-economy weekly cash flow is the norm the original 24-plan
structure was built for. US/UK/EU income is overwhelmingly biweekly or
monthly (salaried pay cycles) — a heavy weekly-plan lineup doesn't match
how these users actually get paid. Structure follows that:

```php
Schema::create('international_plans', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->char('currency', 3); // USD, GBP, EUR
    $table->enum('cycle_type', ['weekly','biweekly','monthly']);
    $table->unsignedSmallInteger('cycle_duration');
    $table->unsignedTinyInteger('members_per_group')->default(12);
    $table->unsignedBigInteger('contribution_amount'); // minor units (cents/pence)
    $table->unsignedBigInteger('registration_fee')->default(0);
    $table->boolean('requires_income_verification')->default(false);
    $table->decimal('min_income_multiplier', 3, 2)->default(0.50);
    $table->enum('status', ['active','draft'])->default('draft');
    $table->timestamps();
});
```

**Starting tiers — reasonable defaults, not final numbers.** These still
need local cost-of-living validation per country before launch, same
caveat as the NGN tiers got:
- **Weekly (4 tiers, not 8)**: $10, $25, $50, $100 — fewer, because weekly
  is the less-common cadence here, not the default like it is in Nigeria.
- **Biweekly (8 tiers)**: $20, $50, $100, $200, $350, $500, $750, $1,000
- **Monthly (8 tiers)**: $50, $100, $200, $350, $500, $750, $1,000, $2,000

`/admin/stripe-rail/plans` — separate CRUD screen from `/admin/circle-plans`,
so admin isn't scrolling past 24 NGN plans to find a USD tier.

### Part B — Income verification: this rail gets something genuinely better than PDF uploads

The PDF-bank-statement-plus-forensics system (Batches 2 and 9) exists
because reliable open-banking infrastructure isn't broadly available across
2LLU's African markets. **It is** in the US, UK, and EU — Plaid (US/Canada,
also EU coverage) and Open Banking / TrueLayer (UK/EU) let a user connect
their real bank account and pull verified transaction data directly,
no PDF, no forgery risk, no forensic PDF-metadata guesswork.

```php
Schema::create('plaid_income_verifications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained();
    $table->foreignUuid('plan_id')->nullable()->constrained('international_plans');
    $table->string('plaid_item_id');
    $table->json('verified_transactions')->nullable(); // structured, not a PDF to forensically inspect
    $table->enum('status', ['pending','approved','rejected'])->default('pending');
    $table->timestamps();
});
```
This eliminates the entire fraud-PDF problem for this rail — there's
nothing to forge when the data comes directly from the user's bank via an
authenticated API connection. Claude API can still help with the same
eligibility judgment (recurring pattern detection, plan recommendation)
against this structured data instead of a PDF, reusing the same
`UnderwritingService` logic shape from Batch 2, just fed cleaner input.

### Part C — Rail-scoped fees: Stripe's own processing cost is higher than Paystack's

```php
Schema::table('fee_settings', function (Blueprint $table) {
    $table->enum('rail', ['wallet', 'stripe_connect'])->default('wallet');
    $table->dropUnique(['key']);
    $table->unique(['key', 'rail']);
});
```
Stripe's card-processing cost (roughly 2.9% + $0.30 for domestic cards,
more for international cards) is meaningfully higher than Paystack's.
**Don't copy the African rail's fee defaults onto this one** — the
`contribution_fee_percent`/`platform_security_fee_percent` need their own
values calculated against Stripe's actual current fee schedule for your
target countries, which varies by card type and needs its own pass before
launch, not a guess made here.

## Part D — Payment method setup: the piece Batch 10 assumed but never built

Batch 10's autopilot charging referenced `default_payment_method_id` as if
it already existed. It didn't — here's the actual setup flow, and it's not
optional: EU/UK PSD2/SCA rules require explicit, captured consent before a
card can be charged off-session on a recurring basis.

```php
// app/Livewire/StripePaymentMethodSetup.php
public function setup()
{
    $user = Auth::user();
    if (!$user->stripe_customer_id) {
        $customer = Cashier::stripe()->customers->create(['email' => $user->email]);
        $user->update(['stripe_customer_id' => $customer->id]);
    }

    $setupIntent = Cashier::stripe()->setupIntents->create([
        'customer' => $user->stripe_customer_id,
        'usage' => 'off_session', // explicit future off-session consent — this is the SCA mandate
        'payment_method_types' => ['card'],
    ]);

    return ['client_secret' => $setupIntent->client_secret]; // frontend confirms via Stripe Elements
}

public function confirmed(string $paymentMethodId)
{
    $user = Auth::user();
    Cashier::stripe()->paymentMethods->attach($paymentMethodId, ['customer' => $user->stripe_customer_id]);
    $user->update(['default_payment_method_id' => $paymentMethodId]);
}
```

**Consent copy, shown alongside the card form — this text IS the mandate:**
> "By adding this card, you authorize 2LLU to automatically charge it for
> your circle contributions on their scheduled dates, without asking each
> time. You can update or remove this authorization any time in your
> payment settings."

This is a hard prerequisite for joining any `stripe_connect` group —
`MatchingService`'s existing Connect-readiness check (Batch 10, Step 1)
should also verify `default_payment_method_id` is set, not just that the
Connect account itself is ready.

## Done when
- International plans show correct currency formatting and never mix with
  NGN plans anywhere in the UI
- A US/UK/EU user's eligibility check runs against real bank data via
  Plaid/Open Banking, with zero PDF upload in the flow
- Fee settings for the Stripe rail are configured independently from the
  wallet rail's defaults, based on actual Stripe fee-schedule math
- A user cannot join a `stripe_connect` group without both Connect
  readiness and a confirmed `default_payment_method_id` with captured
  off-session consent
