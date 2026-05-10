<?php
/**
 * Activation notice on the plugins screen.
 *
 * Shown only on `plugins.php` right after the plugin is activated. Carries
 * a button to the Consent log admin page and another to the Settings tab.
 * The visitor can dismiss the notice; the dismissal is persisted via an
 * AJAX call so it does not come back on the next page load.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TCCL_ACTIVATION_NOTICE_TRANSIENT = 'tccl_show_activation_notice';

/**
 * Sets the transient that triggers the notice on the next plugins.php load.
 */
function tccl_activation_notice_set_flag() {
	set_transient( TCCL_ACTIVATION_NOTICE_TRANSIENT, 1, MONTH_IN_SECONDS );
}
register_activation_hook( TCCL_PLUGIN_FILE, 'tccl_activation_notice_set_flag' );

/**
 * Renders the activation notice on plugins.php only.
 */
function tccl_activation_notice_render() {
	global $pagenow;
	if ( 'plugins.php' !== $pagenow ) {
		return;
	}
	if ( ! current_user_can( tccl_admin_capability() ) ) {
		return;
	}
	if ( ! get_transient( TCCL_ACTIVATION_NOTICE_TRANSIENT ) ) {
		return;
	}

	$records_url  = admin_url( 'admin.php?page=tccl-consents' );
	$settings_url = admin_url( 'admin.php?page=tccl-consents&tab=settings' );
	$dismiss_url  = wp_nonce_url(
		add_query_arg( 'tccl_dismiss_activation_notice', '1', admin_url( 'plugins.php' ) ),
		'tccl_dismiss_activation_notice'
	);
	?>
	<div class="notice notice-success is-dismissible tccl-activation-notice" data-tccl-dismiss-url="<?php echo esc_url( $dismiss_url ); ?>" data-tccl-nonce="<?php echo esc_attr( wp_create_nonce( 'tccl_dismiss_activation_notice' ) ); ?>">
		<p>
			<strong><?php esc_html_e( 'Terms & Conditions Consent Log is ready.', 'terms-conditions-consent-log' ); ?></strong>
			<?php
			if ( class_exists( 'WooCommerce' ) ) {
				esc_html_e( 'Your WooCommerce checkout is already being captured. You can also enable Contact Form 7, comments and the [tccl_consent_box] shortcode in Settings.', 'terms-conditions-consent-log' );
			} else {
				esc_html_e( 'Open the admin page to enable Contact Form 7, comments capture or grab the [tccl_consent_box] shortcode/block.', 'terms-conditions-consent-log' );
			}
			?>
		</p>
		<p>
			<a href="<?php echo esc_url( $records_url ); ?>" class="button button-primary"><?php esc_html_e( 'Open Consent log', 'terms-conditions-consent-log' ); ?></a>
			<a href="<?php echo esc_url( $settings_url ); ?>" class="button"><?php esc_html_e( 'Settings', 'terms-conditions-consent-log' ); ?></a>
		</p>
	</div>
	<script>
	( function () {
		var notice = document.querySelector( '.tccl-activation-notice' );
		if ( ! notice ) { return; }
		notice.addEventListener( 'click', function ( e ) {
			if ( ! e.target.classList || ! e.target.classList.contains( 'notice-dismiss' ) ) { return; }
			var url = notice.getAttribute( 'data-tccl-dismiss-url' );
			if ( ! url ) { return; }
			// Fire-and-forget; the server cleans up the transient.
			var img = new Image();
			img.src = url + '&_=' + Date.now();
		} );
	} )();
	</script>
	<?php
}
add_action( 'admin_notices', 'tccl_activation_notice_render' );

/**
 * Removes the transient when the user dismisses the notice or follows the
 * "Open Consent log" / "Settings" buttons.
 */
function tccl_activation_notice_handle_dismiss() {
	if ( ! is_admin() ) {
		return;
	}

	// Click on "X" sends a GET to plugins.php?tccl_dismiss_activation_notice=1.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
	if ( isset( $_GET['tccl_dismiss_activation_notice'] ) ) {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( wp_verify_nonce( $nonce, 'tccl_dismiss_activation_notice' ) ) {
			delete_transient( TCCL_ACTIVATION_NOTICE_TRANSIENT );
		}
	}

	// Visiting the plugin page is also a clear acknowledgement; clean up.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of which page is being rendered.
	if ( isset( $_GET['page'] ) && 'tccl-consents' === $_GET['page'] ) {
		delete_transient( TCCL_ACTIVATION_NOTICE_TRANSIENT );
	}
}
add_action( 'admin_init', 'tccl_activation_notice_handle_dismiss' );
