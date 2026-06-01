# QuotePilot — Project Tracker

> Living source of truth for this build. Lives in the repo root, committed with the code.
> When switching models or resuming after a break: read **Context** + **Resume Here**, then continue.
> **Every task ends by updating this file** (tick the box, move the Resume pointer, log any new flag) in the same commit as the code.

Last updated: keep this current · Current version: `v0.1.0-dev`

---

## Context

**What:** QuotePilot — a free WordPress.org plugin giving cleaning-service businesses an instant quote calculator, booking, and a customer dashboard. Built to be given to other cleaning businesses (commercial use, not paid).

**Who:** Author Imam Hasan (Tahim) · https://ihtahim.com

**Stack:** Plain PHP + vanilla JavaScript (no React/Vue — kept lightweight for speed, SEO, and plugin compatibility). One modular plugin; features are toggleable.

**Naming/prefixes:** Display name *QuotePilot* · slug/text-domain `quote-pilot` · functions/options/hooks `qp_` · classes `QP_` · tables `wp_qp_`. (Slug not final until WP.org submission — keep a backup name ready.)

**Build tools:** Antigravity (primary AI IDE), Cursor (fallback). Local dev: Local/Flywheel at `…\Local Sites\quotepilot-dev`, bundled PHP 8.2.29 for linting.

**Three standing rules (apply to every file/prompt):**
1. **Security** — every PHP file guards `ABSPATH`; all DB access via `QP_Database` (prepared statements); every AJAX handler calls `QP_Helpers::verify_request()`; escape all output.
2. **Price integrity** — JavaScript only previews; PHP recalculates and is the only source of truth. Never store/trust a browser-sent price.
3. **Prefix everything** — `qp_` / `QP_` / `wp_qp_` / `quote-pilot`.

**Scope decisions:**
- NO fixed time slots — multiple cleaners assigned manually; customer gives a preferred date/time only.
- Payments optional (Stripe + PayPal): full advance / half advance / pay-after-clean. No keys = booking still completes.
- Consent configurable: merged or split boxes + Terms & Conditions; PII only stored after consent.
- Data: 5 custom tables (`wp_qp_bookings`, `booking_items`, `leads`, `date_rules`, `coupons`); Services are a CPT (`qp_service`) with pricing in post meta.

---

## Done ✅

- [x] **Core A1–A8** — main bootstrap, activator (creates 5 tables + `qp_customer` role + default options), `QP_Database` full CRUD with prepared statements & per-table format maps, `qp_service` CPT + pricing meta box, `QP_Modules` loader, `QP_Helpers` (`format_money`, `get_setting`, `sanitize_text`, `verify_request`, `sanitize_quote_input`). *(tag: `v0.1.0-dev`)*
- [x] **B0 — canonical field contract** — `includes/qp-field-keys.php` (`QP_Fields::all/get/by_pricing_role`), 25 fields; sanitizer reconciled to loop the contract. Verified: unknown fields + browser-sent totals are dropped, numerics clamp, coupon uppercases, password survives untouched.
- [x] **B1 — quote module loader + conditional enqueue** — `QP_Quote` loads assets only on pages containing `[quotepilot_form]`; localizes one `QPData` object (ajax_url, nonce `qp_quote_form`, currency, tax, services pricing, date rules, consent config, field contract). Stub assets + silence files in place.
- [x] **G0 — GitHub setup** — Git repository initialized, `.gitignore` configured, initial repository structure committed and successfully pushed to GitHub.
- [x] **B-META — resolve pricing-source gaps** — Closed pricing-source gaps (room-size tiers, repeatable add-ons, oven-size, emergency surcharge settings).

---

## In progress / Next ⏭️

Legend: 🔴 Opus 4.8 (hard/creative/money/security) · 🟡 Pro/Codex (medium) · 🟢 Fast (easy) · ⛔ = paste output to Claude for review first · ⫶ = parallel-safe

**Run sequentially — each waits for the one above (except ⫶).**

