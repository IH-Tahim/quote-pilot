# QuotePilot — Project Tracker & Build Plan

> Living source of truth for this build. Lives in the repo root, committed with the code.
> When switching models or resuming after a break: read **Context** + **Resume Here**, then continue.
> **Every task ends by updating this file** (tick the box, move the Resume pointer, log any new flag) in the same commit as the code.

Last updated: Keep this current · Current version: `v0.2.0-dev`

---

## 📋 Context

**What:** QuotePilot — a free WordPress.org plugin giving cleaning-service businesses an instant quote calculator, booking, and a customer dashboard. Built to be given to other cleaning businesses (commercial use, not paid).

**Who:** Author Imam Hasan (Tahim) · https://ihtahim.com

**Stack:** Plain PHP + vanilla JavaScript (no React/Vue — kept lightweight for speed, SEO, and plugin compatibility). One modular plugin; features are toggleable.

**Naming/prefixes:** Display name *QuotePilot* · slug/text-domain `quote-pilot` · functions/options/hooks `qp_` · classes `QP_` · tables `wp_qp_`.

**Build tools:** Antigravity (primary AI IDE), Cursor (fallback). Local dev: Local/Flywheel at `…\Local Sites\quotepilot-dev`, bundled PHP 8.2.29 for linting.

### 🛡️ Three Standing Rules (Apply to EVERY file/prompt):
1. **SECURITY:** Every PHP file starts with `if (!defined('ABSPATH')) exit;`. All DB access goes through `QP_Database` (prepared statements). Every AJAX handler calls `QP_Helpers::verify_request()`. Escape all output (`esc_html`/`esc_attr`/`esc_url`/`wp_kses`).
2. **PRICE INTEGRITY:** JavaScript only previews. PHP recalculates and is the ONLY source of truth. Never store or trust a price sent from the browser.
3. **PREFIX EVERYTHING:** `qp_` / `QP_` / `wp_qp_` / text-domain `quote-pilot`. No exceptions.

---

## ✅ Done

