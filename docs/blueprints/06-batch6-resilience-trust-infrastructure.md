# 2LLU — Batch 6: Platform Resilience & Trust Infrastructure
### The "don't let this become a catastrophe" batch. Prioritized P0 → P2, not a wishlist.

---

## Why this batch exists

Every batch so far assumes the system behaves correctly. None of them tell
you when it doesn't. For a platform automatically moving pooled money,
assigning debt, and paying out across 49 countries, that's the actual risk
— not a single bug, but a bug nobody notices until the books don't balance
and it's too late to trace. This batch closes that gap.

---

## P0 — Build before real money moves through this. Not optional.

### 1. Ledger reconciliation — the single most important thing in this batch

Every naira/dollar in the system exists in two places: your internal ledger
(sum of wallet balances + pending debts + unclaimed pots) and the actual
balance sitting at Paystack/Flutterwave/Stripe. These two numbers must
always reconcile. If they ever drift silently, you find out from an
insolvency, not from a dashboard.

```php
// app/Jobs/ReconcileLedgerJob.php — scheduled every 4 hours
class ReconcileLedgerJob implements ShouldQueue
{
    public function handle(): void
    {
        foreach (['paystack', 'flutterwave', 'stripe'] as $gateway) {
            $externalBalance = app("{$gateway}.gateway")->getBalance();
            $internalLiability = Wallet::sum('main_balance')
                + Wallet::sum('backup_balance')
                + CircleDebt::where('status', 'active')->sum('total_amount') // money owed TO the platform, subtract
                - GuardianEarning::whereNull('paid_at')->sum('base_earned'); // money owed BY the platform, add back

            $drift = abs($externalBalance - $internalLiability);
            if ($drift > config('llu.reconciliation_tolerance', 100)) { // ₦100 tolerance for rounding, not more
                ReconciliationDrift::create(['gateway' => $gateway, 'external' => $externalBalance,
                    'internal' => $internalLiability, 'drift' => $drift]);
                AdminAlertNotification::route('mail', config('llu.finance_alert_email'))
                    ->send(new LedgerDriftDetected($gateway, $drift));
            }
        }
    }
}
```
This alone would have caught almost every real-world Ajo-app collapse
you've probably heard about — they don't fail because the code was wrong
once, they fail because nobody was watching the gap grow.

### 2. Solvency check — can we actually pay everyone what we owe them, right now

Separate from reconciliation: a running number that answers "if every
active group's collector requested payout today, could we cover it."

```php
public function totalPlatformLiability(): int
{
    return CircleGroup::where('status', 'active')->get()->sum(function ($g) {
        return CircleContribution::where('group_id', $g->id)
            ->where('round_number', $g->current_round)->where('status', 'paid')->sum('net_amount');
    });
}
```
Show this on the admin overview next to the actual gateway balance, always
visible, not buried. The day this number exceeds actual available balance
is the day you have a real problem — and you want to see it a week before
it happens, not the day someone's payout fails.

### 3. Role-based admin access — no single login can do everything

Right now "admin" is one undifferentiated role. That's a single point of
failure — one compromised login, or one honest mistake, can edit fees,
approve payouts, and disable Guardians all at once.

```php
Schema::create('admin_roles', function (Blueprint $table) {
    $table->id(); $table->string('name'); // finance, support, compliance, super_admin
    $table->json('permissions'); // ['fee_settings.edit', 'payouts.approve', 'users.suspend', ...]
});
```
Minimum split: **Finance** (fee settings, payout approval, reconciliation)
can't also **suspend users or edit Guardian rules**; **Support** (KYC
review, user support) can't touch fees or payouts. High-risk actions —
changing `fee_settings`, approving a payout above a threshold, toggling
maintenance/refund mode (Batch 7) — require a second admin's confirmation
(maker-checker), logged to `AuditLog` with both admin IDs.

### 4. Monitoring & alerting on every scheduled job

Every cron in this build — contribution collection, debt retry, FX sync,
escalation bonus payout, reconciliation itself — is worthless if it
silently stops running and nobody notices for two weeks.

```php
// wrap every scheduled job
$schedule->job(new ProcessCircleContributionsJob)->dailyAt('09:00')
    ->onFailure(fn () => AdminAlertNotification::send(new JobFailed('ProcessCircleContributionsJob')))
    ->emailOutputOnFailure(config('llu.ops_email'));
```
Pair with Laravel Horizon (if using Redis queues) for a live dashboard of
queue health, and an uptime monitor (even a simple heartbeat check) that
alerts if the reconciliation job hasn't run in the expected window —
a missed cron should page someone, not wait for a user complaint.

### 5. Backup & tested restore — not just "backups exist"

Automated daily DB backups are table stakes; the part people skip is
**testing the restore**. Schedule a quarterly drill: restore the latest
backup to a throwaway environment and verify the reconciliation job passes
against it. A backup you've never restored is a backup you don't actually
have.

---

## P1 — Build soon, before you're a bigger target than you are today

### 6. KYC document & bank statement encryption at rest

`user_bank_statements.pdf_url` and KYC selfies/IDs are high-value PII —
encrypt the storage disk (or use Laravel's encrypted file storage) and
restrict access to a narrow admin role from Item 3, not "any admin."

### 7. Chargeback / payment dispute handling

A user tops up via card, the money enters the group pot, then their card
issuer reverses the charge weeks later. You already have `PaymentDispute`
in the existing codebase — wire it so a reversed top-up creates a debt
against that user (same `circle_debts` mechanism from Batch 2) rather than
silently leaving a hole in a group's pot that nobody accounted for.

### 8. Session & device security

Standard fintech hardening: flag logins from a new device/location for
step-up verification, rate-limit login attempts, and log device fingerprints
on `PayoutAccount` changes specifically — a payout account switch is the
single most common account-takeover payout, worth its own alert.

### 9. Terms-of-service acceptance, versioned

```php
Schema::create('tos_acceptances', function (Blueprint $table) {
    $table->id(); $table->foreignId('user_id')->constrained();
    $table->string('tos_version'); $table->timestamp('accepted_at'); $table->string('ip_address');
});
```
If a dispute over the debt/penalty/Guardian mechanics ever gets contested,
you want provable evidence of exactly what version of the terms a specific
user agreed to and when.

---

## P2 — Good practice, not urgent

### 10. Feature flags for risky rollouts

New plan tiers, fee changes, or Guardian rule changes ship to 5% of groups
first, not everyone at once. Cheap to build now (a simple `feature_flags`
table gated in `FeeCalculator`/`MatchingService`), expensive to retrofit
after a bad rollout has already hit everyone.

---

## Regulatory note — flagging again, deliberately, one more time

This is the fourth time across this build I've flagged that you're holding
pooled third-party funds with automated debt enforcement across multiple
jurisdictions. I keep repeating it because it's the one item on this whole
list that engineering can't solve — it needs an actual conversation with a
fintech lawyer about money-transmitter/e-money licensing requirements in
Nigeria, Ghana, Kenya, South Africa, Côte d'Ivoire, and wherever your
biggest diaspora user base ends up concentrating. Everything else in this
batch reduces technical risk. This reduces legal risk, and it's the one
item where "we'll get to it" has the highest cost if ignored.

## Done when
- Reconciliation drift alerts fire correctly in a test where internal and
  external balances are deliberately mismatched
- An admin without the Finance role cannot approve a payout or edit fee
  settings, verified by an actual attempted-and-blocked test
- A deliberately failed scheduled job triggers a real alert within its
  expected window, not silently
- A quarterly restore drill is scheduled and documented, not just backups
  existing