- [x] **G0** GitHub setup (gitignore, init, initial commit; hand me push commands) — 🟢
- [x] **B-META** resolve pricing-source gaps (living-room/story price, oven-size multiplier, room-size tiers, repeatable add-ons, emergency surcharge setting) — 🟡
- [ ] **B2** authoritative PHP price engine ⛔ — 🔴  *blocks B5/B6*
- [ ] **B3** conditional show/hide logic — 🟡
- [ ] **B4** multi-step mobile-first form + shortcode — 🟡 *(optional 🔴 design polish)*
- [ ] **B5** secure submission handler (recalc + save + account opt-in + `qp_booking_created`) ⛔ — 🔴
- [ ] **B6** front-end JS (live preview mirror + wizard + lead capture) — 🟡
- [ ] **B7** consent-gated lead handler — 🟡
- [ ] 🏁 **CHECKPOINT** quote engine complete → tag `v0.5.0-quote-engine`
- [ ] **C1** accounts + customer dashboard (email-keyed backfill) — 🟡
- [ ] **C2** admin: settings, bookings list table, single-booking, branding — 🟡
- [ ] **C3** date-rules calendar + coupons admin — 🟢 *(🟡 if calendar fights back)*
- [ ] **C4** notifications: email + WhatsApp click-to-chat + webhook/Mailchimp/Brevo — 🟡
- [ ] **C5** payments: optional Stripe/PayPal, deposit modes, verified webhooks ⛔ — 🔴
- [ ] **D1** readme.txt + i18n .pot + safe uninstall ⫶ — 🟢
- [ ] **D2** final audit (security + performance + WP.org compliance) ⛔ — 🔴
- [ ] 🏁 **RELEASE** → tag `v1.0.0`, submit to WordPress.org

---

## QA gate (run after EVERY part)

```
1. LINT    php -l every changed PHP file → "No syntax errors detected"
2. LOAD    reload with WP_DEBUG on → zero PHP notices/warnings
3. SMOKE   run that part's functional test
4. COMMIT  git add + commit with the part's message
5. PUSH    git push (tag at 🏁 checkpoints)
6. TRACK   tick the box here, move "Resume Here", log any new open question
```
Debug setup (`wp-config.php`): `WP_DEBUG=true`, `WP_DEBUG_LOG=true`, `WP_DEBUG_DISPLAY=false`, `SCRIPT_DEBUG=true`. Watch with `tail -f wp-content/debug.log`.

Escalation: syntax→🟢 · logic/wrong output→🟡 · price/money/security/data→🔴 · can't localise→🔴 "trace end-to-end with temporary error_log probes, then remove them."

---

## Open questions / decisions to revisit

- **B-META pricing model** — I added room-size & living-room tier cards (small/med/large × multiplier), an oven-size large-multiplier, repeatable per-service add-ons (slug/label/type/value), and a global emergency surcharge (flat/%). **Confirm these match how you actually price** before relying on them.
- **Plugin slug** — `quote-pilot` looked free in the directory but isn't locked until WP.org submission. Keep a backup name; do a quick trademark glance before branding.
- **WP.org Contributors field** — needs your wordpress.org username (set when you create the account to submit).
- **SMTP** — recommend (don't bundle) an SMTP plugin for email deliverability; QuotePilot should detect and cooperate.
- **WP-Cron caveat (for v1.1)** — recovery emails will need real server cron on low-traffic sites.

---

## Deferred to v1.1 (NOT in v1.0)

Specced and ready; build after first release for scope control:
- UTM / click-ID capture (utm_*, gclid, fbclid, landing page, referrer, device) per lead/booking.
- Abandoned-lead recovery sequence (1h / 24h / 72h emails, consent-gated, via WP-Cron).
- DataLayer / GA4 / GTM events (quote_start, step_view, email_enter, price_update, quote_submit, booking_confirm, lead_abandon).

## Rejected (kept as long-term roadmap only)

Time slots · 2-way Google Calendar + staff calendars · auto-generated location/SEO pages (doorway-page risk) · white-label / hide-branding (.org concern) · multi-tenant SaaS (separate product).

---

## Changelog (human-readable, mirror into readme.txt at release)

- **v0.1.0-dev** — Core foundation: tables, DB layer, Services CPT, helpers, module loader.
- **v0.2.0-dev** — Canonical field contract (B0), module assets enqueue (B1), Git integration (G0), and pricing metadata settings (B-META).

---

## ▶️ Resume Here

**Next action:** Run **B2 (authoritative PHP price engine)** on 🔴 Opus.
**Last completed & tested:** B-META — Gaps in pricing sources resolved, and Git configuration complete.
**Waiting on me:** Confirm if any custom adjustments are needed for the B-META pricing options, then proceed to implementation of the secure, authoritative PHP price calculation engine (B2).
**Review checkpoints ahead:** B2, B5, C5, D2 — paste output to Claude before proceeding.
