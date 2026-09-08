# 2LLU — Batch 10 Addendum: What's Still Missing + Full Stripe-Rail Admin Panel
### Research-grounded, not guessed. Depends on Batch 10 and Batch 6 (RBAC). Give this whole file to Claude Code as one prompt, alongside Batch 10 itself.

---

## The gap the earlier pasted document didn't cover

Informal ROSCAs between people who know each other are broadly tolerated
across most of your Stripe-supported countries — that's centuries of
"susu," "tanda," "hui," "chit fund" tradition. A **company** organizing and
algorithmically matching strangers into these circles, taking a fee, is a
different regulatory category — most US states require money-transmitter
licensing for exactly this activity absent an exemption; the UK requires
FCA payment-institution authorization; the EU requires PSD2/EMD2
authorization or operating under an authorized e-money institution.
**Stripe Connect solves the payment-processing compliance question. It
does not solve the licensing question.** Real companies doing this at
scale in the US (Esusu Inc., eMoneyPool) operate as licensed entities or
under a licensed partner for exactly this reason. This isn't optional
homework — get this in front of a fintech lawyer per target country before
`payment_rail = 'stripe_connect'` handles real money, the same way I've
flagged licensing for the African side every few batches.

---

## What Batch 10 is missing

### 1. Regional identity verification — Dojah/SmileID don't cover these countries

