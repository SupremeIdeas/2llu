# NaaraSim Wizard — Design & Research (for later build)

> **Status: DESIGN for later build. Admin-toggleable.** A guided, chat-style
> assistant that helps a user secure an **eSIM**, a **virtual number**, or an
> **OTP** end-to-end — right inside a floating widget — with almost no thinking
> required. **Logic-first, Claude-light** (see §8). Nothing here is built yet.

## 0. Principles

1. **The wizard is a deterministic state machine**, not an LLM freestyle. Each
   step has fixed options + validation. Claude is used **only** for (a) parsing a
   user's free-text answer into one of the fixed options and (b) short Q&A — both
   cached and optional, with a button fallback so it works even with Claude off.
2. **Never expose our suppliers.** Users see **model nicknames** (§2), never
   "5sim", "eSIM Go", "Twilio", etc. — same way Claude exposes "Sonnet/Opus", not
   its infra.
3. **Reuse the real engines.** Ordering goes through the existing
   `SmsNumberRouter` / `ProviderRouter`; pricing through `PricingEngine`
   (MarginGuard floor); money through `WalletService`; device check through
   `DeviceCompat`; support handoff to **NaaraCare** (`/support`). The wizard is a
   *guided front-end* to code we already have.
4. **A model only appears if its API key is configured** (via `ProviderKeys`).

---

## 1. The two things the wizard sells

- **eSIM data plans** (data, needs an eSIM-capable device)
- **Virtual / verification numbers**: OTP (disposable), short rental, long rental,
  or permanent (voice + SMS)

Some models also do **data + number** where the provider truly bundles it — the
wizard surfaces that only when the model's capability flag says so (no over-promise).

---

## 2. The "Model" abstraction (nickname layer)

A registry maps each internal provider → a **public model** with a capability
profile. The wizard, dashboard, and pricing show the **model**; internal routing
uses the real provider. Admin edits the mapping; unconfigured models are hidden.

### Suggested model roster (nicknames + what the user sees)

| Model (public) | Powers (internal — never shown) | What it's for |
| --- | --- | --- |
| **Naara Orbit** | eSIM Go | Global eSIM **data** — fast, primary coverage |
| **Naara Roam** | Airalo | Global eSIM **data** — widest country coverage |
| **Naara Skylink** | Quibity/eSIM.sm | eSIM **data** — extended/alternate coverage |
| **Naara Verify** | 5sim | **OTP** & verification, 180+ countries; short rentals |
| **Naara Flash** | SMS-Activate | Fast **disposable OTP** — backup global coverage |
| **Naara Liberty** | Getatext | **US** numbers — OTP + long-term rental |
| **Naara Line** | Twilio | **Permanent** numbers + **voice**; search a number that matches yours |
| **Naara Signal** | Telnyx | **Permanent** numbers + voice — alternate coverage; number search |

> Names are illustrative — admin can rename any model. Each carries a one-line
> descriptor + an icon (via the existing `<x-service-icon>` system).

### Capability flags per model (drives the whole wizard)

`esim_data, otp, rental_short, rental_long, permanent, voice, number_search
(pattern), data_with_number, renewable, countries[]`, plus live price via
`PricingEngine`. These flags decide which options the wizard shows and which model
it recommends — **no guessing, pure data**.

---

## 3. The wizard workflow (state machine)

Each `→` is a state with fixed options; free-text is parsed to an option (§8).

1. **Orientation (first-registration auto-open).** "Here's what NaaraSim is…"
   (eSIM data + numbers in one wallet). One short card, dismissible.
2. **Purpose.** "What do you want to do?" → options built from the **capability
   flags of configured models**: *Get an OTP code · Rent a number · Buy a
   permanent number · Get eSIM data · Data + number*.
3. **Recommend a model.** The wizard suggests the best model for that purpose and
   explains why; user confirms or picks another (only models that support the
   purpose are shown).
4. **Country.** Type a country **or** open the in-chat country selector (search).
   Only countries the chosen model supports are selectable.
5. **Number type detail** (numbers only). "What's the number for?" → *OTP /
   one-time · Rental · Permanent*, then *temporary or permanent* for more tailored
   next steps. **If the chosen model doesn't support the pick**, the wizard says
   "Sorry — the model that supports this is **X**" and lists the models that do,
   with what each does, and lets the user switch.
