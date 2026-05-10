<?php
/**
 * WooCommerce email integration (opt-in).
 *
 * Adds a small consent line to the New order email (admin) and to the
 * order confirmation email (customer). Both options are OFF by default
 * — the admin enables them in Settings > Order emails.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inserts the consent line at the bottom of the order block.
 *
 * @param WC_Order $order         Order being emailed.
 * @param bool     $sent_to_admin True when the email is the admin notification.
 * @param bool     $plain_text    True when the plain-text email variant is being rendered.
 * @param WC_Email $email         The email object.
 */
function tccl_email_after_order_table( $order, $sent_to_admin, $plain_text, $email ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	if ( $sent_to_admin ) {
		if ( ! (int) tccl_get_setting( 'email_admin_show_consent', 0 ) ) {
			return;
		}
	} else {
		if ( ! (int) tccl_get_setting( 'email_customer_show_consent', 0 ) ) {
			return;
		}
	}

	$record = tccl_get_record_by_order( $order->get_id() );
	if ( ! $record ) {
		return;
	}

	$accepted = (int) $record->consent_value;
	$icon     = $accepted ? '✓' : '✗';
	$status   = $accepted ? __( 'Terms accepted', 'terms-conditions-consent-log' ) : __( 'Terms NOT accepted', 'terms-conditions-consent-log' );

	if ( $plain_text ) {
		echo "\n";
		if ( $sent_to_admin ) {
			echo esc_html(
				sprintf(
					/* translators: 1: status icon (✓ / ✗), 2: status text, 3: version, 4: IP address. */
					__( '%1$s %2$s · v%3$s · IP: %4$s', 'terms-conditions-consent-log' ),
					$icon,
					$status,
					$record->consent_version,
					$record->ip_address
				)
			) . "\n";
		} else {
			echo esc_html(
				sprintf(
					/* translators: 1: status icon (✓ / ✗), 2: status text, 3: version, 4: timestamp UTC. */
					__( '%1$s %2$s · v%3$s · %4$s UTC', 'terms-conditions-consent-log' ),
					$icon,
					$status,
					$record->consent_version,
					$record->created_at
				)
			) . "\n";
		}
		return;
	}

	echo '<p style="margin-top:1em;padding:8px 12px;border-left:3px solid #c3c4c7;background:#f6f7f7;font-size:13px;line-height:1.5;">';
	echo '<strong>' . esc_html( $icon . ' ' . $status ) . '</strong>';
	echo ' &middot; ' . esc_html( __( 'Version', 'terms-conditions-consent-log' ) ) . ': <code>' . esc_html( $record->consent_version ) . '</code>';

	if ( $sent_to_admin ) {
		echo ' &middot; IP: <code>' . esc_html( $record->ip_address ) . '</code>';
		$record_url = add_query_arg(
			array(
				'page'     => 'tccl-consents',
				'order_id' => $order->get_id(),
			),
			admin_url( 'admin.php' )
		);
		echo ' &middot; <a href="' . esc_url( $record_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View record', 'terms-conditions-consent-log' ) . '</a>';
	} else {
		echo ' &middot; ' . esc_html( $record->created_at ) . ' UTC';
	}

	echo '</p>';
}
add_action( 'woocommerce_email_after_order_table', 'tccl_email_after_order_table', 30, 4 );