Your existing KYC providers are Africa-focused. For Stripe-rail users, use
**Stripe Identity** (integrates natively with Connect, verifies government
ID + selfie liveness, works across most Stripe-supported countries) instead
of trying to stretch Dojah/SmileID somewhere they don't operate.
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('stripe_identity_verification_id')->nullable();
});
```
KYC requirements differ meaningfully by country (SSN last-4 in the US,
National Insurance number in the UK, etc.) — Stripe Identity handles this
variance natively per-country, which is exactly why it's the right tool
here instead of building country-specific KYC logic yourselves.

### 2. Sanctions/AML screening — don't assume Stripe's onboarding is sufficient without documenting it

Stripe performs baseline AML/sanctions checks during Connect account
approval, but 2LLU's own compliance posture shouldn't rest entirely on an
unverified assumption about what that baseline covers. Run your own
sanctions-list screening at KYC time and log the result:
```php
Schema::create('sanctions_screening_results', function (Blueprint $table) {
    $table->id(); $table->foreignId('user_id')->constrained();
    $table->string('provider'); $table->enum('result', ['clear','flagged','pending_review']);
    $table->json('raw_response')->nullable(); $table->timestamps();
});
```
A flagged result blocks Connect onboarding completion and routes to manual
compliance review (Batch 6's RBAC — compliance role, not general admin).

### 3. Tax reporting — this will actually surprise your users if you don't handle it

Confirmed today: the federal 1099-K threshold is $20,000 and 200
transactions for 2025-2026 (the lower $600 threshold that was planned got
rolled back). **But several states set their own, much lower thresholds**
— meaning a collector's single payout in a modest circle could trigger a
1099-K even though it isn't taxable income, it's the return of pooled
savings. Stripe Connect has a built-in 1099 product for exactly this — use
it rather than building tax-form generation yourselves:
```php
// Enable via Stripe's Connect 1099 tooling, tag each contribution's
// application_fee/transfer with clear metadata so gross reported amounts
// are traceable back to actual circle activity, not opaque line items.
```
**User-facing disclosure, shown before joining a Stripe-rail circle:**
> "Because payments in your circle are processed through Stripe, you may
> receive a Form 1099-K when it's your turn to collect, even though this
> money is the return of your own and your circle-mates' pooled
> contributions, not income. Keep your 2LLU contribution history — you can
> export it any time — to support your tax filing if needed. We recommend
> speaking with a tax professional if you're unsure how to report this."
Build the export (contribution history CSV/PDF per user) now, not as an
afterthought during tax season.

### 4. Compliant collections — the Guardian model cannot transplant here as-is

The redesigned Guardians module (in-app mediation, reputation flags) was
built to be non-threatening — but even that framing risks running into the
US Fair Debt Collection Practices Act (FDCPA) and equivalent UK/EU consumer
credit regulation the moment it's communicating about money someone owes,
if it's structured like a "case" assigned to a person. For Stripe-rail
circles, replace Guardian case management entirely with **automated
dunning** — the same pattern Netflix or any subscription billing system
uses for a failed card: retry on a schedule, neutral notification copy,
no personal "case," no assigned human contacting anyone.
```php
Schema::create('stripe_dunning_attempts', function (Blueprint $table) {
    $table->id(); $table->foreignUuid('debt_id')->constrained('circle_debts');
    $table->unsignedTinyInteger('attempt_number');
    $table->enum('status', ['scheduled','succeeded','failed']);
    $table->timestamp('attempted_at')->nullable();
});
```
Notification copy: *"Your payment for this cycle didn't go through. We'll
retry automatically over the next few days — you can also update your
payment method any time."* No mention of penalties framed as debt
collection language; keep it in "billing retry" register, not "collections"
register, since the legal exposure hinges partly on how it's characterized.

### 5. Chargeback liability — a real Stripe Connect nuance the earlier document never addressed

By default, **your platform's Stripe account can be liable for a chargeback
on a Destination charge**, even though the money already moved to the
collector via `transfer_data.destination`. This is a genuine "read Stripe's
own Connect dispute-liability documentation before finalizing the charge
structure" item — depending on how you configure it (e.g., `on_behalf_of`),
liability can sometimes shift toward the connected account instead. This
needs a deliberate decision, not a default left unexamined, because the
failure mode is real money leaving your platform balance for a dispute on
a payment you never actually kept.

### 6. Refunds and disputes on this rail need their own logic — Batch 7's assumed a wallet that doesn't exist here

```php
// app/Services/2LLU/StripeRailRefundService.php
class StripeRailRefundService
{
    public function refundUnmatched(User $user): void
    {
        // Only for members in groups that haven't activated yet — same
        // "clean case" principle as Batch 7, executed differently: an
        // actual Stripe refund on each charged PaymentIntent, not a wallet
        // payout, since there's no wallet on this rail.
        CircleContribution::where('user_id', $user->id)
            ->whereHas('group', fn ($q) => $q->where('status', 'open'))
            ->where('status', 'paid')->each(function ($c) {
                Cashier::stripe()->refunds->create(['payment_intent' => $c->stripe_payment_intent_id]);
                $c->update(['status' => 'refunded']);
            });
    }
}
```
Active-group members still route to the same manual-review path as Batch
7 — that principle (can't cleanly claw back money that already funded
someone else's payout) doesn't change just because the rail did.

### 7. Currency-correct plan tiers, not NGN numbers with a currency symbol swapped

The 24-plan structure (Batch 1) needs genuinely researched, locally
sensible amounts per currency — a $2 "starter" weekly plan or a $50
"starter" weekly plan land very differently depending on the country's
actual cost of living. This is a per-currency research task before launch,
not something to guess into a seeder — flag it now so it doesn't get
skipped under launch pressure.

---

## Full admin panel for the Stripe rail — deliberately separate from the African admin views

Different rail, different operational reality, different admin needs.
Build `/admin/stripe-rail/*` as its own section (gated by Batch 6's RBAC,
new `stripe_compliance` role for the sanctions-review queue specifically):

- **`/admin/stripe-rail/connect-accounts`** — every user's Connect
  onboarding status (pending / restricted / active), with Stripe's own
  `requirements`/`disabled_reason` surfaced directly so admin can see
  *why* an account is stuck, not just that it is.
- **`/admin/stripe-rail/reconciliation`** — extends Batch 6's
  reconciliation job with a rail-specific view: Stripe balance vs.
  Stripe-rail internal liability, kept visually separate from the
  Paystack/Flutterwave numbers so a drift on one rail doesn't get lost in
  a blended total.
- **`/admin/stripe-rail/disputes`** — chargeback/dispute queue specific to
  Stripe, since timelines and evidence-submission requirements differ
  entirely from Paystack's dispute process.
- **`/admin/stripe-rail/tax-documents`** — 1099-K generation status per
  user, count of users approaching the threshold this year, export tools.
- **`/admin/stripe-rail/sanctions-review`** — flagged screening results
  pending manual compliance review, restricted to the `stripe_compliance`
  admin role, not general admin.
- **`/admin/stripe-rail/dunning`** — the automated collections-replacement
  queue from item 4: scheduled retries, success/fail rates, no per-case
  human assignment.
- **`/admin/stripe-rail/plans`** — currency-scoped plan management,
  separate from the NGN-based `/admin/circle-plans`, so admin isn't
  scrolling past 24 African plans to edit a USD tier.
- **`/admin/stripe-rail/compliance-copy`** — versioned, per-country
  disclosure text (US FDIC-non-insurance disclaimer, UK FSCS-non-coverage
  disclaimer, 1099-K notice, etc.) — admin-editable, same versioning
  discipline as the maintenance-mode message from Batch 7.

## Done when
- A lawyer has reviewed money-transmitter/payment-institution requirements
  for at least the first 2-3 launch countries on this rail — this gate
  comes before code review, not after
- Stripe Identity replaces Dojah/SmileID for every Stripe-rail user, with
  country-appropriate ID requirements handled natively
- Every Stripe-rail user sees the 1099-K disclosure before their first
  contribution, and can export their contribution history at any time
- Dunning notifications never use debt-collection language or imply a
  human "case" is assigned to their missed payment
- Chargeback liability configuration is a documented, deliberate decision
  — not Stripe Connect's untouched default, unexamined
- The full `/admin/stripe-rail/*` section is visually and functionally
  separate from the African admin views, with its own RBAC role for
  compliance-sensitive screens
