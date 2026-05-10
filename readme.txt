=== Terms & Conditions Consent Log ===
Contributors: fernandot, ayudawp
Tags: gdpr, consent, woocommerce, contact-form-7, privacy
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tamper-evident GDPR consent log: WooCommerce checkout, Contact Form 7, WP comments and a [tccl_consent_box] shortcode. Works with or without WooCommerce.

== Description ==

Article 7.1 of the GDPR demands more than a boolean: the defensible consent record needs the timestamp, the IP, the user agent, the document version in force at that moment and the exact text the user was shown.

Terms & Conditions Consent Log fills that gap for any acceptance checkbox on your site, with or without WooCommerce. Every accepted consent — at the WooCommerce checkout, in a Contact Form 7 form, in the WordPress comments form or in a stand-alone shortcode/block — writes a row to a dedicated indexed table, sealed with a SHA-256 hash of the accepted text so any later change is detectable. From a clean admin screen you can filter, search, export to CSV, integrate with the native WordPress Privacy Tools, and open a one-page printable A4 certificate per record (your browser saves it as PDF in one click).

= Works with or without WooCommerce =

If WooCommerce is active, the menu lives under WooCommerce → Consent log and the checkout is captured automatically. If it is not, the menu lives under Users → Consent log and the rest of the plugin (Records, Settings, CSV export, PDF certificate, Privacy Tools integration) keeps working the same way.

= Four sources of consent =

* **WooCommerce checkout** (auto when WC is active): captures the native terms checkbox.
* **Contact Form 7** (opt-in): detects [acceptance] fields automatically and the first email field of the form. Stored as `cf7_form_{ID}`, one type per form. No snippets required.
* **WordPress comments** (opt-in): logs the native `wp-comment-cookies-consent` checkbox (introduced in WP 4.9.6) when the visitor opts in. Stored as `comment_consent`.
* **`[tccl_consent_box]` shortcode and Gutenberg block**: drop a self-contained consent checkbox in any page, post, widget area or HTML field of a form builder. Submission posts to a REST endpoint and writes a record. Always available.

For anything else (Gravity Forms, WPForms, Fluent Forms, custom flows), call `tccl_save_consent()` from the appropriate hook.

= Why a dedicated table =

Storing thousands of consent records in `wp_postmeta` is wasteful and slow. The plugin uses its own indexed table and exposes a public function (`tccl_save_consent`) that you can call from anywhere to log additional consents in the same place.

= Main features =

* Records timestamp UTC, IP, user agent, document version, source URL and full consent text per acceptance.
* Custom database table with the right indexes (no `wp_postmeta` bloat).
* **Tamper-evident**: each record is sealed with a SHA-256 hash. Any later change to the stored text is detected and reported as TAMPERED in the records list.
* **Printable A4 certificate** per record, with a built-in "Print / Save as PDF" button — the browser exports the certificate to PDF natively, no external library bundled.
* **Native Privacy Tools integration**: `Tools > Export Personal Data` and `Tools > Erase Personal Data` both include consent records (erasure anonymises rather than deletes — the record itself is the lawful basis to keep it).
* WooCommerce checkout texts are **optional** — leave them empty and the WooCommerce native text is shown to the customer and stored verbatim.
* Automatic version bump when the text changes (suggests `MAJOR.MINOR-YYYY-MM-DD`).
* Optional opt-out of IP and/or user agent tracking.
* Configurable retention with a one-click anonymise button (records kept; PII scrubbed).
* Live partial-match filters (email, order, date range, type) + filtered CSV export with UTF-8 BOM (opens cleanly in Excel).
* (When WooCommerce is active) Order metabox with the consent summary, integrity badge and outdated-version indicator. "Consent" column on the orders list (legacy and HPOS) with a quick visual status. Optional consent line in the New order email (admin) and the order confirmation email (customer) — both off by default.
* Optional `delete_data_on_uninstall` setting (off by default) — uninstalling does not destroy consent evidence unless you explicitly opt in.
* HPOS (custom order tables) compatible.
* Public `tccl_save_consent()` function to log consents from anywhere.

= Translation ready =

All strings use the `terms-conditions-consent-log` text domain. Spanish (es_ES) is bundled.

= Roadmap =

