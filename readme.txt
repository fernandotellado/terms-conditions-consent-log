=== Terms & Conditions Consent Log ===
Contributors: fernandot, ayudawp
Tags: woocommerce, gdpr, consent, terms, log
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tamper-evident GDPR consent log for WooCommerce: timestamp, IP, version, exact text, SHA-256 seal, printable PDF certificate.

== Description ==

WooCommerce stores a boolean for the terms checkbox, which is not enough to demonstrate consent under article 7.1 of the GDPR. The defensible record needs the timestamp, the IP, the user agent, the document version in force at that moment and the exact text the customer was shown.

Terms & Conditions Consent Log fills the gap. Every WooCommerce checkout writes a row to a dedicated indexed table, sealed with a SHA-256 hash of the accepted text so any later change is detectable. From a clean admin screen you can filter, search, export to CSV, integrate with the native WordPress Privacy Tools, and open a one-page printable A4 certificate per record (your browser saves it as PDF in one click).

= Why a dedicated table =

Storing thousands of consent records in `wp_postmeta` is wasteful and slow. The plugin uses its own indexed table and exposes a public function (`tccl_save_consent`) that you can call from contact forms, comments or any custom flow to log additional consents in the same place.

= Main features =

* Captures the native WooCommerce terms checkbox at checkout.
* Stores timestamp UTC, IP, user agent, document version and full consent text.
* Custom database table with the right indexes (no `wp_postmeta` bloat).
* **Tamper-evident**: each record is sealed with a SHA-256 hash. Any later change to the stored text is detected and reported as TAMPERED in the records list.
* **Printable A4 certificate** per record, with a built-in "Print / Save as PDF" button — the browser exports the certificate to PDF natively, no external library bundled.
* **Native Privacy Tools integration**: `Tools > Export Personal Data` and `Tools > Erase Personal Data` both include consent records (erasure anonymises rather than deletes — the record itself is the lawful basis to keep it).
* Both texts (checkbox and pre-checkout informational paragraph) are **optional** — leave them empty and the WooCommerce native text is shown to the customer and stored verbatim.
* Settings page to edit the checkbox text, the optional pre-checkout informational text and the document version.
* Automatic version bump when the text changes (suggests `MAJOR.MINOR-YYYY-MM-DD`).
* Optional opt-out of IP and/or user agent tracking.
* Configurable retention with a one-click anonymise button (records kept; PII scrubbed).
* Live partial-match filters (email, order, date range, type) + filtered CSV export with UTF-8 BOM (opens cleanly in Excel).
* Order metabox with the consent summary, integrity badge and outdated-version indicator.
* "Consent" column on the orders list (legacy and HPOS) with a quick visual status.
* Optional consent line in the WooCommerce New order email (admin) and the order confirmation email (customer) — both off by default.
* Optional `delete_data_on_uninstall` setting (off by default) — uninstalling does not destroy consent evidence unless you explicitly opt in.
* HPOS (custom order tables) compatible.
* Public `tccl_save_consent()` function to log consents from anywhere.

= Translation ready =

All strings use the `terms-conditions-consent-log` text domain. Spanish (es_ES) is bundled.

= Roadmap =

* Block Checkout (Gutenberg-based) support.
* Multiple configurable consent checkboxes (privacy, marketing, age verification, custom).
* Version history and diff viewer.
* REST API.
* WP-CLI commands.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/terms-conditions-consent-log/` or install through Plugins > Add New.
2. Activate the plugin.
3. Go to WooCommerce > Consent log > Settings if you want to override the checkbox text or add an informational paragraph above it. If you leave them empty, the WooCommerce native text is shown and recorded verbatim.
4. Every WooCommerce checkout will log the consent automatically from now on.

== Frequently Asked Questions ==

= Does it work with the new WooCommerce Block Checkout? =

Not yet. The classic checkout is fully supported. Block Checkout support is on the roadmap.

= Where is the data stored? =

In a custom indexed table called `wp_tccl_consents` (with your site prefix). Each order also gets three meta entries (`_tccl_terms_accepted`, `_tccl_terms_version`, `_tccl_recorded_at`) so the order edit screen can show the summary without querying the table.

= How do I bump the document version when I change my terms? =

Edit the version field in WooCommerce > Consent log > Settings, or simply check "Bump version on save". The plugin can also bump it automatically if it detects the checkbox text has changed but the version field has not.

= How do I delete or anonymise data for a specific customer? =

Use the WordPress native Tools > Erase Personal Data screen. The plugin registers an eraser that anonymises records linked to the requested email (it does not delete them, since the record itself is the lawful basis to keep the proof of consent). You can also anonymise filtered records from the Records tab.

= How do I export a customer's consent history? =

Use Tools > Export Personal Data. The plugin registers an exporter that returns every consent record linked to the requested email.

= Will an uninstall destroy my data? =

Only if you explicitly opt in. The setting "Delete all data on uninstall" is off by default. Even if you uninstall accidentally, your consent evidence will survive.

= Can I log consents from contact forms or comments too? =

Yes. The function `tccl_save_consent()` is public. Example for Contact Form 7:

`add_action( 'wpcf7_mail_sent', function( $form ) {
    if ( ! empty( $_POST['privacy_consent'] ) ) {
        tccl_save_consent( array(
            'email'           => sanitize_email( wp_unslash( $_POST['your-email'] ?? '' ) ),
            'consent_type'    => 'contact_form',
            'consent_version' => '1.0-2026-05-09',
            'consent_text'    => 'I have read and agree to the privacy policy.',
            'consent_value'   => 1,
        ) );
    }
});`

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
* Records the WooCommerce native terms checkbox at checkout (timestamp UTC, IP, user agent, version, exact text).
* SHA-256 integrity sealing per record.
* Printable A4 certificate per record (browser saves it as PDF).
* Native WordPress Privacy Tools integration (export and erase).
* Live partial-match filters (email, order, date range, type) with filtered CSV export.
* Optional opt-in lines in WooCommerce admin and customer order emails.
* Optional opt-in deletion of plugin data on uninstall (off by default).
* HPOS compatible.
* Spanish (es_ES) translation bundled.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Support ==

* [Official website](https://servicios.ayudawp.com)
* [WordPress support forum](https://wordpress.org/support/plugin/terms-conditions-consent-log/)
* [YouTube channel](https://www.youtube.com/AyudaWordPressES)
* [Documentation and tutorials](https://ayudawp.com)
