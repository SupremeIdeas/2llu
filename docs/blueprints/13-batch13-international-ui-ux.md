# 2LLU — Batch 13: International UI/UX (Registration, Login, Onboarding, CTAs)
### Depends on Batches 10-12. Give this whole file to Claude Code as one prompt.

---

## The design principle: same brand, different door

The coffee/milk/butter design system from Batch 1 stays — that's your
brand, and switching visual identity per market would undercut the "one
platform, one trust signal" goal. What changes is **the copy, the
onboarding sequence, and the entry point** — because a US or UK visitor
doesn't arrive already understanding what a rotating savings circle is the
way most of your African users do.

## Step 1 — Region routing at entry, not buried in a form

`/join` — the very first screen, before any registration form:
> "Where are you contributing from?"
Two paths, presented as equal, not one primary and one secondary:
- **Nigeria, Ghana, Kenya, South Africa, Côte d'Ivoire** → existing
  African registration flow (Batches 1-9), unchanged.
- **International** → new flow below, gated by Batch 11's country status.

IP-based geolocation suggests a default, but the user picks explicitly —
never silently routed. If International is picked but the detected
country isn't `live` yet in `stripe_rail_country_status`:
> "2LLU is coming soon to [Country]. Leave your email and we'll let you
> know the moment it's available." — waitlist capture, not a dead end.

```php
Schema::create('stripe_rail_waitlist', function (Blueprint $table) {
    $table->id(); $table->string('email'); $table->char('country', 2);
    $table->timestamps();
});
```

## Step 2 — Education screen before registration starts

This is the "make it make more sense" piece you asked for. Three short
slides, skippable, shown once per user:
1. *"Circles have existed for centuries"* — tandas in Mexico, susu in West
   Africa, hui in China, kye in Korea, chit funds in India. Grounds the
   concept in something globally recognized, not a foreign import.
2. *"How it works"* — simple visual: 12 people, one collects each round,
   everyone collects once, no interest, no bank.
3. *"Built on Stripe"* — a trust signal specific to this audience: Stripe's
   own logo/badge, "bank-level security," PCI compliance mention. African
   users trust Paystack/Flutterwave already; this audience trusts Stripe's
   name specifically, so lead with it here in a way the African flow
   doesn't need to.

## Step 3 — Registration flow, distinct sequence from the African side

1. Country/currency confirmation (carried from Step 1)
2. **Stripe Identity verification** (Batch 10 addendum) — replaces the
   selfie+BVN/NIN flow entirely for this rail
3. Income declaration, in local currency
4. Browse **international plans only** (Batch 12) — never shows NGN plans
5. **Payment method setup** (Batch 12, Part D) — the SCA-consent card flow
6. For higher tiers: **Plaid/Open Banking connection** (Batch 12, Part B)
   — replaces PDF bank-statement upload entirely
7. Fund purpose + urgency (same structured input as Batch 9 — this part
   is genuinely shared, no reason to rebuild it differently)
8. Matched into a `payment_rail = 'stripe_connect'` group automatically

## Step 4 — CTA and copy differences, specific and deliberate

| | African flow | International flow |
|---|---|---|
| Primary CTA | "Join the Ajo" | "Join a Circle" |
| Trust badge | Paystack/Flutterwave logos | Stripe logo, "Bank-level security" |
| Onboarding tone | Assumes familiarity | Explains from first principles |
| KYC step label | "Verify your identity" | "Verify with Stripe Identity" |
| Income step | Bank statement PDF upload | "Connect your bank securely" (Plaid/Open Banking) |

"Ajo" tests well with an audience that already knows the word; "Circle" is
the term that translates without explanation for everyone else. Don't
force one vocabulary across both audiences — this is exactly the kind of
market-specific copy decision worth keeping deliberately separate.

## Step 5 — Login: same account system, rail-aware dashboard

No separate login system — one account, one password, same auth. What
changes is what the dashboard shows once logged in: a user on the
international rail sees their `international_plans` groups, USD/GBP/EUR
formatting throughout, and Stripe-specific account management (Step 6).
A user never sees NGN amounts or African-rail UI elements unless they're
actually a member of an African-rail group too (diaspora members can be
in both, per the earlier diaspora batch — the dashboard shows both
sections only when both apply, not by default).

## Step 6 — Payout/tax self-service: reuse Stripe's own hosted dashboard, don't rebuild it

Rather than building bank-detail editing and 1099-K download screens from
scratch, use Stripe's own Express Dashboard for Connect accounts:
```php
// "Manage my payout account" button
$loginLink = Cashier::stripe()->accounts->createLoginLink($user->stripe_connect_id);
return redirect()->to($loginLink->url);
```
This gives users a genuine, Stripe-hosted interface for updating bank
details, viewing balance/payout history, and downloading their 1099-K when
issued — all covered by Stripe's own compliance and security, not
something 2LLU needs to maintain in parallel. Link it prominently from the
international dashboard rather than building a shadow version of it.

## Step 7 — Group experience: shared components, locale-aware formatting

The group dashboard, chat, member directory, and round tracker from Batch
3 are the same Livewire components for both rails — no reason to duplicate
them. What needs to change:
- Currency formatting via the group's `plan.currency` (`$`, `£`, `€`)
- Date formatting per locale (MM/DD/YYYY vs DD/MM/YYYY)
- The round tracker's "collect" action triggers the Stripe-rail flow
  (no manual click needed — Batch 10's autopilot already handles the
  charge; the UI here is informational, not transactional)

## Step 8 — Admin note: this whole UI is invisible until Batch 11's gate opens

None of Steps 1-7 render for a country that isn't `live` in
`stripe_rail_country_status` — the waitlist from Step 1 is the only thing
a user in a not-yet-approved country ever sees. This keeps the UI build
fully decoupled from the legal-clearance timeline: build and test all of
this now, ship it dark, flip Batch 11's switch per country when ready.

## Done when
- A user can complete the full international registration flow end to end
  in a staging environment, with a country manually set to `live`
- Setting that country back to anything other than `live` immediately
  shows the waitlist screen instead, with no partial/broken UI state
- Currency and date formatting are correct throughout for at least USD,
  GBP, and EUR
- The Stripe Express Dashboard login link works and correctly shows the
  connected account's real payout/tax information
- No African-rail terminology, currency, or UI element appears anywhere
  in the international flow, and vice versa
