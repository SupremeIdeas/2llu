# 2LLU — Batch 3: Matching, Group Experience, Guardians (redesigned), Admin
### Depends on Batch 1 + 2. Give this whole file to Claude Code as one prompt.

---

## Step 1 — Geo schema (structure now, data seeded later — as agreed)

Matching needs to go finer than country/state for Nigeria (LGA), but other
countries don't use "LGA" as a concept, so the schema stays generically
named with Nigeria as the deepest-seeded example first.

```php
Schema::create('geo_countries', function (Blueprint $table) {
    $table->id(); $table->char('code', 2)->unique(); $table->string('name');
});
Schema::create('geo_states', function (Blueprint $table) {
    $table->id(); $table->foreignId('country_id')->constrained('geo_countries'); $table->string('name');
});
Schema::create('geo_localities', function (Blueprint $table) {
    // LGA in Nigeria; the equivalent administrative subdivision elsewhere
    $table->id(); $table->foreignId('state_id')->constrained('geo_states'); $table->string('name');
});
Schema::create('geo_towns', function (Blueprint $table) {
    $table->id(); $table->foreignId('locality_id')->constrained('geo_localities'); $table->string('name');
});
Schema::create('geo_streets', function (Blueprint $table) {
    $table->id(); $table->foreignId('town_id')->constrained('geo_towns'); $table->string('name');
});
```
No seed data in this batch — country → state → locality → town → street for
every Paystack/Flutterwave/Stripe-supported country is its own dedicated
seeding project once the build is done, exactly as you planned it. Building
it into this batch would just produce guessed placeholder data that has to
be thrown away later.

`circle_members` gets one addition so matching can use it once geo data
exists:
```php
Schema::table('circle_members', function (Blueprint $table) {
    $table->foreignId('locality_id')->nullable()->constrained('geo_localities');
});
```

## Step 2 — Matching: country → state → locality

```php
// MatchingService::joinOrCreateGroup — refined ordering
$group = CircleGroup::where('plan_id', $plan->id)
    ->where('country', $user->country)
    ->where('status', 'open')
    ->whereColumn('member_count', '<', 'max_members')
    ->orderByRaw('state = ? DESC, locality_id = ? DESC, created_at ASC', [$user->state, $user->locality_id])
    ->lockForUpdate()->first() ?? CircleGroup::create([...]);
```
Never cross countries — that rule from Batch 1/2 doesn't change. State and
locality are just tighter preference ordering on top of it, and only bite
once geo data exists; until then it degrades gracefully to country/state
matching from earlier batches.

## Step 3 — Group experience UI

- **Member directory** — name, photo, bio, state (locality once seeded) —
  phone number reveal-on-click, not listed openly, to limit scraping.
- **Round tracker** — current collector + their `circle_fund_requests`
  purpose/description as a small campaign card, using the concentric-ring
  motif from Batch 1's design system for the cycle visualization.
- **Group chat** — polling or existing broadcast infra on `circle_chat_messages`, image upload through whatever storage disk you already use elsewhere.
- **Rename group** — members-only, plan name stays as subtitle.

## Step 4 — Guardians (redesigned, per what we agreed)

No GPS, no home visits, no address-pulling from bank statements. Guardians
are verified local moderators who do in-app mediation and get paid for
successfully getting a defaulting member to log in and clear their debt —
not for locating anyone physically.

