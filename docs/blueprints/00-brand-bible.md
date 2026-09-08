# 2LLU Brand Bible v2 — Corrected + Extended for Both Rails
### Tagline stays: "Building Goals Together, One Circle at a Time"

---

## What changed from the pasted draft, and why

The voice, values, and warmth of the original are kept entirely — "smart
elder brother, not a bank," patience over hype, no MMM-adjacent language.
What changed is anything that made a **factual claim contradicting what
we've actually built**, since shipping those would be a legal liability,
not a trust-builder.

| Original claim | Problem | Fixed to |
|---|---|---|
| "Money moves directly between members" | False for the wallet rail — money sits in your Paystack/Flutterwave balance with fee deductions and a debt engine acting on it | "2LLU takes a small, always-visible fee to run the platform. On our international rail, contributions pass straight through Stripe to your circle-mate. Either way, you see every fee before you pay." |
| "We do not collect... BVN" | False — advanced KYC collects BVN | Removed the denial; BVN is now listed under Account Data with an explanation of why (identity verification, required for higher-tier plans) |
| "Cannot exit until every member has had a turn" | Contradicts the refund system (Batch 7) | "If you haven't been matched into an active circle yet, you can request a full refund any time. Once your circle is active, exiting early affects everyone else's turn — talk to us first, and we'll help." |
| No mention of Stripe, Claude/Anthropic, Plaid, Guardians | GDPR/NDPR require disclosing every data processor | All four added explicitly to the Privacy Policy's data-sharing section |
| "Right to Your Turn... guaranteed" | Unconditional guarantee is a real liability if enough members default in one round | "Your turn is protected by our debt-and-penalty system — if someone misses a payment, they're charged a penalty that goes straight to you as compensation while we recover the rest." |

---

## 1. Brand Voice — unchanged, this part was already right

Warm, honest, patient, community-first. Simple English, Naija flavor when
it fits. Never: "invest," "returns," "double your money," "passive
income," "guaranteed profit." Always: "contribute," "support," "circle,"
"turn," "goal," "build," "together," "patience."

## 2. About Us — kept, one addition

> 2LLU is a community-driven platform that helps visionaries reach their
> goals through collective support, structured circles, and patience.
> ...
> **2LLU runs two ways to fit where you are**: for Nigeria, Ghana, Kenya,
> South Africa, and Côte d'Ivoire, circles run through Paystack and
> Flutterwave. For everywhere else we support, circles run through Stripe.
> Same trust, same patience — the payment rail underneath just matches
> your country.

## 3. What We Do — kept as-is, applies identically to both rails

## 4. Vision — kept as-is

## 5. Landing Page Copy — corrected footer disclaimer, rest kept

**Corrected footer line** (replaces the "money moves directly between
members" claim platform-wide):
> "2LLU does not invest your money and does not lend money. We always show
> our fee before you contribute. See how your rail works: [Africa] [International]"

## 6. Legal Messaging — the corrected sections

**Disclaimer (footer + signup)** — kept mostly as-is, with the fee
acknowledgment added:
> 2LLU is a community support and contribution platform. 2LLU does not
> offer investment products, loans, or guarantee any financial returns.
> 2LLU charges a transparent platform fee, always shown before you
> contribute. Participation in any circle is voluntary.

**Exit Rule — corrected**:
> You can request a refund any time before your circle becomes active. Once
> active, you're committed through your circle's full cycle — this
> protects everyone else's turn, the same way you're protected once it's
> yours. If something's genuinely wrong, talk to us before it becomes a
> missed payment.

**Right to Your Turn — corrected**:
> Once your circle is active, your turn is scheduled and protected by our
> debt-and-penalty system — if a circle-mate misses a payment, they're
> charged a penalty paid straight to you, while we work to recover the
> rest.

## 7. One-liners — kept, add one for the international side

> "Circles have crossed oceans before. tandas, susu, hui, kye — 2LLU is
> just the next chapter."

---

## International Addendum — required, not decorative

This section didn't exist in the pasted draft at all. Required for the
Stripe rail specifically, per Batches 10-13:

### Terms of Service additions
- **SCA/card-mandate consent**: "By adding a payment method, you authorize
  2LLU to automatically charge it for your circle contributions on their
  scheduled dates, without asking each time. You can revoke this any time."
- **Tax disclosure**: "Because international contributions are processed
  through Stripe, you may receive a Form 1099-K when it's your turn to
  collect. This reflects payment volume, not taxable income — export your
  contribution history any time to support your filing."
- **Not FDIC/FSCS insured**: "2LLU is not a bank. Funds are not covered by
  FDIC (US) or FSCS (UK) deposit insurance."
- **Dunning, not collection**: replace "admin decision" removal language
  with: "If a payment doesn't go through, we retry automatically over the
  next few days. You'll never be contacted by a collections agent."

### Privacy Policy additions
- **Processors disclosed by name**: Paystack, Flutterwave, Stripe,
  Anthropic (bank-statement/eligibility analysis), Plaid/Open Banking
  (income verification, international rail only), Dojah/SmileID/Stripe
  Identity (KYC).
- **GDPR-specific rights** (EU/UK users): access, correction, erasure,
  **portability**, **restriction of processing**, **objection** — the
  pasted draft only listed the first three; GDPR requires all six.
- **Retention period**: needs actual verification against AML record-
  keeping requirements per jurisdiction before publishing a number — the
  pasted "2 years" wasn't checked against anything and shouldn't ship
  unverified, since many jurisdictions require 5+ years for AML records.
- **Complaints/regulator escalation path**: required disclosure for FCA-
  regulated activity in the UK specifically — "If you're unhappy with how
  we've resolved a complaint, you can escalate to [Financial Ombudsman
  Service / relevant body]."

### What's still genuinely missing, flagging rather than guessing
- **Cookie/tracking consent banner** — required under EU/UK ePrivacy rules,
  not built anywhere yet in this whole project.
- **Marketing email consent language** — CAN-SPAM (US) and PECR (UK) have
  different opt-in/opt-out requirements; the current "you can opt out of
  promos" line is too thin for UK/EU specifically.
- **Actual retention period**, once verified against real AML requirements
  per country — placeholder above, not a final number.