* Block Checkout (Gutenberg-based) support for WooCommerce.
* Multiple configurable consent checkboxes per source (privacy, marketing, age verification, custom).
* Version history and diff viewer.
* More form-builder integrations (Gravity Forms, WPForms, Fluent Forms).
* Read REST API endpoints.
* WP-CLI commands.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/terms-conditions-consent-log/` or install through Plugins > Add New.
2. Activate the plugin.
3. Open the plugin admin page:
   * If WooCommerce is active: WooCommerce > Consent log.
   * Otherwise: Users > Consent log.
4. In the Settings tab, optionally enable the WordPress comments and/or Contact Form 7 integrations (off by default), or just paste `[tccl_consent_box]` in any page or post.
5. (Optional, WooCommerce only) Override the checkbox text or add a pre-checkout informational paragraph. Leave them empty to keep the WooCommerce native text.
6. Every accepted consent from any of the enabled sources will be logged automatically from now on.

== Frequently Asked Questions ==

= Can I use the plugin without WooCommerce? =

Yes. Activate it on any WordPress site and the menu appears under Users > Consent log. The Records, Settings, CSV export, PDF certificate and Privacy Tools integration all work the same way. The WooCommerce-specific bits (checkout capture, order metabox, order list column, order email line) only load when WooCommerce is active.

= How do I capture consents from Contact Form 7? =

Open Consent log > Settings > Integrations and tick "Log every CF7 form submission that ticks an [acceptance] field". Then make sure your CF7 forms include an [acceptance] field, e.g.:

`[acceptance privacy] I have read and agree to the privacy policy. [/acceptance]`

The plugin uses the form ID as part of the consent_type (cf7_form_{ID}), so each form is filterable separately. The first email field of the form is used as the subject email. No snippets, no functions.php edits.

= How does the [tccl_consent_box] shortcode work? =

It renders a self-contained consent checkbox + submit button, with optional email field for visitors who are not logged in. Submission posts to a REST endpoint that records the consent through tccl_save_consent(). Drop it in any page, post, widget area or HTML field of a form builder, e.g.:

`[tccl_consent_box text="I have read and agree to the privacy policy." consent_type="newsletter_signup"]`

The same functionality is also available as a Gutenberg block called "Consent box".

Important: do NOT use the shortcode as a substitute for the cookie checkbox of a cookie/banner plugin (Complianz, CookieYes, Real Cookie Banner, etc.). The legal context is different — cookie banners cover ePrivacy/cookies, this consent log covers GDPR art. 7.1 specific consents to specific personal-data processing. Mixing them yields ambiguous evidence.

= Are WordPress comment opt-ins logged automatically? =

No, they are off by default. Enable them in Consent log > Settings > Integrations. Only comments where the visitor ticks the native "Save my name, email, and website..." checkbox are recorded.

= Does it work with the new WooCommerce Block Checkout? =

Not yet. The classic checkout is fully supported. Block Checkout support is on the roadmap.

= Where is the data stored? =

In a custom indexed table called `wp_tccl_consents` (with your site prefix). When WooCommerce is active, each order also gets three meta entries (`_tccl_terms_accepted`, `_tccl_terms_version`, `_tccl_recorded_at`) so the order edit screen can show the summary without querying the table.

= How do I bump the document version when I change my terms? =

Edit the version field in Consent log > Settings, or simply check "Bump version on save". The plugin can also bump it automatically if it detects the checkbox text has changed but the version field has not.

= How do I delete or anonymise data for a specific customer? =

Use the WordPress native Tools > Erase Personal Data screen. The plugin registers an eraser that anonymises records linked to the requested email (it does not delete them, since the record itself is the lawful basis to keep the proof of consent). You can also anonymise filtered records from the Records tab.

= How do I export a customer's consent history? =

Use Tools > Export Personal Data. The plugin registers an exporter that returns every consent record linked to the requested email.

= Will an uninstall destroy my data? =

Only if you explicitly opt in. The setting "Delete all data on uninstall" is off by default. Even if you uninstall accidentally, your consent evidence will survive.

= How do I capture consents from Gravity Forms, WPForms, or any other source? =

Call the public `tccl_save_consent()` function from the relevant hook. Example for Gravity Forms:

`add_action( 'gform_after_submission', function ( $entry, $form ) {
    if ( ! empty( $entry['1.1'] ) ) { // ID of your consent checkbox in the entry.
        tccl_save_consent( array(
            'email'           => sanitize_email( $entry['2'] ?? '' ),
            'consent_type'    => 'gravity_form_' . absint( $form['id'] ),
            'consent_version' => '1.0-2026-05-10',
            'consent_text'    => 'I have read and agree to the privacy policy.',
            'consent_value'   => 1,
        ) );
    }
}, 10, 2 );`

Same idea for `wpforms_process_complete`, `fluentform/submission_inserted`, `user_register`, etc.

= Does the IP detection work behind Cloudflare or other reverse proxies? =

The plugin reads `REMOTE_ADDR` only and does not trust forwarded headers, which can be spoofed without a verified proxy. If your hosting puts the proxy IP in `REMOTE_ADDR` instead of the real client IP, all entries will record the proxy IP. Most WordPress-friendly hostings pass the real IP correctly.

= What does "Tamper-evident" mean here? =

When a consent is written, the plugin computes a SHA-256 hash of the exact accepted text and stores it alongside the record. On every read, the stored hash is compared against a freshly computed one — any difference is reported as TAMPERED in the records list, the order metabox and the certificate view. This is a cryptographic integrity check, not an electronic signature.

= Is the certificate a real PDF? =

The plugin renders a one-page A4 view with print-optimised CSS and a "Print / Save as PDF" button. Modern browsers (Chrome, Safari, Firefox, Edge) export that view to a real PDF natively — same fidelity as a server-side library would produce, with the added benefit that it respects your site's language and fonts. No external library bundled, so the plugin stays small.

== Screenshots ==

1. Records list with live filters, integrity column and CSV export.
2. Settings tab with editable texts, version control, retention, email options and uninstall control.
3. Order metabox with consent summary, integrity badge and printable-certificate button.
4. Consent column on the orders list.
5. Printable A4 certificate ready to be saved as PDF from the browser.
6. Exported CSV file with filtered records (metadata header + nice column names).

== Changelog ==

= 1.0.0 =
* Initial release.
* Works with or without WooCommerce. Menu under WooCommerce > Consent log when WC is active, otherwise under Users > Consent log. Capability defaults to manage_woocommerce or manage_options accordingly. Filterable via tccl_admin_menu_parent and tccl_admin_capability.
* Activation notice on the plugins screen with quick links to the Records and the Settings tabs.
* WooCommerce checkout capture (timestamp UTC, IP, user agent, version, source URL, exact text). Order metabox, "Consent" column on the orders list, optional consent lines in the New order admin email and the customer order email. HPOS compatible.
* Contact Form 7 integration (opt-in): captures every form submission that ticks an [acceptance] field, including the source URL of the page that hosted the form. Stored as cf7_form_{ID}, one type per form.
* WordPress comments integration (opt-in): captures the native wp-comment-cookies-consent checkbox (WP 4.9.6+) along with the post permalink. Stored as comment_consent.
* [tccl_consent_box] shortcode and Gutenberg block: stand-alone consent checkbox with REST endpoint, drop-in anywhere. The default text falls back to a configurable site-wide value in Settings > Integrations.
* Public tccl_save_consent() function (now accepting an optional source_url) for any other source (Gravity Forms, WPForms, custom flows…).
* Source URL recorded with every acceptance and shown on the records list, the PDF certificate, the CSV export and the Privacy Tools export.
* SHA-256 integrity sealing per record.
* Printable A4 certificate per record (browser saves it as PDF).
* Native WordPress Privacy Tools integration (export and erase).
* Live partial-match filters (email, order, date range, type) with filtered CSV export.
* Optional opt-in deletion of plugin data on uninstall (off by default).
* Spanish (es_ES) translation bundled.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Support ==

Need help or have suggestions?

* [Official website](https://servicios.ayudawp.com)
* [WordPress support forum](https://wordpress.org/support/plugin/eu-withdrawal-compliance/)
* [YouTube channel](https://www.youtube.com/AyudaWordPressES)
* [Documentation and tutorials](https://ayudawp.com)

Love the plugin? Please leave us a 5-star review and help spread the word!

== About AyudaWP.com ==

We are specialists in WordPress security, SEO, AI and performance optimization plugins. We create tools that solve real problems for WordPress site owners while maintaining the highest coding standards and accessibility requirements.