```php
Schema::create('guardians', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('locality_id')->nullable()->constrained('geo_localities');
    $table->string('security_id_badge_url'); // verification upload, KYC-gated
    $table->foreignUuid('team_id')->nullable()->constrained('guardian_teams');
    $table->enum('status', ['pending','verified','suspended'])->default('pending');
    $table->timestamps();
});

// Referral team: a Guardian can invite up to 3 teammates. A referred
// Guardian becomes team-locked and cannot refer further — caps the
// structure at exactly one level, no pyramid.
Schema::create('guardian_teams', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('lead_guardian_id')->constrained('users');
    $table->unsignedTinyInteger('member_count')->default(1);
    $table->timestamps();
});

Schema::create('guardian_cases', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('debt_id')->constrained('circle_debts');
    $table->foreignUuid('guardian_id')->nullable()->constrained('guardians');
    $table->enum('status', ['unassigned','under_investigation','resolved','escalated'])->default('unassigned');
    $table->timestamp('acknowledged_at')->nullable(); // must ack within SLA or reassign
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();
});

Schema::create('guardian_earnings', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('guardian_id')->constrained('users');
    $table->foreignUuid('case_id')->nullable()->constrained('guardian_cases');
    $table->enum('type', ['registration_share','contribution_share','recovery_share']);
    $table->unsignedBigInteger('amount');
    $table->timestamps();
});
```

**Referral team logic:**
```php
public function joinAsTeammate(User $referred, Guardian $referrer): void
{
    abort_if($referrer->team()->count() >= 3, 422, 'This Guardian\'s team is already full.');
    abort_if($referred->guardianRecord?->team_id, 422, 'You are already on a team.');
    // referred guardian is now team-locked — cannot start their own referral chain
}
```

**Case assignment — load-balanced, not manual:**
```php
public function assignCase(CircleDebt $debt): void
{
    $case = GuardianCase::create(['debt_id' => $debt->id, 'status' => 'unassigned']);

    // Prefer guardians in the debtor's locality with the fewest active cases —
    // this is what naturally gives every guardian a floor of ~3 cases before
    // new cases spread to others, without hardcoding a magic number.
    $guardian = Guardian::where('locality_id', $debt->debtor->locality_id)
        ->where('status', 'verified')
        ->withCount(['cases' => fn ($q) => $q->whereIn('status', ['unassigned','under_investigation'])])
        ->orderBy('cases_count')->first();

    $case->update(['guardian_id' => $guardian->id, 'status' => 'unassigned']);
}
```

**SLA reassignment** (scheduled job): a case not marked
`under_investigation` within 48h of assignment gets reassigned to the next
least-loaded guardian in the locality, and repeats.

