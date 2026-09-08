# 2LLU — Batch 1: Clone → Strip → Foundation
### Product renamed from "Circle" to **2LLU** (users = "2llubers"). Give this whole file to Claude Code as one prompt. Nothing here depends on Batch 2/3/4.

> **Naming note:** database table prefix stays `circle_` internally — renaming
> every table now would mean re-doing work Claude Code may have already run.
> Only the user-facing brand is 2LLU. Say the word if you want a full
> `circle_` → `tullu_` table rename instead; it's a mechanical find-replace
> across migrations/models, not a redesign, so it's cheap to do later too.

---

## Step 1 — Clone & detach

```bash
git clone <naarasim-repo-url> circle
cd circle
git remote set-url origin <new-circle-repo-url>
composer install
cp .env.example .env
php artisan key:generate
```
Set `APP_NAME=Circle` in `.env`.

## Step 2 — Strip eSIM only

Delete:
- `app/Models/EsimOrder.php`, `EsimPlan.php`, `EsimCompatibleDevice.php`, `EsimCountryImage.php`, `EsimRegionImage.php`
- `app/Services/eSIM/*`
- Livewire components, routes, views matching `**esim**`

New migration to drop the tables (don't edit old migration history):
```php
Schema::dropIfExists('esim_orders');
Schema::dropIfExists('esim_plans');
Schema::dropIfExists('esim_compatible_devices');
Schema::dropIfExists('esim_country_images');
Schema::dropIfExists('esim_region_images');
```
Do not touch SMS, GiftCards, Numbers, Merchant/Partner — same provider plumbing, not eSIM itself.

Verify: `php artisan migrate:fresh --seed` boots clean, zero eSIM references.

## Step 3 — Brand & design system

Dark mode = brown/coffee. Light mode = milk/cream. Butter-yellow is the one
accent that carries across both, so the brand reads as one thing regardless
of mode.

```
espresso   #1B120B   dark bg
espresso-2 #271A11   dark surface / cards
coffee-line#4A3220   dark hairline borders
milk       #FBF7EE   light bg
milk-2     #F3EAD9   light surface / cards
milk-line  #E2D0AF   light hairline borders
butter     #E8B34C   primary accent — both modes
caramel    #B97A3E   secondary gradient stop
coffee     #7A4A25   light-mode secondary accent / links
```

Typography — deliberately not the generic Inter-everywhere fintech look:
- Display: **Fraunces** (warm serif, used sparingly for hero headlines and section titles)
- Body: **Sora**
- Numbers/currency/turn counters: **IBM Plex Mono**, tabular figures — this is what gives it fintech precision against the warm palette

Signature element — call it **"The Pour"**: a soft mesh gradient (butter → caramel → base) behind the landing hero, like milk being poured into coffee. Carry the same idea into the product itself as a **concentric-ring motif** — thin ring arcs on card borders and as the visual language for the round/cycle tracker in Batch 3, since the product literally is a rotation. Tie the metaphor to the mechanic, don't just decorate with it.

```js
// tailwind.config.js additions
colors: {
  espresso: { DEFAULT: '#1B120B', surface: '#271A11', border: '#4A3220' },
  milk:     { DEFAULT: '#FBF7EE', surface: '#F3EAD9', border: '#E2D0AF' },
  butter: '#E8B34C', caramel: '#B97A3E', coffee: '#7A4A25',
},
fontFamily: {
  display: ['Fraunces', 'serif'],
  body: ['Sora', 'sans-serif'],
  mono: ['"IBM Plex Mono"', 'monospace'],
},
backgroundImage: {
  'pour-dark':  'radial-gradient(at 20% 15%, #E8B34C33 0, transparent 45%), radial-gradient(at 60% 40%, #B97A3E40 0, transparent 55%), radial-gradient(at 85% 85%, #1B120B 0, transparent 70%)',
  'pour-light': 'radial-gradient(at 20% 15%, #E8B34C40 0, transparent 45%), radial-gradient(at 60% 40%, #7A4A2522 0, transparent 55%), radial-gradient(at 85% 85%, #FBF7EE 0, transparent 70%)',
},
```
Apply `dark:` variants using Tailwind's `darkMode: 'class'`. Landing hero uses `bg-pour-light dark:bg-pour-dark`. Cards get a 1px `border-milk-line dark:border-coffee-line` with a faint concentric-ring `::before` on hover, not on every card at once — restraint matters more than coverage.

## Step 4 — Database schema

```php
// circle_priority_rules (create before circle_plans — it's referenced)
Schema::create('circle_priority_rules', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->json('ordered_purposes');
    $table->enum('tie_breaker', ['join_order','random'])->default('join_order');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// circle_plans
Schema::create('circle_plans', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->enum('cycle_type', ['daily','weekly','biweekly','monthly']);
    $table->unsignedSmallInteger('cycle_duration');
    $table->unsignedTinyInteger('members_per_group')->default(13);
    $table->unsignedBigInteger('contribution_amount');
    $table->unsignedBigInteger('registration_fee');
    $table->decimal('platform_fee_percent', 5, 2)->default(0.5);
    $table->enum('kyc_level_required', ['basic','advanced']);
    $table->char('currency', 3)->default('NGN');
    $table->boolean('requires_bank_statement')->default(false);
    $table->decimal('min_income_multiplier', 3, 2)->default(0.50); // contribution must be <= income * this
    $table->json('countries_allowed');
    $table->text('tooltip_details')->nullable();
    $table->json('bullet_points')->nullable();
    $table->enum('turn_sort_strategy', ['fifo','priority_auto'])->default('fifo');
    $table->foreignUuid('priority_rule_id')->nullable()->constrained('circle_priority_rules')->nullOnDelete();
    $table->enum('status', ['active','draft'])->default('draft');
    $table->unsignedInteger('display_order')->default(0);
    $table->timestamps();
});

// circle_groups
Schema::create('circle_groups', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('plan_id')->constrained('circle_plans');
    $table->string('name');
    $table->char('country', 2);
    $table->string('state')->nullable();
    $table->enum('status', ['open','active','completed','suspended'])->default('open');
    $table->unsignedTinyInteger('member_count')->default(0);
    $table->unsignedTinyInteger('max_members');
    $table->unsignedTinyInteger('current_round')->default(0);
    $table->date('cycle_start_date')->nullable();
    $table->boolean('renew_requested')->default(false);
    $table->timestamps();
});

// circle_members
Schema::create('circle_members', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('group_id')->constrained('circle_groups');
    $table->foreignId('user_id')->constrained();
    $table->unsignedTinyInteger('turn_number')->nullable();
    $table->timestamp('joined_at');
    $table->enum('status', ['active','warning','suspended','completed'])->default('active');
    $table->unsignedTinyInteger('warning_count')->default(0);
    $table->timestamps();
});

// circle_fund_requests — member's own stated purpose, no approval gate
Schema::create('circle_fund_requests', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('member_id')->constrained('circle_members');
    $table->enum('purpose', ['business_startup','travel','emergency','loan_payoff','school_fees','other']);
    $table->text('description');
    $table->timestamps();
});

// circle_contributions
Schema::create('circle_contributions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained();
    $table->foreignUuid('group_id')->constrained('circle_groups');
    $table->unsignedTinyInteger('round_number');
    $table->unsignedBigInteger('amount');
    $table->string('paystack_reference')->unique()->nullable();
    $table->enum('status', ['pending','paid','missed'])->default('pending');
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
});

// circle_chat_messages — schema now, UI in Batch 3
Schema::create('circle_chat_messages', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('group_id')->constrained('circle_groups');
    $table->foreignId('user_id')->constrained();
    $table->text('message')->nullable();
    $table->string('image_url')->nullable();
    $table->timestamps();
});
```

Models: `CirclePriorityRule`, `CirclePlan`, `CircleGroup`, `CircleMember`, `CircleFundRequest`, `CircleContribution` — standard Eloquent, `HasUuids`, relationships as the FKs above imply (plan→groups, group→members, member→fundRequest). `CirclePriorityRule` gets one helper:
```php
public function rankOf(string $purpose): int
{
    $i = array_search($purpose, $this->ordered_purposes, true);
    return $i === false ? PHP_INT_MAX : $i;
}
```

## Step 5 — Automatic turn-sorting engine

This is the fully-automatic sorting you asked for: admin sets a priority
order once (Step 6), the engine applies it with zero manual steps per group.

```php
// app/Services/Circle/TurnSortingService.php
class TurnSortingService
{
    public function assignTurns(CircleGroup $group): void
    {
        $plan = $group->plan;
        $members = $group->members()->with('fundRequest')->get();

        $sorted = $plan->turn_sort_strategy === 'priority_auto'
            ? $this->byPriority($members, $plan->priorityRule ?? CirclePriorityRule::where('is_active', true)->first())
            : $members->sortBy('joined_at');

        foreach ($sorted->values() as $i => $member) {
            $member->update(['turn_number' => $i + 1]);
        }
    }

    private function byPriority(Collection $members, ?CirclePriorityRule $rule): Collection
    {
        if (!$rule) return $members->sortBy('joined_at');

        return $members->sort(function ($a, $b) use ($rule) {
            $ra = $rule->rankOf($a->fundRequest?->purpose ?? '');
            $rb = $rule->rankOf($b->fundRequest?->purpose ?? '');
            if ($ra !== $rb) return $ra <=> $rb;
            return $rule->tie_breaker === 'random' ? rand(-1, 1) : $a->joined_at <=> $b->joined_at;
        });
    }
}
```
Write unit tests: `priority_auto` output matches the rule's order exactly; `fifo` ignores purpose entirely.

## Step 5b — 24-plan structure + eligibility foundation

The `circle_plans` table above already supports this — 24 plans is just 24
rows, not a schema change beyond the 3 columns added in Step 4
(`currency`, `requires_bank_statement`, `min_income_multiplier`). This step
is the seeding structure and the supporting tables that Batch 2's AI
underwriting and debt engine will need.

**Plan tiers** — 8 per cycle type, using the NGN figures you gave me as the
default-country seed (other-currency tiers are a research task for later,
not guessed here):
- Weekly (8): ₦2k → ₦100k/week, 12-13 members, 12-13 weeks. Plans 1-2 = basic KYC only. Plans 3-8 = advanced KYC + bank statement.
- Biweekly (8): ₦20k → ₦500k/2 weeks, same KYC split.
- Monthly (8): ₦15k, 30k, 50k, 100k, 200k, 300k, 400k, 500k — ₦1,000,000 dropped per your correction (was a 9th value for 8 slots). Plans are recommendation targets from Batch 2's underwriting, not a rigid ladder a user must climb — someone can be shown any plan their credit history supports.

**Registration token**: `circle_plans.registration_fee` is a flat admin-set amount per plan — the "small token" you described, not a percentage. Kept separate from the percentage-based fees in Step 5c below, which apply to ongoing contributions and payouts, not to signup.

**New tables:**
```php
// income declaration — collected once at onboarding, used for eligibility math
Schema::table('users', function (Blueprint $table) {
    $table->enum('income_cycle', ['weekly','biweekly','monthly'])->nullable();
    $table->unsignedBigInteger('income_amount')->nullable();
    $table->timestamp('income_declared_at')->nullable();
});

// user_bank_statements — the AI-underwriting record, logic wired in Batch 2
Schema::create('user_bank_statements', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained();
    $table->foreignUuid('plan_id')->nullable()->constrained('circle_plans');
    $table->string('pdf_url');
    $table->unsignedInteger('file_size_bytes');
    $table->json('ai_analysis')->nullable(); // Claude API's structured result
    $table->enum('status', ['pending','approved','rejected'])->default('pending');
    $table->timestamps();
});

// circle_debts — the missed-contribution ledger, logic wired in Batch 2
Schema::create('circle_debts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('debtor_user_id')->constrained('users');
    $table->foreignId('creditor_user_id')->constrained('users'); // whose turn was short-funded
    $table->foreignUuid('group_id')->constrained('circle_groups');
    $table->foreignUuid('contribution_id')->nullable()->constrained('circle_contributions');
    $table->unsignedBigInteger('original_amount');
    $table->unsignedBigInteger('penalty_amount'); // 5% of original
    $table->unsignedBigInteger('total_amount');
    $table->enum('status', ['active','paid'])->default('active');
    $table->timestamps();
});

// fx_rates — daily-synced, logic wired in Batch 2
Schema::create('fx_rates', function (Blueprint $table) {
    $table->id();
    $table->char('from_currency', 3);
    $table->char('to_currency', 3);
    $table->decimal('rate', 18, 6);
    $table->timestamp('updated_at');
    $table->unique(['from_currency','to_currency']);
});
```

File-upload validation for `user_bank_statements`: **PDF only, max 2MB**
(not 10MB — Opay/PalmPay statements run 100-400KB, traditional banks
500KB-1.5MB, so 2MB covers everyone without inviting 50MB scans). Error
copy: *"Upload your last 3 months bank statement PDF. Max 2MB. Tip:
download '3-Month Statement' from the Opay/PalmPay app — usually under
500KB."*

## Step 5d — Dynamic Fee Control Panel

Four fee types, globally configurable by admin with enforced min/max bounds
— not hardcoded, not per-plan, so admin can run promos (drop a fee toward
its minimum) without touching code. The actual deduction math against
contributions and payouts is Batch 2's job — this step just needs the
settings to exist and be admin-editable.

```php
Schema::create('fee_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->decimal('value', 10, 4);
    $table->decimal('min_value', 10, 4);
    $table->decimal('max_value', 10, 4);
    $table->timestamps();
});
```

Seed:
| key | default | min | max |
|---|---|---|---|
| `contribution_fee_percent` | 0.5% | 0% | 2% |
| `guardian_pool_fee_percent` | 1.5% | 0.5% | 3% |
| `payout_processing_fee_flat` | ₦100 | ₦50 | ₦500 |
| `platform_security_fee_percent` | 0.2% | 0% | 1% |

`/admin/fee-settings` — editable table, save rejected if a value falls
outside its own `min_value`/`max_value`.

## Step 6 — Admin screens (themed with Step 3's tokens)

- `/admin/circle-plans` — CRUD for all `circle_plans` fields, including the `turn_sort_strategy` toggle and a picker for which `CirclePriorityRule` to use.
- `/admin/circle-priority-rules` — drag-and-drop reorder of the 6 purpose categories, tie-breaker dropdown, name, save. **This is the only manual step in the whole system** — configured once, in advance. Nothing manual happens per-member or per-group after.

Seed one default rule (`emergency, loan_payoff, school_fees, business_startup, travel, other`) and the 24 plans from Step 5b (NGN tiers, `requires_bank_statement = true` on tiers 3-8 in each category).

## Step 7 — Admin payout constants (plumbing only, logic comes in Batch 2)

`PayoutRequest.payee_type` already supports `user | merchant`. Add:
```php
public const PAYEE_ADMIN = 'admin';
public const BUCKET_CIRCLE_PLATFORM_FEES = 'circle_platform_fees';
```
Reuse the existing user-facing payout-account Livewire component, scoped for admin, at `/admin/payout-accounts` — same verification flow, no new component.

---

## Done when
- Repo boots with zero eSIM references and the new brand tokens applied
- `/admin/circle-plans` and `/admin/circle-priority-rules` work end to end
- 24 plans seeded correctly, KYC/bank-statement flags correct per tier
- `TurnSortingService` tests pass for both strategies
- `/admin/payout-accounts` loads for an admin, same flow a user gets
- `income_cycle`/`income_amount` columns exist on `users`; `user_bank_statements`, `circle_debts`, `fx_rates` tables exist with no business logic yet — that's Batch 2
- `fee_settings` seeded with the 4 fee types and correct min/max bounds; `/admin/fee-settings` enforces them on save
- 24 plans reflect the corrected 8-tier monthly list (no ₦1,000,000 row)
