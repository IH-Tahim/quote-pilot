=== QuotePilot ===
Contributors: imamhasan
Tags: quote, calculator, booking, pricing, cleaner, cleaning service, estimation
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Instant quote calculator, multi-step booking wizard, and customer dashboard for cleaning-service businesses.

== Description ==

QuotePilot is a powerful, lightweight, modular WordPress plugin designed specifically for cleaning service businesses. It enables you to offer instant online quotes, guide clients through an intuitive mobile-first multi-step booking wizard, and provide a secure customer dashboard—all without third-party subscriptions or heavy frameworks.

=== Features ===
* **Canonical Field Contract:** Robust handling of up to 25 predefined service estimation inputs with strict sanitization.
* **Authoritative Server-Side Price Engine:** Ensures price calculation logic is handled entirely on the server side to protect pricing integrity.
* **Complex Conditional Logic:** Pre-evaluate admin-defined rules to show or hide fields on the frontend dynamically.
* **Mobile-First Multi-Step Form:** An optimized wizard layout that feels native on mobile, complete with touch targets and progress tracking.
* **Partial Lead Capture:** Capture customer contacts (with consent gating) before form submission to recover abandoned leads.
* **Secure Customer Dashboard:** Quick registration and automatic guest booking linking by email backfill.
* **Extended Payment Support:** Integration with Stripe Checkout and PayPal Orders API for full/half deposits or pay-after-booking options.
* **Admin Control Center:** Complete configuration of surcharges, closed dates, coupons, lists, and notifications.

== Installation ==

1. Upload the entire `quote-pilot` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure settings under the 'QuotePilot' menu in your WordPress dashboard.
4. Insert the `[quotepilot_form]` shortcode on any page to display the quote wizard.
5. Insert the `[quotepilot_dashboard]` shortcode to display the secure customer dashboard.

== Frequently Asked Questions ==

= Is this plugin translation ready? =
Yes! The entire codebase uses translation functions (`__`, `esc_html__`, etc.) and supports full translation.

= Can I use it for other service businesses besides cleaning? =
Absolutely. While default fields are tailored for cleaning (bedrooms, bathrooms, ovens, etc.), it can easily adapt to other booking and service workflows.

= Are secret keys securely stored? =
Yes. Gateway secret keys are loaded exclusively on the server side and are never exposed to the frontend browser context.

== Screenshots ==

1. The modern, mobile-first multi-step booking wizard.
2. The interactive customer booking dashboard.
3. The QuotePilot administrator settings panel.

== Changelog ==

= 1.0.0 =
* Initial official production release.
* Added Stripe Checkout Sessions and PayPal Orders API v2 REST flows.
* Integrated transactional email alerts and outgoing Mailchimp webhooks.
* Implemented partial lead captures with database upsert seams.
* Built conditional show/hide rule evaluators and server recalculation engine.