**Earnings engine v2 — base pay from the pool, bonus pay from resolved escalations.** This replaces the flat 40%-of-debt split from the earlier draft with the model you actually locked in: predictable base pay funded by the Guardian Pool (Batch 2's `guardian_pool_ledger`), plus a separate bonus pool that only rewards guardians who actually resolved something.

```php
Schema::create('guardian_earnings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('guardian_id')->constrained('users');
    $table->string('month'); // '2026-09'
    $table->unsignedBigInteger('base_earned')->default(0);
    $table->unsignedBigInteger('cap')->default(0);
    $table->unsignedBigInteger('bonus_earned')->default(0);
    $table->enum('status', ['active','capped'])->default('active');
    $table->unique(['guardian_id','month']);
});

Schema::create('guardian_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique(); // monthly_cap, escalation_bonus_percent
    $table->decimal('value', 12, 2);
});
// seed: monthly_cap = 50000 (₦10,000–₦500,000 admin range), escalation_bonus_percent = 20
```

**Base pay** — guardians are paid from the Guardian Pool for active monitoring (no open, unacknowledged cases), up to the monthly cap:
```php
public function payBase(Guardian $g): void
{
    $month = now()->format('Y-m');
    $record = GuardianEarning::firstOrCreate(['guardian_id' => $g->user_id, 'month' => $month],
        ['cap' => GuardianSetting::value('monthly_cap')]);

    if ($record->status === 'capped') return; // silent stop — no notification spam

    $available = GuardianPoolLedger::whereMonth('created_at', now()->month)->sum('amount');
    $share = $this->poolShareFor($g, $available); // even split across active guardians in the locality, your call on exact formula

    if ($record->base_earned + $share >= $record->cap) {
        $record->update(['base_earned' => $record->cap, 'status' => 'capped']);
    } else {
        $record->increment('base_earned', $share);
    }
}
```
Pool money that goes unpaid because guardians are capped stays in
`guardian_pool_ledger` and rolls into next month's available pool — it does
not silently vanish, and it's separate from the escalation bonus pool below
rather than merged into it, so the two funding sources stay auditable.

**Escalation bonus pool** — funded by 20% of *last* month's total registration-fee tokens, split only among guardians who resolved an `escalated` case that month:
```php
// app/Jobs/CalculateEscalationBonusPoolJob.php — scheduled 1st of month
public function handle(): void
{
    $lastMonthRegFees = CreditLedger::where('type', 'registration_token')
        ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
        ->sum('amount');

    EscalationBonusPool::create([
        'month' => now()->format('Y-m'),
        'amount' => (int) round($lastMonthRegFees * GuardianSetting::value('escalation_bonus_percent') / 100),
    ]);
}

// app/Jobs/PayEscalationBonusJob.php — scheduled 28th of month
public function handle(): void
{
    $pool = EscalationBonusPool::where('month', now()->format('Y-m'))->first();
    $resolved = GuardianCase::where('status', 'resolved')
        ->where('resolved_at', '>=', now()->startOfMonth())
        ->where('resolved_at', '<=', now())
        ->get()
        ->groupBy('guardian_id');

    $totalResolved = $resolved->flatten()->count();
    if (!$pool || !$totalResolved) return;
    $perCase = intdiv($pool->amount, $totalResolved);

    foreach ($resolved as $guardianId => $cases) {
        $record = GuardianEarning::firstOrCreate(['guardian_id' => $guardianId, 'month' => now()->format('Y-m')]);
        $record->increment('bonus_earned', $perCase * $cases->count());
    }
}
```

**Open question, flagging rather than assuming:** the referral-team structure from Step 4 (lead + up to 3 teammates) was built around splitting a percentage evenly across the team. This bonus model pays whichever guardian's `guardian_id` is on the resolved case — it doesn't automatically split across their team. Do you want team-resolved escalations to still split the bonus evenly across the team of up to 4, or should it go entirely to whoever's assigned to the case? Easy either way, just needs to be explicit before this ships.

**Admin dashboard must show:** total Guardian Pool this month, guardians at cap (X/Y), next month's projected bonus pool (20% of this month's registration fees so far), and escalations resolved this month with the per-case amount once the pool locks on the 1st.

**Firm boundary, unchanged from what we agreed:** Guardians cannot seize
property. No location tracking, no GPS logging, no address pulled from
bank statements. Persistent non-payment after mediation escalates to
actual police/legal process with the in-app record as evidence — Guardians
facilitate that handoff, they don't enforce anything themselves.

## Step 5 — Renewal flow (this was missing — adding it back)

Two stages, matching exactly what you described: members vote on renewing,
anyone who leaves gets replaced through normal matching, and the *full*
resulting roster must unanimously confirm before the new cycle starts —
not just the original members.

```php
Schema::create('circle_renewal_votes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('group_id')->constrained('circle_groups');
    $table->foreignId('user_id')->constrained();
    $table->boolean('vote');
    $table->timestamps();
    $table->unique(['group_id','user_id']);
});
```

**Stage 1 — initial renewal poll**, opened when `CircleCycleCompleted`
fires (all 12/13 rounds done):
```php
// app/Jobs/TallyInitialRenewalVoteJob.php — scheduled 7 days after cycle completion
public function handle(CircleGroup $group): void
{
    $votes = CircleRenewalVote::where('group_id', $group->id)->get();
    $staying = $votes->where('vote', true)->pluck('user_id');

    if ($staying->isEmpty()) {
        CircleGroupDisbanded::dispatch($group);
        return;
    }

    $newGroup = CircleGroup::create([
        'plan_id' => $group->plan_id, 'country' => $group->country, 'state' => $group->state,
        'name' => $group->name.' (Cycle '.($group->cycle_number ?? 1) + 1.')',
        'status' => 'open', 'max_members' => $group->max_members,
    ]);

    foreach ($group->members->whereIn('user_id', $staying) as $m) {
        CircleMember::create(['group_id' => $newGroup->id, 'user_id' => $m->user_id, 'joined_at' => now()]);
    }
    $newGroup->update(['member_count' => $newGroup->members()->count()]);

    // Vacated slots (members who voted no, or didn't vote) open to normal
    // matching — same MatchingService from Batch 2, same country/state/locality rules.
    if ($newGroup->member_count < $newGroup->max_members) {
        $newGroup->update(['status' => 'open']); // sits in the matching pool until full
    } else {
        $this->openFinalConfirmation($newGroup); // already full, skip straight to stage 2
    }
}
```

