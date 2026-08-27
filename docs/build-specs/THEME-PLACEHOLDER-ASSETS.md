# Theme placeholder assets — swap these later

Per-theme home hero art seeded for the Theme System (Batch 2/§3). Each theme
carries its own hero image (`theme_presets.hero_assets.dashboard`), shown on the
dashboard home when the theme is applied. Files live under
`public/img/themes/` and were supplied by the owner (real Naara hero art).

**Owner note:** these are treated as placeholders — swap for final per-theme art
whenever ready. There are 8 unique images covering 14 personas, so some themes
currently SHARE an image (marked ⟳). Supply 6 more unique heroes to make every
theme 1:1.

| Theme | Hero image (`/img/themes/…`) | Unique? |
|---|---|---|
| Aurora Shift | islands-female.webp | ✓ |
| Sunset Transit | balloons.webp | ✓ |
| Midnight Signal | portal-gateway.webp | ✓ |
| Paperwhite | app-ui-phone.webp | ✓ |
| Ledger (fintra-clean) | before-after.webp | ✓ |
| Origin Bold | branded.webp | ✓ |
| Capable | app-ui-phone.webp | ⟳ (shares with Paperwhite) |
| Horizon (waitlisty-soft) | balloons.webp | ⟳ (shares with Sunset Transit) |
| Grid Nine (genius-grid) | before-after.webp | ⟳ (shares with Ledger) |
| Skyline (lander-hero) | worldwide.webp | ✓ |
| Aries | portal-gateway.webp | ⟳ (shares with Midnight Signal) |
| Emerald Route | islands-male.webp | ✓ |
| Coral Current | islands-female.webp | ⟳ (shares with Aurora Shift) |
| Slate Signal | worldwide.webp | ⟳ (shares with Skyline) |
| Naara Official | — (no theme hero; keeps admin HeroBackground behaviour) | n/a |

**Precedence on the dashboard home:** an admin-uploaded `HeroBackground` still
wins (an explicit choice); otherwise the active theme's hero shows; otherwise
the title + tiles render with no image (unchanged degrade).

**Reference-only images NOT shipped** (from `naarathemeseedimages.zip`, per the
blueprint §7 — they are screenshots of a different product's UI, supplied to
convey layout energy, never product imagery): `hero-inspo-traveler-map-unbranded`,
and the `dashboard-*` / `esim-marketplace-*` mockups.

**Storage note:** seeded to `public/img/themes/` (committed static assets) so
they work on both VPS and shared cPanel without Wasabi keys. When live Wasabi
keys exist, per-theme hero uploads can flow through `MediaStorage` like other
admin imagery; these committed defaults remain the fallback.
