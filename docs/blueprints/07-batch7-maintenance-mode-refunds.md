# 2LLU — Batch 7: Maintenance Mode + Structured Refunds
### Depends on Batch 6's RBAC (maintenance/refund toggles are high-risk actions). Give this whole file to Claude Code as one prompt.

---

## Step 1 — Maintenance mode: popup on action, not a full-screen block

```php
Schema::create('maintenance_settings', function (Blueprint $table) {
    $table->id();
    $table->boolean('is_active')->default(false);
    $table->boolean('refunds_enabled')->default(false); // separate switch, per your spec
    $table->text('message');
    $table->foreignId('updated_by')->nullable()->constrained('users');
    $table->timestamps();
});
```

```php
// app/Http/Middleware/MaintenanceModePopup.php
class MaintenanceModePopup
{
    public function handle(Request $request, Closure $next)
    {
        $setting = MaintenanceSetting::first();
        if (!$setting?->is_active) return $next($request);

        // Reads (GET) pass through untouched — people can still browse.
        // Only state-changing requests get intercepted, which is exactly
        // "popup only when someone tries to DO something."
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        // Refund routes stay live independently, gated by their own switch.
        if ($request->routeIs('refunds.*') && $setting->refunds_enabled) {
            return $next($request);
        }

        return response()->json([
            'maintenance' => true,
            'message' => $setting->message,
            'refunds_enabled' => $setting->refunds_enabled,
        ], 423); // 423 Locked — frontend catches this globally and renders the popup, not a page swap
    }
}
```
Apply this globally, not per-route — it needs to catch every write attempt
platform-wide, which is exactly the "everything pauses" behavior you want,
without a full-screen takeover since GET requests are untouched.

**Admin controls** (behind Batch 6's Finance/super_admin role, maker-checker
on toggling `is_active` — this is exactly the kind of action that shouldn't
go live from one person fat-fingering a switch):
```
/admin/maintenance — toggle is_active, edit message (rich text, versioned:
keep old messages in a maintenance_message_history table so there's a
record of what was said and when), toggle refunds_enabled independently.
```

## Step 2 — Poll: "if we're not migrated in 24h, would you want a refund"

```php
Schema::create('maintenance_polls', function (Blueprint $table) {
    $table->id();
    $table->string('question');
    $table->boolean('is_active')->default(true);
    $table->timestamp('opens_at');
    $table->timestamp('closes_at');
    $table->timestamps();
});
Schema::create('maintenance_poll_votes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('poll_id')->constrained('maintenance_polls');
    $table->foreignId('user_id')->constrained();
    $table->boolean('agree');
    $table->timestamps();
    $table->unique(['poll_id', 'user_id']);
});
```
Poll voting is itself a write action, so it needs the same treatment as
refunds — allowlisted through the middleware alongside `refunds.*` even
while everything else is paused, since voting on "should we open refunds"
has to work precisely when refunds might be needed. Admin dashboard shows
live agree/disagree tallies while the poll is open.

## Step 3 — Refund eligibility: this is the part that needs real care

Not everyone gets the same refund treatment, and pretending otherwise
would be dishonest to users. Two clearly different situations:

**Clean case — refund immediately, fully automated:**
A member whose group hasn't activated yet (`circle_groups.status = 'open'`)
hasn't had their contribution touch anyone else's payout. Their money is
sitting untouched. This is a straightforward return of their own funds.

**Hard case — needs admin review, not automation:**
A member in an *active* group has already had part of their contribution
fund a previous collector's payout. You cannot cleanly "refund" that money
without clawing it back from someone who already legitimately collected
it — that's not a refund, that's undoing a completed transaction between
two other people. Automating this would create exactly the kind of
silent, unaccountable money movement Batch 6's reconciliation system
exists to catch. This case routes to manual admin review, explicitly, not
into the same automated flow.