**Stage 2 — final unanimous confirmation**, triggered once the backfilled
group hits `max_members` (whether that's immediately, or after new
registrants fill it via matching over the following days/weeks):
```php
// Triggered from MatchingService::joinOrCreateGroup when member_count hits max on a renewal group
public function openFinalConfirmation(CircleGroup $group): void
{
    CircleRenewalVote::where('group_id', $group->id)->delete(); // fresh vote, full new roster
    CircleFinalConfirmationOpened::dispatch($group); // notify every member: original stayers + new joiners
}

// app/Jobs/TallyFinalConfirmationJob.php — scheduled 72h after openFinalConfirmation
public function handle(CircleGroup $group): void
{
    $yes = CircleRenewalVote::where('group_id', $group->id)->where('vote', true)->count();

    if ($yes >= $group->max_members) { // every single member, no exceptions — it's a money product
        app(TurnSortingService::class)->assignTurns($group);
        $group->update(['status' => 'active', 'current_round' => 1, 'cycle_start_date' => now()]);
    } else {
        // Anyone who didn't confirm is dropped and their slot reopens to
        // matching — the group doesn't activate until every remaining
        // member has explicitly said yes.
        CircleRenewalVote::where('group_id', $group->id)->where('vote', false)->orWhereNull('vote')
            ->each(fn ($v) => CircleMember::where('group_id', $group->id)->where('user_id', $v->user_id)->delete());
        $group->update(['status' => 'open']); // back to matching for the reopened slots
    }
}
```

This can loop — a group can sit in "backfilling + reconfirming" for a
while if members keep dropping out at the final step. That's intentional:
unanimous consent before real money starts moving matters more than
speed here.

## Step 6 — Admin dashboard

`/admin/2llu` — 24 plans CRUD (already in Batch 1), all users + KYC status,
all groups + round progress, bank-statement review queue, payout approvals,
Guardian management (verify badges, view teams, adjust earning caps),
platform earnings (`circle_platform_fees` bucket from Batch 1), CSV export
for compliance/audit.

## Step 7 — Compliance copy

> "2LLU is a technology platform that facilitates mutual group
> contributions between verified members. 2LLU does not lend money and
> does not charge interest. Guardians are community moderators who assist
> with in-app mediation of overdue contributions — they have no authority
> to seize property, track your location, or visit your home. Persistent
> non-payment may be escalated to law enforcement using in-app records as
> evidence."

Get this reviewed by an actual lawyer in each jurisdiction you launch in
before it ships — flagging this again because you're now holding pooled
funds *and* running a debt-recovery incentive structure, which is a step
up in regulatory exposure from where Batch 1 started.

## Done when
- Matching prefers locality once geo data exists, degrades gracefully to
  country/state until then
- A Guardian can build a team of up to 3 referred teammates, referred
  guardians can't refer further
- Case assignment naturally load-balances toward a floor of active cases
  per guardian; unacknowledged cases reassign on schedule
- Base pay from the Guardian Pool stops cleanly at the monthly cap; the
  escalation bonus pool calculates from last month's registration fees and
  splits correctly across everyone who resolved a case that month
- Renewal correctly loops: initial poll → backfill via matching → full
  roster must unanimously confirm before turns are assigned; a group can
  sit in backfill/reconfirm for multiple cycles without breaking
- Nothing in this batch touches GPS, home addresses, or physical visits