6. **Number matching** (only for `number_search` models — **Naara Line / Signal**;
   see §5). "Type a number you'd love (or your local number) — we'll find the
   closest match." The wizard searches for numbers where the **last 4–7** or
   **first 4–7 digits** rhyme with theirs (country code will differ). It explains
   the exact number may differ; the user may list 2–3 numbers to widen the search.
   **If no match:** apologise and offer another country code or model — with a
   caution that a different model/number might not suit their use-case, so they
   choose deliberately ("we don't want to break what you need it for").
7. **Choose from results.** Show the available numbers/plans (price via
   PricingEngine, never cost) → **Get this number / plan**.
8. **Balance check.** Confirm the user has enough **wallet balance** (topped up via
   a gateway; NaaraCredits may be applied within the model's margin cap). If short:
   *"You don't have enough balance. Minimise this wizard, top up your wallet, then
   tap the floating widget — your progress is saved. Hurry: someone else may grab
   this number."* The **session is persisted** (§7) so they resume exactly where
   they left off (re-validating the number is still available).
9. **Purchase.** Order through the real router; on success the item lands in the
   **dashboard** (§9) and is shown **copyable** in the wizard.
10. **OTP flow.** For OTP numbers, the code can be **pushed from the dashboard to
    the wizard** for one-tap copy (same code reflects in both). The user can paste
    a number back into the wizard to request another OTP where the model allows.

### eSIM path
Same shape, but step 5 becomes a **device-compatibility check** (§4) before
purchase, then it shows the data plans for the country/model and, on success, the
eSIM (QR/LPA) appears in the dashboard + is copyable in the wizard.

---

## 4. eSIM device-compatibility (accurate workflow)

Reuse `DeviceCompat` (already returns supported / not / unknown). In the wizard:

1. "Is your phone eSIM-ready?" → user types the model **or** picks from a shown
   list of common eSIM-capable devices.
2. `DeviceCompat::check()` → **supported** (auto-confirm), **not supported**
   (block the purchase, explain), or **unknown** → guide them to **dial `*#06#`**:
   a **32-digit EID** = eSIM hardware present; no EID = no eSIM. (Facts: iPhone
   XS/XR 2018+, Samsung S20+, Pixel 3+, many 2020+ Androids; **caveats**: mainland-
   China iPhones have no eSIM, carrier-locked phones only take the locking carrier's
   eSIM.) Keep the device list maintained in `DeviceCompat`.
3. Only after a pass (or explicit user confirmation for "unknown") does the wizard
   show plans. **This mirrors the existing checkout gate — no new risk.**

---

## 5. Number matching — what's actually possible (source of truth)

Pattern matching is a **permanent-number** capability, **not** disposable OTP:

- **Naara Line (Twilio)** — `AvailablePhoneNumbers` supports a match pattern (2–16
  chars, meta-chars `* % + $`; `+`=match start, `$`=match end) and `NearNumber`.
  So "ends with 1234", "starts with 080-like", etc. are real.
- **Naara Signal (Telnyx)** — `starts_with` / `ends_with` / `contains` (contains =
  last 4 digits). No wildcards.
- **Naara Verify / Flash / Liberty (5sim / SMS-Activate / Getatext)** — OTP/rental
  models **assign** a number for the service+country; **no custom pattern**. So the
  wizard offers the "match your number" step **only when `number_search` is true**,
  and for OTP models it just shows the available number(s). Documenting this keeps
  us honest and avoids promising a match a provider can't deliver.

---

## 6. Wizard pricing (transparent)

- **Free for the first 3 completed wizard sessions.** After that, **$0.45** is added
  to the bill per wizard-completed purchase, shown up front: *"A small $0.45
  (not even a dollar) supports the NaaraSim Wizard doing the heavy lifting. Prefer
  to skip it? You can always use the dashboard directly for free."*
- The fee is a line item (never hidden), charged from wallet/credits at purchase.
  Track `wizard_uses` per user; reset/window policy is admin-set. The dashboard
  path is always free — the fee only rewards the *convenience* of the wizard.

---

## 7. Session persistence (save & resume)

A `wizard_sessions` row per user (state, collected answers, selected item, TTL).
On minimise/top-up/return, the widget rehydrates the last state. Selected numbers
are **soft-held** only as long as the provider allows; on resume the wizard
re-checks availability and, if gone, apologises and re-lists — exactly as the copy
promises.

---

## 8. Claude usage strategy (keep it cheap)

- **Deterministic first.** Every step works with **buttons only** — the wizard is
  fully usable with Claude **off**.
- **Claude is used only for:** (a) mapping a free-text answer to a fixed option
  ("I need to verify WhatsApp in Kenya" → purpose=OTP, service=WhatsApp,
  country=Kenya) and (b) answering a short user question. Both go through the
  existing `AnthropicClient`, are **cached** by normalized input, use a **tiny
  max_tokens**, and **fall back to buttons** on any error or when the key is off.
- **No Claude in the money path** — ordering/pricing/balance are pure code.
- Result: a snappy, near-free wizard that *feels* intelligent because the **logic**
  is intelligent, with Claude as a thin NLU sprinkle.

---

## 9. Dashboard reorganisation (source of order)

Restructure "My Connectivity" so nothing feels mixed:

- **Top-level tabs:** **eSIMs** · **Numbers** (each item tagged `eSIM` or
  `Number`).
- **Numbers grouped by type → then by model:** *Permanent* (with model badge, e.g.
  "Naara Line"), *Rental* (short/long, model badge), *OTP/verification* (model
  badge). No mixing across types.
- **Per-item state:** active · awaiting OTP · **expiring soon** · **expired →
  Archive**. A model that supports **renewal** shows a **Renew** action; a
  permanently-expired item is clearly labelled and moved to **Archive** (kept for
  history, never cluttering the active view).
- **Per-model sections** so OTP, rentals, and permanents each live under their
  owning model — clean, predictable, professional.
- eSIMs: **active** vs **expired/archived**, each with model badge, data left,
  QR/LPA, and renewal where supported.

Data model: numbers/eSIMs already have provider + type; add `model_key` (public
nickname), `archived_at`, `renewable`, `expires_at` surfacing so the UI can sort
deterministically.

---

## 10. NaaraCare handoff

When a user asks something the wizard shouldn't answer (billing dispute, refund,
account issue), it offers **one-tap** navigation to **NaaraCare** (`/support`,
the existing AI support agent) — inline, fast, with context passed so the agent
starts warm.

