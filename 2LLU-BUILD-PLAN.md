# 2LLU Build Plan — Sequencing, Priority, Model Assignment

> This is the authoritative "what's next" document for 2LLU, the same role
> `PROGRESS.md` plays for the NaaraSim history this repo was forked from.
> Read this before starting any batch. Full source text for every batch:
> `docs/blueprints/` (index: `docs/blueprints/README.md`).

---

## 0a. Owner decision: eSIM code stays, untouched, dormant

Batch 1's "Step 2 — Strip eSIM" (deleting eSIM-only files and surgically
editing ~80 files that mix eSIM with Numbers/SMS/admin) was attempted and
aborted: it's an invasive, high-token, high-risk audit for a payoff that
doesn't actually block building 2LLU's own features. Owner decision:
**skip it.** eSIM code, routes, models, and admin screens (`EsimHero`,
`EsimControlCenter`, `esim.qr`, `esim:sync*` commands, etc.) stay exactly
as they are in the codebase — inert as far as 2LLU is concerned, but not
deleted, not gated, not touched. Effort goes into building 2LLU's own
features additively on top, per the phases below. Revisit an actual eSIM
removal later only if it becomes a real problem (e.g. it starts showing up
in 2LLU's own admin nav in a confusing way) — at that point a narrow,
targeted fix beats another full-codebase audit.

## 0. Where we are

2LLU is a git-history fork of `SupremeIdeas/NaaraSim` (`main` @ `d921fd4`,
530 commits carried over). At fork time the codebase is byte-for-byte
NaaraSim — eSIM code, NaaraSim branding, and all still present. **Batch 1
below is what turns this into 2LLU**; nothing product-specific has been
built yet. Graphify is installed and the initial graph is built
(`graphify-out/`, regenerated automatically on commit via the installed git
hook — see `CLAUDE.md`).

NaaraSim's existing wallet, KYC, payout-account, PayoutRequest,
merchant/partner, and numbers/SMS provider plumbing all carry over
untouched and get **reused**, not rebuilt — Batch 1 only strips eSIM. Read
`PROGRESS.md` for what that inherited infrastructure already does; it's
still accurate for the parts 2LLU keeps.

---

## 1. Batch 2 — resolved

Batches 3, 9, 10, and 12 all declared a dependency on "Batch 2" — the money
engine (`UnderwritingService`, the `circle_debts` penalty engine,
`FeeCalculator`, wallet debit/credit, `WithdrawalService`,
`GuardianPoolLedger`, FX sync) — that wasn't in the original batch set given
to this session. The owner supplied the real document afterward:
`docs/blueprints/02-batch2-fee-math-wallet-debt-underwriting-fx.md`. No
reconstruction was needed. Phase 0.5/2 below now build from that document
directly.

---

## 2. Dependency graph

```
Batch 1 (foundation)
  ├─→ Batch 2 (money engine: underwriting, debt, fees, FX)
  │     ├─→ Batch 3 (matching, guardians, admin, renewal)
  │     │     └─→ Batch 4 (geo seed: Africa) ─→ Batch 5 (geo seed: intl)
  │     ├─→ Batch 9 (referral priority, honest verification)
  │     └─→ Batch 10 (Stripe Connect rail)
  │           ├─→ Batch 10a (intl compliance + admin)
  │           │     └─→ Batch 11 (rollout gate) ─┐
  │           └───────────────────────────────────┼─→ Batch 12 (intl plans/fees/payment method)
  │                                                 └─→ Batch 13 (intl UI/UX)
  └─→ Batch 6 (resilience/trust: reconciliation, RBAC, monitoring)
        └─→ Batch 7 (maintenance mode + refunds)

Batch 8 (hosting portability) — cross-cutting discipline from Batch 1
  onward (storage/queue abstraction); the concrete migration-runbook work
  slots in once core features exist, before the first real deploy.
```

Batch 6's P0 items (reconciliation, solvency check, RBAC) are explicitly
"build before real money moves through this" — they gate go-live for
*both* rails, not just their own phase slot.

---

## 3. Model assignment — the rule

Pick the cheapest model that won't need a redo. Three tiers, mapped to what
this build actually needs:

- **Haiku 4.5** — fully-specified, low-ambiguity, mechanical work: seeding
  scripts against a documented external API with sanity-check thresholds
  already given, copy/CTA text swaps, schema migrations with no business
  logic attached.
- **Sonnet 5** — the default. Most service/Livewire/UI build-out, matching
  and renewal flow, admin CRUD screens, webhook glue, integration work
  where the pattern is clear even if the code isn't trivial.
- **Opus 5** — money-correctness and legal/security-critical reasoning:
  anything where a subtle mistake means wrong money movement, a compliance
  gate that can be silently bypassed, or a security boundary (RBAC,
  maker-checker) that has to actually hold. Worth the extra cost because a
  bug here isn't a bug, it's a solvency or licensing incident.

Where a single batch mixes both (e.g. Batch 7's maintenance-mode UI vs. its
refund-eligibility logic), the phase table below splits it.

---

## 4. Phases, in build order

| Phase | Batch(es) | What | Model | Why |
|---|---|---|---|---|
| 0 | — | Fork, Graphify install + build, blueprints saved | done | — |
| 1 | Batch 1 | Clone/strip/brand/design system, `circle_*` schema, `TurnSortingService`, 24-plan seed, fee-settings panel | **Sonnet 5** | Well-specified feature build, no open money-correctness questions |
| 2 | Batch 2 | Income eligibility filter, Claude-based bank-statement underwriting (`UnderwritingService`), `FeeCalculator` fee-split math, idempotent contribution debit + `circle_debts` penalty/compensation engine, transparent payout breakdown, daily FX sync | **Opus 5** | This is the money engine — wallet debits, debt creation, fee splitting, payout math. A subtle bug here means wrong money movement, not a cosmetic defect |
| 3 | Batch 3 | Geo schema, matching engine, group UI, Guardians v2, renewal voting, admin | **Sonnet 5**, except **Opus 5** for the Guardian base-pay/escalation-bonus earnings math (real-money distribution, has an explicitly open design question to resolve first) | Split by risk within the batch |
| 4 | Batch 4 → 5 | Geo seeding: 5 Paystack countries, then 44 Stripe countries | **Haiku 4.5** | Fully mechanical: documented API, given sanity-check tolerances, no judgment calls |
| 5 | Batch 6 | Ledger reconciliation, solvency check, RBAC + maker-checker, job monitoring/alerting, tested backup/restore | **Opus 5** | Explicitly P0 "before real money moves"; pure correctness + security-boundary reasoning |
| 6 | Batch 7 | Maintenance-mode middleware/poll/UI on **Sonnet 5**; `RefundEligibilityService`/`RefundService` clean-case-vs-manual-review logic on **Opus 5** | mixed | Refund correctness is money-path; the popup/poll UI isn't |
| 7 | Batch 9 | Referral-weighted priority, honest forensic verification, structured urgency input | **Sonnet 5** | Clear spec, integrates with (not redesigns) Batch 2's underwriting |
| 8 | Batch 8 | Storage/queue/env abstraction discipline — start applying from Phase 1 onward, not as a bolt-on; the R2 config, `TrustProxies`, log rotation, `.env.example` split, and migration-day dry run land as their own pass once core features exist, before the first real production deploy | **Sonnet 5** | Systematic infra work; the actual migration rehearsal is an ops exercise, not model-graded |
| 9 | Batch 10 + 10a | Stripe Connect rail: onboarding gate, autopilot off-session charging, webhook handling, chargeback-liability decision, Stripe Identity, sanctions screening, tax/1099-K, dunning, `/admin/stripe-rail/*` | **Opus 5** | Highest-stakes code in the whole plan — cross-border real-money charges plus the licensing/compliance surface flagged repeatedly across these docs |
| 10 | Batch 11 | Master kill switch + per-country legal-gating checklist, enforced (not just UI-suggested) | **Opus 5** | A gate that can be silently bypassed is a compliance incident, not a bug |
| 11 | Batch 12 | International plan tiers + Plaid/Open Banking on **Sonnet 5**; SCA/off-session payment-method consent flow and rail-scoped fee math on **Opus 5** | mixed | Payment-authorization correctness is the risky slice, plan CRUD isn't |
| 12 | Batch 13 | Region routing, education screens, international registration/login/dashboard UX | **Sonnet 5** | UI/copy/flow work; low reasoning risk once the rails underneath (9–11) are solid |

**Non-negotiable, cutting across every phase from 9 onward:** a lawyer
reviews money-transmitter/licensing requirements per target country before
that country's Stripe-rail checklist (Batch 11) can reach `live`. This is
a human/business gate, not something any model tier substitutes for — it's
restated in nearly every later batch document for a reason.

---

## 5. Next step

**Phase 1 — Batch 1.** Fork/tooling and all 14+1 blueprint documents are in
place; nothing further is blocked. Start the actual strip-eSIM/rebrand/
schema work per `docs/blueprints/01-batch1-clone-strip-foundation.md`.
Update this line as phases complete, same discipline `PROGRESS.md` already
uses for the inherited NaaraSim work.
