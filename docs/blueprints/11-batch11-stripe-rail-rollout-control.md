# 2LLU — Batch 11: Master Toggle + Country-by-Country Legal Gating
### The safety switch that lets admin turn this on per-country only after lawyers clear it. Give this whole file to Claude Code as one prompt.

---

## Why a single on/off switch isn't enough

Legal clearance happens country by country, not all at once — the UK
lawyer finishing first shouldn't mean US users can register. This needs
two layers: a master kill switch, and a per-country status that can't
reach "live" without a documented compliance checklist being complete.

```php
Schema::create('stripe_rail_settings', function (Blueprint $table) {
    $table->id();
    $table->boolean('master_enabled')->default(false); // global kill switch
    $table->timestamps();
});

Schema::create('stripe_rail_country_status', function (Blueprint $table) {
    $table->id();
    $table->char('country', 2)->unique();
    $table->enum('status', ['not_started', 'legal_review', 'approved', 'live', 'suspended'])
        ->default('not_started');
    $table->json('compliance_checklist'); // see below — structured, not a free-text note
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->timestamp('approved_at')->nullable();
    $table->string('lawyer_signoff_doc_url')->nullable();
    $table->timestamps();
});
```

**Checklist structure — a country can't be set to `live` unless every item
is checked, enforced in code, not just convention:**
```php
$defaultChecklist = [
    'money_transmitter_or_licensing_review' => false,   // or FCA/PSD2 equivalent
    'lawyer_signoff_document_uploaded' => false,
    'tax_disclosure_reviewed' => false,                  // Batch 10 addendum's 1099-K copy
    'dunning_copy_legal_reviewed' => false,               // FDCPA-safe language confirmed
    'consumer_disclosure_localized' => false,             // FDIC/FSCS-non-coverage disclaimer etc.
    'chargeback_liability_configuration_decided' => false,
];
```

```php
// app/Services/2LLU/StripeRailGate.php
class StripeRailGate
{
    public function isLive(string $country): bool
    {
        if (!StripeRailSetting::first()?->master_enabled) return false;
        $status = StripeRailCountryStatus::where('country', $country)->first();
        return $status?->status === 'live';
    }
}
```
Wire this into `MatchingService::joinOrCreateGroup` and registration
eligibility (Batch 10, Step 1) as a hard gate — a country not marked `live`
shows "coming soon," never a broken or half-working registration flow.

## Admin panel: `/admin/stripe-rail/rollout`

- Master toggle at the top — turning this off instantly stops new
  registrations across every country on this rail, without touching
  existing active circles (those keep running; this only gates new joins).
- Per-country table: current status, the checklist with checkboxes,
  upload field for the lawyer signoff document, and a "Mark Live" button
  that's disabled (not just discouraged — actually disabled) until every
  checklist item is true and a signoff document exists.
- Only the `stripe_compliance` role (Batch 6/10's RBAC) can check off
  checklist items or upload the signoff doc — a general admin can view the
  status but can't advance it, so nobody accidentally flips a country live
  from the wrong login.
- Status history log — every status change recorded with who changed it
  and when, feeding `AuditLog` same as every other high-risk action in
  this build.
- `suspended` status exists deliberately — if something changes
  post-launch (a regulatory shift, a dispute pattern), admin can pull a
  country back without deleting its history or configuration.

## Adding new countries as clearance expands

`stripe_rail_country_status` rows aren't limited to a hardcoded list — new
rows get created (status `not_started`) as you decide to pursue a new
country from Batch 5's 44-country list, and the whole rollout workflow
applies uniformly to each one. This is what makes rolling out country #12
exactly as easy as country #2 was — same checklist, same gate, no
special-casing.

## Done when
- With `master_enabled = false`, no Stripe-rail registration is possible
  anywhere, verified with an actual attempted-and-blocked test
- A country stuck at `legal_review` cannot be set `live` while any
  checklist item is false — verified as an enforced constraint, not just a
  UI suggestion
- Only `stripe_compliance`-role admins can advance a country's status
- Every status change is logged with actor and timestamp