---

## 11. Widget UX

- Floating bottom-right, not blocking content; **animated glowing border** in the
  brand colours (a lightweight moving gradient stroke). Reduced-motion: static glow.
- **Collapse** via an X at the top of the widget; **re-open** from a header icon on
  the dashboard (clearly labelled).
- Progress within the wizard is obvious; every action shows a loading state (money
  actions disable while in flight). SVG icons only, dark-mode parity — same UI rules.

---

## 12. Build phases (each shippable + tested)

1. **Model registry + capability flags** (+ hide unconfigured) — pure data layer.
2. **Wizard state machine** (buttons only, no Claude) + widget shell + session save.
3. **Wire to real routers** (numbers, eSIM) + balance/save-resume + dashboard drop.
4. **Dashboard reorganisation** (tabs, model grouping, archive/renewal).
5. **Number matching** for Naara Line/Signal; **device check** for eSIM.
6. **Claude NLU sprinkle** (cached, fallback) + **NaaraCare** handoff.
7. **Wizard fee** (free 3×, then $0.45) + transparency copy.

## ⚠️ Build-safety notes (wait-for-later)

- **Soft-holding a number** across a top-up is provider-dependent — treat holds as
  best-effort and always re-validate on resume (never charge for a gone number).
- **Number-matching wildcards differ** by provider (Twilio meta-chars vs Telnyx
  literals) — abstract behind the model, don't leak provider syntax to users.
- **Data+number bundles** are rare — only expose when a model's flag proves it.
- **Keep Claude optional** — if the Anthropic key is absent, the wizard must still
  fully work on buttons. Do not make any purchase depend on an LLM response.

## Sources

- Twilio — [AvailablePhoneNumber (match patterns, NearNumber)](https://www.twilio.com/docs/phone-numbers/api/availablephonenumberlocal-resource)
- Telnyx — [Advanced number search (starts_with/ends_with/contains)](https://developers.telnyx.com/docs/numbers/phone-numbers/advanced-number-search)
- eSIM device support + `*#06#`/EID — [eSIM compatible phones 2026](https://www.easysim.global/blog/esim-phones)