```php
// app/Services/2LLU/RefundEligibilityService.php
class RefundEligibilityService
{
    public function check(User $user): array
    {
        $unmatchedContributions = CircleMember::where('user_id', $user->id)
            ->whereHas('group', fn ($q) => $q->where('status', 'open'))
            ->with('group.plan')->get()
            ->sum(fn ($m) => $m->group->plan->registration_fee + $m->group->plan->contribution_amount);

        $freeWalletBalance = $user->wallet->main_balance;

        $activeGroupCount = CircleMember::where('user_id', $user->id)
            ->whereHas('group', fn ($q) => $q->where('status', 'active'))->count();

        return [
            'auto_refundable' => $unmatchedContributions + $freeWalletBalance,
            'needs_manual_review' => $activeGroupCount > 0,
            'active_group_note' => $activeGroupCount > 0
                ? 'You have active group contributions already funding another member\'s payout — these need manual review, not automatic refund.'
                : null,
        ];
    }
}
```

**Refund request flow** — reuses the existing `WithdrawalService`, same
rails as every other payout in this build, not a parallel system:
```php
Schema::create('refund_requests', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained();
    $table->unsignedBigInteger('amount');
    $table->enum('source', ['auto_unmatched', 'manual_active_group']);
    $table->enum('status', ['pending', 'approved', 'processed', 'rejected'])->default('pending');
    $table->text('admin_note')->nullable();
    $table->timestamps();
});
```
```php
// app/Services/2LLU/RefundService.php
class RefundService
{
    public function request(User $user, RefundEligibilityService $eligibility): RefundRequest
    {
        abort_unless(MaintenanceSetting::first()?->refunds_enabled, 423, 'Refunds are not currently open.');
        $check = $eligibility->check($user);
        abort_if($check['auto_refundable'] <= 0 && !$check['needs_manual_review'], 422, 'No refundable balance found.');

        return RefundRequest::create([
            'user_id' => $user->id,
            'amount' => $check['auto_refundable'],
            'source' => $check['needs_manual_review'] ? 'manual_active_group' : 'auto_unmatched',
            'status' => $check['needs_manual_review'] ? 'pending' : 'approved', // clean case skips review
        ]);
    }

    public function process(RefundRequest $refund, WithdrawalService $withdrawals): void
    {
        abort_unless($refund->status === 'approved', 422, 'Refund must be approved first.');

        // Pull the member out of any open groups so their slot frees up for matching.
        CircleMember::where('user_id', $refund->user_id)
            ->whereHas('group', fn ($q) => $q->where('status', 'open'))->delete();

        $withdrawals->initiate(
            user: $refund->user, payeeType: 'user', sourceBucket: 'user_refund',
            amount: $refund->amount, payoutAccountId: $refund->user->defaultPayoutAccount()->id,
        );

        $refund->update(['status' => 'processed']);
    }
}
```
The `auto_unmatched` case can process immediately once requested — no admin
click needed, that's the whole point of it being the "clean" case.
`manual_active_group` sits at `pending` until Finance-role admin (Batch 6)
reviews and either approves with a note or rejects with a reason — never
silent, always logged to `AuditLog`.

## Step 4 — User-facing message, exactly as you described

Popup (not full-screen), triggered on any attempted action:
> "2LLU is temporarily paused while we upgrade our systems. We're sorry for
> the disruption. [If refunds are open:] If you haven't been matched into a
> group yet, you can request a full refund to your wallet below. [Poll, if
> active:] Help us understand your patience: if we're not back on our new
> servers within 24 hours, would you prefer we open refunds for everyone?
> [Agree] [Disagree]"

Admin edits the message text and can see exactly what was live at any past
moment via the version history — never guessing what users actually saw.

## Done when
- Toggling maintenance mode on blocks every write action platform-wide
  except refund/poll routes, while reads keep working — verified with an
  actual blocked-write test, not just visual inspection
- A member in an open (not-yet-full) group gets an immediate, fully
  automated refund the moment they request one, with refunds_enabled on
- A member in an active group is correctly routed to manual review with a
  clear explanation, never silently auto-refunded
- The poll accepts one vote per user, tallies correctly, and stays
  functional even while maintenance mode blocks everything else
- Every maintenance message edit and every manual refund decision is
  logged to `AuditLog` with the acting admin's ID
