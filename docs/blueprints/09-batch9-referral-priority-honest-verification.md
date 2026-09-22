# 2LLU — Batch 9: Referral-Weighted Priority + Honest Fraud Verification
### Depends on Batch 1 (TurnSortingService), Batch 2 (UnderwritingService). Give this whole file to Claude Code as one prompt.

---

## Part A — Referral-weighted priority (visible factors, hidden formula)

**The rule that's non-negotiable, per our earlier discussion**: the exact
weighting is proprietary and never published — but the *existence* of
purpose, urgency, and referrals as factors goes in the T&C/FAQ. Users know
the rules exist; they don't get the formula. That's the version that
builds trust instead of eventually breaking it.

### Turn 1 is always FIFO, unconditionally — no exceptions

```php
// app/Services/Circle/TurnSortingService.php — updated
public function assignTurns(CircleGroup $group): void
{
    $plan = $group->plan;
    $members = $group->members()->with('fundRequest.user')->get();

    if ($plan->turn_sort_strategy !== 'priority_auto') {
        $sorted = $members->sortBy('joined_at');
    } else {
        $earliest = $members->sortBy('joined_at')->first(); // always turn 1
        $rest = $members->reject(fn ($m) => $m->id === $earliest->id);
        $rule = $plan->priorityRule ?? CirclePriorityRule::where('is_active', true)->first();
        $sorted = collect([$earliest])->merge(
            $rest->sortByDesc(fn ($m) => $this->score($m, $rule))->values()
        );
    }

    foreach ($sorted->values() as $i => $member) {
        $member->update(['turn_number' => $i + 1]);
    }
}

private function score(CircleMember $member, ?CirclePriorityRule $rule): float
{
    if (!$rule) return 0;
    $fund = $member->fundRequest;
    $categoryScore = $rule->ordered_purposes
        ? (count($rule->ordered_purposes) - $rule->rankOf($fund?->purpose ?? '')) * 10
        : 0;
    $urgencyScore = ($rule->urgency_weights ?? [])[$fund?->urgency_level ?? 'medium'] ?? 0;
    $referralScore = min($member->user->verifiedReferralCount(), $rule->referral_cap)
        * $rule->referral_weight_per_referral;
    return $categoryScore + $urgencyScore + $referralScore;
}
```

### Schema additions

```php
Schema::table('circle_priority_rules', function (Blueprint $table) {
    $table->json('urgency_weights')->nullable(); // {"critical":40,"high":25,"medium":10,"low":0}
    $table->decimal('referral_weight_per_referral', 8, 2)->default(2);
    $table->unsignedTinyInteger('referral_cap')->default(5); // diminishing returns past this
});

Schema::table('circle_fund_requests', function (Blueprint $table) {
    $table->enum('urgency_level', ['critical','high','medium','low'])->default('medium');
    $table->string('supporting_document_url')->nullable(); // optional, for critical/high tiers
});

Schema::create('referrals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('referrer_id')->constrained('users');
    $table->foreignId('referred_user_id')->constrained('users')->unique();
    $table->timestamps();
});
```

```php
// User.php
public function verifiedReferralCount(): int
{
    return Referral::where('referrer_id', $this->id)
        ->whereHas('referredUser', fn ($q) => $q->where('kyc_status', 'verified'))
        ->count();
}
```
Only **KYC-verified** referred users count — this is the fraud guard you
asked for, blocking the obvious abuse of mass-creating fake accounts to
farm priority.

### Onboarding change: structured input replaces free-text scoring

This is the refinement from last time — scoring "how thoughtful the
description reads" would silently reward good writers over people in real
need. Replace with:
- **Purpose category** (existing dropdown: emergency, loan_payoff, etc.)
- **Urgency level** (critical/high/medium/low) — the user's own honest
  self-assessment, not something Claude scores for eloquence
- **Optional supporting document** for critical/high — hospital admission
  note, invoice, eviction notice. Verifiable, not a writing contest.

### Disclosure copy (T&C / FAQ — not hidden, not detailed)

> "Turn order for the first collector in each circle is always first-come,
> first-served. For remaining turns, position may be influenced by your
> stated need and the number of verified members you've referred to 2LLU.
> The exact weighting isn't published, to keep the system resistant to
> gaming, but the factors themselves are never secret."

## Part B — Honest fraud verification, replacing the covert third-party check

### Forensic pre-check — legitimate, keep this part

```php
// app/Services/2LLU/StatementForensicsService.php
class StatementForensicsService
{
    public function inspect(string $pdfPath): array
    {
        $meta = $this->extractMetadata($pdfPath); // via a proper PDF library — pick one during Batch 9 implementation, don't guess the API here
        $flags = [];

        if (!empty($meta['ModDate']) && !empty($meta['CreationDate']) && $meta['ModDate'] !== $meta['CreationDate']) {
            $flags[] = 'modified_after_creation';
        }
        if (!in_array($meta['Producer'] ?? null, config('llu.trusted_bank_pdf_producers', []))) {
            $flags[] = 'unrecognized_producer';
        }
        // Font-consistency / text-layer re-render checks depend on which PDF
        // parsing library gets chosen — flag as a follow-up once that's picked,
        // not something to fake an implementation of here.
        return $flags;
    }
}
```

### What changes: no covert third-party contact, ever

```php
Schema::table('user_bank_statements', function (Blueprint $table) {
    $table->json('forensic_flags')->nullable();
});
Schema::table('user_bank_statements', function (Blueprint $table) {
    $table->enum('status', ['pending','approved','rejected','needs_supplementary_docs'])
        ->default('pending')->change();
});
Schema::create('supplementary_documents', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('bank_statement_id')->constrained('user_bank_statements');
    $table->string('document_url');
    $table->string('description')->nullable(); // user's own label
    $table->timestamps();
});
```

```php
// UnderwritingService::analyze() — updated
public function analyze(UserBankStatement $statement, StatementForensicsService $forensics): array
{
    $flags = $forensics->inspect(Storage::path($statement->pdf_url));
    $result = $this->callClaude($statement); // existing logic from Batch 2, unchanged

    $suspicious = !empty($flags) || str_contains(strtolower($result['reason'] ?? ''), 'unusual');

    $statement->update([
        'ai_analysis' => $result,
        'forensic_flags' => $flags,
        'status' => $suspicious ? 'needs_supplementary_docs' : ($result['eligible'] ? 'approved' : 'rejected'),
    ]);
    return $result;
}
```

**User-facing message, honest, no pretense:**
> "We need additional documentation to verify this income before approving
> this plan — an employer letter, an invoice, or a brief explanation of the
> transactions in question all work. This keeps 2LLU fair for everyone
> contributing real money."

No message goes to whoever sent them a transfer. No claim about "checking
legitimacy" that hides the actual ask. The user supplies what they choose
to supply, or picks a plan without the requirement, exactly as you said —
just without the deception layer, because that specific piece would have
processed a non-consenting third party's financial data under false
pretenses, which is a real legal exposure in every one of your target
countries, not a technicality.

## Done when
- Turn 1 is provably FIFO regardless of any score, verified with a test
  where the highest-scored member isn't the earliest joiner
- Referral count only credits KYC-verified referred users
- Urgency is a self-reported structured field, never derived from how
  well-written a description is
- A flagged statement routes to an honest supplementary-document request,
  never to contacting a third party
- T&C/FAQ discloses the existence of these factors without revealing the
  weighting formula