- [x] **Core A1–A8** — Main bootstrap, activator (creates 5 tables + `qp_customer` role + default options), `QP_Database` full CRUD with prepared statements & per-table format maps, `qp_service` CPT + pricing meta box, `QP_Modules` loader, `QP_Helpers` (`format_money`, `get_setting`, `sanitize_text`, `verify_request`, `sanitize_quote_input`). *(tag: `v0.1.0-dev`)*
- [x] **B0 — Canonical field contract** — `includes/qp-field-keys.php` (`QP_Fields::all/get/by_pricing_role`), 25 fields; sanitizer reconciled to loop the contract. Verified: unknown fields + browser-sent totals are dropped, numerics clamp, coupon uppercases, password survives untouched.
- [x] **B1 — Quote module loader + conditional enqueue** — `QP_Quote` loads assets only on pages containing `[quotepilot_form]`; localizes one `QPData` object (ajax_url, nonce `qp_quote_form`, currency, tax, services pricing, date rules, consent config, field contract). Stub assets + silence files in place.
- [x] **G0 — GitHub setup** — Git repository initialized, `.gitignore` configured, initial repository structure committed and successfully pushed to GitHub.
- [x] **B-META — Resolve pricing-source gaps** — Closed pricing-source gaps (room-size tiers, repeatable add-ons, oven-size, emergency surcharge settings).
- [x] **B2 — Authoritative Price Engine** — Implemented strict server-side calculation engine rounded to 2 decimals at each subtotal step. Handles unit multipliers (sqft, bedrooms, bathrooms, extra_bathrooms, living_rooms, stories, ovens), room size tiers, repeatable flat/percent add-ons, flat/percent surcharges, coupon states validation (expiry, limit checks), and GST/tax.
- [x] **B3 — Conditional Logic Engine** — Created conditional show/hide rules evaluator supporting complex comparison operators (is, is_not, greater_than, less_than, contains, checked) with logical combinations (all/any) and default visibility. Filters field inputs directly into the price engine.
- [x] **B4 — Mobile-First Form & Shortcode** — Created `[quotepilot_form]` shortcode and its mobile-first multi-step HTML/CSS wizard view. Incorporates accessible keyboard triggers, progress bar, dynamically resolved consent options, and a sticky summary footer in the mobile thumb zone. Scoped under HSL modern layout themes.
- [x] **B6 — Front-end JavaScript** — `quote-calculator.js` (527 lines): mirrors PHP price pipeline order exactly (base→flat add-ons→pct add-ons→flat surcharges→pct surcharges→discount→tax), mirrors `QP_Conditional` show/hide, dynamically renders add-on cards, updates sticky footer live. Preview-only — never submitted. `form-wizard.js` (465 lines): step nav, progress bar, per-step inline validation, password reveal toggle, AJAX submit via `FormData`, renders server confirmation panel from server response (not JS preview). `lead-capture.js` (134 lines): consent-gated, 500ms debounced, fires `qp_save_lead` POST only after consent box ticked. All three scripts load only on shortcode pages (B1 guarantee intact). Verified: JS preview $245.00 == PHP engine $245.00 for same inputs; browser fake $9.99 ignored; `qp_booking_created` fires on submit.
- [x] **B5 — Secure Submission Handler** — `QP_Submission` (AJAX `qp_submit`/`nopriv`). Nonce-first pipeline → sanitize via contract → validate service/email/consent (consent_data proof JSON w/ timestamp+IP) → conditional `evaluate()` → **server recalc via `QP_Price_Engine` (browser total ignored)** → `insert_booking` + `insert_booking_items` → coupon increment on success → guest `user_id=0` or `qp_customer` account opt-in → fires `qp_booking_created` seam. Conditional rules sourced via `qp_conditional_rules` filter (empty = all visible). **Verified end-to-end on the live DB: 16/16 assertions pass** — stored total == engine total (200.00, not the browser's 9.99), hidden oven field excluded (saved $120), guest user_id=0, account opt-in creates qp_customer, valid coupon 10% → 180.00 w/ usage incremented once, expired coupon rejected + no booking saved.

---

## ⏭️ In progress / Next (Multi-Model Completion Build Plan)

### Model Routing Strategy:
- 🔴 **Claude Opus 4.8** — Hard, creative, and money/security-critical code (price engine, submission pipeline, payments, final audit).
- 🟡 **Codex / Gemini Pro** — Medium structured work (meta boxes, conditional logic, form markup, dashboard, admin, notifications).
- 🟢 **Gemini Fast** — Easy mechanical work (readme, i18n, uninstall scaffolding, simple CRUD, asset stubs, git commands).

### Dependency & Sequence Map:
```
B-META [✅] ──> B2 [🔴] ──> B3 [🟡] ──> B4 [🟡] ──> B5 [🔴] ──> B6 [🟡] ──> B7 [🟡] ──┐
                                                                                   │
                                                          QUOTE ENGINE COMPLETE ───▼
                                                      C1 [🟡] (accounts/dashboard)
                                                             │
                                                             ▼
                                                      C2 [🟡] (admin settings/bookings)
                                                             │
                                                             ▼
                                                      C3 [🟢/🟡] (date-rules/coupons)
                                                             │
                                                             ▼
                                                      C4 [🟡] (notifications) ──┐
                                                             │                  │ (hooks C4 then C5)
                                                      C5 [🔴] (payments) 🔴 ────┘
                                                             │
                                                             ▼
                                                      D2 [🔴] (final audit)

⫶ PARALLEL-OK at any time: D1 [🟢] (readme / i18n / uninstall)
```

### The Checklist:
- [x] **G0** GitHub setup (gitignore, init, initial commit) — 🟢
- [x] **B-META** Resolve pricing-source gaps (room-size tiers, repeatable add-ons, oven-size, emergency surcharge settings) — 🟡
- [x] **B2** Authoritative PHP price engine ⛔ — 🔴
- [x] **B3** Conditional show/hide logic — 🟡
- [x] **B4** Multi-step mobile-first form + shortcode — 🟡
- [x] **B5** Secure submission handler (recalc + save + account opt-in + `qp_booking_created`) ⛔ — 🔴
- [x] **B6** Front-end JS (live preview mirror + wizard + lead capture) — 🟡
- [x] **B7** Consent-gated lead handler — 🟡
- [x] 🏁 **CHECKPOINT** Quote engine complete → Tag `v0.5.0-quote-engine`
- [x] **C1** Accounts + customer dashboard (email-keyed backfill) — 🟡
- [x] **C2** Admin: settings, bookings list table, single-booking, branding — 🟡
- [x] **C3** Date-rules calendar + coupons admin — 🟢
- [x] **C4** Notifications: email + WhatsApp click-to-chat + webhook/Mailchimp/Brevo — 🟡
- [ ] **C5** Payments: optional Stripe/PayPal, deposit modes, verified webhooks ⛔ — 🔴
- [ ] **D1** Readme.txt + i18n .pot + safe uninstall ⫶ — 🟢 *(Parallel-safe)*
- [ ] **D2** Final audit (security + performance + WP.org compliance) ⛔ — 🔴
- [ ] 🏁 **RELEASE** → Tag `v1.0.0`, submit to WordPress.org

---

## 🛠️ Step-by-Step Task Details & Prompts

This section contains the exact requirements and specifications for each remaining block of the build plan. Future agents should execute these prompts precisely.

### [🔴 Opus] PART B2 — The PHP Price Engine
* **File:** `modules/quote-calculator/class-qp-price-engine.php` — Class `QP_Price_Engine`.
* **Public Method:** `calculate( array $input, int $service_id, array $visible_keys = null ) : array`
* **Returns:**
  ```php
  [ 'base' => float, 'surcharge_total' => float, 'discount_total' => float,
    'tax_rate' => float, 'tax_total' => float, 'total' => float, 'items' => array ]
  ```
  *(where `items` are breakdown rows ready for `wp_qp_booking_items` insertion)*
* **Calculation Order (Authoritative Server-Side):**
  1. **base** = service base_price + per-unit multipliers (sqft*rate, bedrooms*rate, bathrooms*rate, extra_bathrooms*rate, living_rooms*rate, stories*rate, ovens*rate * oven_size multiplier, room_size/living_room_size tier multipliers if enabled).
  2. + **flat add-ons** (from service `_qp_addons` type=flat) = `subtotal_1`
  3. + **percent add-ons** (percent of `subtotal_1`) = `subtotal_2`
  4. + **flat surcharges** (emergency surcharge if flat; date high_rate if flat) = `subtotal_3`
  5. + **percent surcharges** (emergency surcharge/date high_rate if percent, computed on `subtotal_3`) = `subtotal_4`
  6. - **discount** (coupon: flat or percent of `subtotal_4`) = `subtotal_5`
  7. + **GST/tax** (rate from settings, only if service taxable) = **TOTAL**
* **Hard Requirements:**
  - **HIDDEN FIELDS COUNT AS ZERO:** If `$visible_keys` is provided, any field key not in it contributes nothing.
  - **COUPON VALIDATION:** Check coupon exists, is active (1), not expired (`expires_at`), and `usage_count` < `usage_limit` (unless `usage_limit=0`). Return invalid flags if so; do not increment usage here.
  - **ROUNDING:** Round money to 2 decimals at each stored step; never return floats in final outputs.
  - **BREAKDOWN:** Every contributing line becomes an `items[]` row: `item_label`, `item_type` (base|multiplier|addon_flat|addon_percent|surcharge|discount|tax), `quantity`, `unit_amount`, `line_total`.
  - **PURE FUNCTION:** No DB writes, no echo, no globals.
* **Test:** Write a CLI-style test verifying multi-field quote calculation, hidden-field exclusion, expired coupon rejection, and percentage ordering.

---

### [🟡 Pro] PART B3 — Conditional Show/Hide Logic
* **File:** `modules/quote-calculator/class-qp-conditional.php` — Class `QP_Conditional`.
* **Public Methods:**
  - `evaluate( array $rules, array $input ) : array` // Returns array of visible field keys
  - `is_visible( string $field_key, array $rules, array $input ) : bool`
* **Rule Shape:**
  ```php
  [ 'target_field' => 'oven_size',
    'action'       => 'show',          // show|hide
    'logic'        => 'all',           // all|any
    'conditions'   => [ ['field'=>'service_id','op'=>'is','value'=>'12'], ... ] ]
  ```
* **Operators:** `is`, `is_not`, `greater_than`, `less_than`, `contains` (for array fields), `checked`.
* **Requirements:** Fields with no rules are visible by default. `evaluate()` output directly maps to `$visible_keys` input of `QP_Price_Engine::calculate()`. Pure logic.

---

### [🟡 Pro] PART B4 — The Multi-Step Form (Mobile-First)
* **Files:**
  - `modules/quote-calculator/class-qp-shortcode.php` — Registers `[quotepilot_form]` with optional pre-selection attribute `service="slug"`.
  - `modules/quote-calculator/views/form.php` — Wizard markup.
* **Mobile-First UX Specifications:**
  - Single column, multi-step wizard, 5–6 steps max, with an animated progress bar.
  - Correct input types for mobile keyboards: `tel` for telephone, `email` for email, counts/sqft `type="number" inputmode="numeric"`.
  - Large tappable option CARDS (not raw selects) for service selection, room size, stories, oven size. Touch targets >= 44px, >= 8px spacing.
  - Sticky summary footer showing live running price (populated by B6 JS) with full-width primary CTA button in the thumb zone.
  - Dynamic consent area from `QPData.consent` (merged/split layout as configured).
  - Account opt-in checkbox (`create_account`) dynamically reveals the password field.
  - Fully accessible (aria, descriptive labels, keyboard navigable).

---

### [🔴 Opus] PART B5 — Submission Handler (Recalc & Save)
* **File:** `modules/quote-calculator/class-qp-submission.php` — Class `QP_Submission`.
* **AJAX Endpoints:** `wp_ajax_qp_submit` and `wp_ajax_nopriv_qp_submit` → `handle_submit()`.
* **Submission Pipeline:**
  1. Verify nonce (`qp_quote_form`) via `QP_Helpers::verify_request('qp_quote_form')`.
  2. Whitelist and sanitize input via `QP_Helpers::sanitize_quote_input($_POST)`.
  3. Validate required consent checkboxes. Save `consent_data` JSON (consented boxes, timestamp, IP).
  4. Run conditional rules to get visible keys: `QP_Conditional::evaluate()`.
  5. Calculate authoritative price server-side: `QP_Price_Engine::calculate()`. (NEVER trust client-provided totals).
  6. Create DB record via `QP_Database::insert_booking()` (`booking_status='pending'`) and `insert_booking_items()`.
  7. If coupon used, increment coupon count: `QP_Database::increment_coupon_usage()`.
  8. Account Creation: If `create_account` ticked and password present, create WP user with `qp_customer` role. Associate `user_id`. Handle existing emails gracefully.
  9. Trigger action hook: `do_action('qp_booking_created', $booking_id, $booking_row, $input);` for integrations/payments.
  10. Return JSON success with `booking_id`, `breakdown`, and next payment steps.

---

### [🟡 Pro] PART B6 — Front-End JavaScript (Preview, Wizard, Lead Capture)
* **Files:**
  - `public/js/quote-calculator.js` — Live Preview: Mirrors server-side pricing pipeline (B2) and conditional logic (B3) in vanilla JS. Re-evaluates on any input change. Renders running total and itemized list in the sticky footer.
  - `public/js/form-wizard.js` — Steps wizard back/next, manages progress bars, inline step-validation, reveals password fields, handles AJAX POST to form submit endpoint.
  - `public/js/lead-capture.js` — Captures emails/phones. Only POSTs partial save (`qp_save_lead`) AFTER consent checkbox is ticked.

---

### [🟡 Pro] PART B7 — Lead Handler (Server)
* **File:** `modules/quote-calculator/class-qp-leads.php` — Class `QP_Leads`.
* **AJAX Endpoints:** `wp_ajax_qp_save_lead` and `wp_ajax_nopriv_qp_save_lead` → `save_partial()`.
* **Pipeline:** Verify request nonce, check consent flag, sanitize inputs, upsert lead into `wp_qp_leads` by email (`lead_status='new'`).
* **Conversion Seam:** Hook to `qp_booking_created` to find matching email in `wp_qp_leads` and mark `lead_status='converted'` and set `converted_booking_id`.

---

### [🟡 Pro] PART C1 — Accounts & Customer Dashboard
* **Files:**
  - `modules/accounts/class-qp-accounts.php` — Handles registration and sign-on via core WP functions.
  - `modules/accounts/class-qp-email-link.php` — Hooks to `user_register` to backfill `user_id` on bookings matching the registration email where `user_id` was previously 0.
  - `modules/accounts/class-qp-dashboard.php` — Shortcode `[quotepilot_dashboard]` showing sign-in form for guests and an elegant list of bookings (status, date, total, itemized breakdown) for signed-in customers.

---

### [🟡 Pro] PART C2 — Admin Settings, Bookings List, & Branding
* **Files under `admin/`:**
  - `class-qp-admin.php` — Registers top-level and submenu pages.
  - `class-qp-settings.php` — Settings API page: currency, GST options, payment keys, consent configs, surcharge values, module toggles. Nonce-guarded, sanitizes inputs, masks private API credentials.
  - `class-qp-bookings-list.php` — `WP_List_Table` displaying booking rows (filterable, sortable). Detailed single-booking panel allowing status overrides, items inspection, and cleaner assignments.
  - `class-qp-branding.php` — Allows uploading custom logo, selecting primary/secondary colors, and typography fonts, outputting them as CSS variables scoped to our forms.

---

### [🟢 Fast / 🟡 Pro] PART C3 — Date Rules Calendar & Coupons Admin
* **Date Rules CRUD:** Screen to define specific dates as `closed` or `high_rate` (surcharge amount, type, note) and save them to `wp_qp_date_rules`.
* **Coupons CRUD:** Lists, adds, and updates coupon rows in `wp_qp_coupons`. Validates unique code strings, discount value and types, expiry limits, and usage limits.

---

### [🟡 Pro] PART C4 — Notifications (Email, WhatsApp, Webhooks)
* **All hooks bound to `qp_booking_created` action:**
  - `class-qp-email.php` — Sends stylized template emails via `wp_mail()` for customer confirmation and admin alert. Supports customizable dynamic merge tags.
  - `class-qp-whatsapp.php` — Builds free wa.me click-to-chat links pre-filled with dynamic booking details for both frontend confirmation screens and admin templates. Includes stubs for full API tiers.
  - `class-qp-integrations.php` — Outgoing JSON webhook trigger sending payload to custom webhook URL. Integrates with Mailchimp/Brevo contacts lists (only if marketing consent is logged).

---

### [🔴 Opus] PART C5 — Payments (Optional Stripe & PayPal)
* **Files under `modules/payments/`:**
  - `class-qp-payments.php` — Core router. Reads deposit requirements (full, half, none) and initiates gateway workflows.
  - `class-qp-stripe.php` and `class-qp-paypal.php` — Server-side Checkout session flows. Secret credentials loaded server-side only.
  - `class-qp-webhook.php` — Verifies webhook signatures and IPN notifications securely. On verification, updates `payment_status` (`paid` or `deposit_paid`), records transactions, and updates amounts. Safe, idempotent updates.

---

### [🟢 Fast] PART D1 — Readme, Translations, & Uninstall Scaffolding
* `readme.txt` — Standard WP.org format with descriptive installation, screenshots, and version tags.
* `uninstall.php` — Scoped guard checking `WP_UNINSTALL_PLUGIN`. Drops all 5 tables and option keys *only* if the settings opt-in for "Delete data on uninstall" is checked.
* `languages/quote-pilot.pot` — Scans plugin codebase to build translation templates.

---

### [🔴 Opus] PART D2 — Pre-Release Security, Performance, & WP.org Audit
* **Security Checks:** Verify absolute input sanitation, escaping throughout all output, nonce checks on every handler, capacity restrictions, and prepared queries.
* **Performance Checks:** Ensure assets only load conditionally where shortcodes are present. Add DB indexes on `user_id`, `customer_email`, `status`, and `date`.
* **WP.org Checks:** Review prefixing, GPL-compatible styling, code readability, and strict errors avoidance in PHP.

---

## 🚨 QA Gate & Escalation Protocol

### The QA Gate checklist:
Before committing and declaring any part "Done":
1. **LINT:** Run `php -l [file]` on every added/changed file → MUST return "No syntax errors detected".
2. **LOAD:** Load the site/wp-admin with `WP_DEBUG` active → MUST have zero PHP warnings, errors, or notices.
3. **SMOKE:** Run the part's specific smoke tests.
4. **COMMIT:** Execute `git add .` and `git commit -m "[message]"` using the specific message template.
5. **REPORT:** Document changes, files touched, smoke test outcomes, and any unresolved queries.

### Escalation Path:
- **Typo / syntax error:** Fast model (🟢) -> "Fix syntax error at [line] in [file]"
- **Logic issue / test failure:** Pro model (🟡) -> Paste actual vs expected outputs and error stacks.
- **Security / database / price integrity bug:** Opus model (🔴) -> Paste full error trace and logs.
- **Undiagnosed bug after 2 tries:** Opus model (🔴) -> "Trace this end to end using temporary error_log() probes, resolve, then remove probes."

---

## 📈 Changelog

- **v0.1.0-dev** — Core foundation: tables, DB layer, Services CPT, helpers, module loader.
- **v0.2.0-dev** — Canonical field contract (B0), module assets enqueue (B1), Git integration (G0), and pricing metadata settings (B-META) complete.
- **v0.3.0-dev** — Authoritative server-side price calculation engine (B2) and field show/hide conditional logic evaluator (B3).
- **v0.4.0-dev** — Mobile-first multi-step quote form CPT selectors, enqueued styles and shortcode templates (B4).
- **v0.4.1-dev** — Secure submission handler (B5): server-side recalc + booking/items persistence, consent proof, account opt-in, `qp_booking_created` integration seam. Verified 16/16 end-to-end against the live DB.
- **v0.4.2-dev** — Front-end JS (B6): live price preview (mirrors PHP pipeline exactly), multi-step wizard with inline validation, AJAX submit with server confirmation panel, consent-gated lead-capture debounced POST. Verified: preview $245.00 == PHP engine, browser fake ignored, B1 asset-loading guarantee intact.
- **v0.5.0-quote-engine** — Consent-gated partial lead capture (B7) with custom db upserts and booking convert flipping hooks. Wired and tagged primary milestone checkpoint.
- **v0.6.0-dev** — Customer accounts registration/login & linking linkage (C1), Admin options settings screens & list-tables (C2), Surcharge calendar & coupon rules CRUD (C3), Outbound transactional emails, wa.me links, & Mailchimp CRM webhooks (C4).

---

## ▶️ Resume Here

**Next Action:** Run **C5 (Payments Integration - Stripe/PayPal)** on **🔴 Opus**.
**Last completed & tested:** B7 to C4 — Consent-gated lead handler, customer accounts dashboard with linkage backfills, admin settings, list tables, color branding customizer, surcharge/coupon rules CRUD, email/WhatsApp notifications and outgoing webhooks. All verified using dedicated CLI test suites.
**Waiting on me:** Build C5 Payments Router and secret signature webhooks validation.
**Review checkpoints ahead:** C5, D2 — paste output to Claude before proceeding.
