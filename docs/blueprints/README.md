# 2LLU Blueprints

Source documents for the 2LLU build, saved verbatim from the batches supplied
2026-09-08. Read in this order — later batches assume earlier ones are done
and passing their "Done when" checks. Full sequencing, priority, and model
assignment per batch: **`/2LLU-BUILD-PLAN.md`** at the repo root.

| # | File | Covers |
|---|---|---|
| — | `00-brand-bible.md` | Voice, legal-safe copy, disclosures (both rails) |
| 1 | `01-batch1-clone-strip-foundation.md` | Fork, strip eSIM, brand/design system, core schema, turn-sorting, fee settings |
| **2** | **missing — see build plan Phase 0.5** | Underwriting/Claude bank-statement analysis, debt engine, fee splitting, wallet/withdrawal, FX sync |
| 3 | `03-batch3-matching-guardians-admin.md` | Geo schema, matching, group UI, Guardians v2, renewal voting, admin |
| 4 | `04-batch4-geo-seeding.md` | Country→state→LGA seed data, 5 Paystack countries |
| 5 | `05-batch5-geo-seeding-stripe-international.md` | Same pipeline, 44 Stripe countries |
| 6 | `06-batch6-resilience-trust-infrastructure.md` | Ledger reconciliation, solvency check, RBAC, job monitoring, backups |
| 7 | `07-batch7-maintenance-mode-refunds.md` | Maintenance popup, poll, clean-case vs manual-review refunds |
| 8 | `08-batch8-hosting-portability.md` | Namecheap cPanel ↔ Cloudways VPS portability, R2, migration runbook |
| 9 | `09-batch9-referral-priority-honest-verification.md` | Referral-weighted turn priority, honest fraud verification |
| 10 | `10-batch10-stripe-connect-native-circles.md` | Stripe Connect payment rail for Stripe-supported countries |
| 10a | `10a-batch10-addendum-international-compliance-admin.md` | Licensing gap, Stripe Identity, sanctions, tax/1099-K, dunning, chargeback liability, `/admin/stripe-rail/*` |
| 11 | `11-batch11-stripe-rail-rollout-control.md` | Master kill switch + per-country legal-gating checklist |
| 12 | `12-batch12-international-plans-fees-payment-method.md` | International plan tiers, Plaid/Open Banking, rail-scoped fees, SCA payment-method setup |
| 13 | `13-batch13-international-ui-ux.md` | Region routing, education screens, international registration/login/dashboard UX |

## The one thing every batch repeats, and why it's not boilerplate

Every batch that touches real money or cross-border user data restates the
same warning: get a fintech lawyer to review money-transmitter/licensing
requirements per country before that batch's code handles real funds. That
repetition is deliberate on the author's part, not padding — treat every
occurrence of it in these documents as a real gate, not a formality to
scroll past.
