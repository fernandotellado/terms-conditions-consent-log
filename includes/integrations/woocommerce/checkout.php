<?php
/**
 * WooCommerce checkout integration.
 *
 * Captures consent on order creation. The pre-checkout informational text
 * and the checkbox text are both opt-in: leave the settings empty and the
 * plugin does not override the WooCommerce defaults — but consent is still
 * logged with whatever text the customer actually saw.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the text the customer is currently shown next to the terms checkbox.
 *
 * Order of resolution:
 * 1. If the admin filled `terms_text` in Settings, return that.
 * 2. Otherwise fall back to whatever WooCommerce shows — `wc_get_terms_and_conditions_checkbox_text()`
 *    runs the native `woocommerce_get_terms_and_conditions_checkbox_text` filter and returns the
 *    canonical template with a `[terms]` placeholder. We replace that placeholder with the linked
 *    title of the terms page exactly like the WC `terms.php` template does, so the stored text
 *    matches what the customer actually saw.
 *
 * @return string HTML string (may contain a link).
 */
function tccl_resolve_displayed_terms_text() {
	$custom = (string) tccl_get_setting( 'terms_text', '' );
	if ( '' !== trim( $custom ) ) {
		return $custom;
	}

	if ( ! function_exists( 'wc_get_terms_and_conditions_checkbox_text' ) ) {
		return '';
	}

	$template = (string) wc_get_terms_and_conditions_checkbox_text();
	if ( '' === trim( $template ) ) {
		return '';
	}

	$page_id    = function_exists( 'wc_terms_and_conditions_page_id' ) ? wc_terms_and_conditions_page_id() : 0;
	$page       = $page_id ? get_post( $page_id ) : null;
	$terms_link = $page
		? '<a href="' . esc_url( get_permalink( $page->ID ) ) . '" class="woocommerce-terms-and-conditions-link" target="_blank">' . esc_html( $page->post_title ) . '</a>'
		: '';

	return str_replace( '[terms]', $terms_link, $template );
}

/**
 * Returns the same text suitable for use as a placeholder hint in the Settings
 * textarea (no HTML — just the readable string the customer would see).
 *
 * @return string
 */
function tccl_get_terms_text_placeholder_hint() {
	if ( ! function_exists( 'wc_get_terms_and_conditions_checkbox_text' ) ) {
		return __( 'I have read and agree to the website terms and conditions.', 'terms-conditions-consent-log' );
	}

	$template = (string) wc_get_terms_and_conditions_checkbox_text();
	if ( '' === trim( $template ) ) {
		return __( 'I have read and agree to the website terms and conditions.', 'terms-conditions-consent-log' );
	}

	$page_id = function_exists( 'wc_terms_and_conditions_page_id' ) ? wc_terms_and_conditions_page_id() : 0;
	$page    = $page_id ? get_post( $page_id ) : null;
	$title   = $page ? $page->post_title : __( 'terms and conditions', 'terms-conditions-consent-log' );

	return wp_strip_all_tags( str_replace( '[terms]', $title, $template ) );
}

/**
 * Replaces the WooCommerce native terms checkbox label only when the admin
 * has set a custom text. Empty setting = WooCommerce decides.
 *
 * @param string $text Default text from WooCommerce.
 * @return string
 */
function tccl_replace_terms_checkbox_text( $text ) {
	$custom = (string) tccl_get_setting( 'terms_text', '' );
	return '' !== trim( $custom ) ? $custom : $text;
}
add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', 'tccl_replace_terms_checkbox_text' );

/**
 * Renders the optional pre-checkout informational text just before the
 * terms checkbox. Empty setting = nothing rendered.
 */
function tccl_render_pre_checkout_text() {
	$pre = (string) tccl_get_setting( 'pre_checkout_text', '' );
	if ( '' === trim( $pre ) ) {
		return;
	}
	echo '<p class="tccl-pre-checkout-text">' . wp_kses_post( $pre ) . '</p>';
}
add_action( 'woocommerce_checkout_before_terms_and_conditions', 'tccl_render_pre_checkout_text' );

/**
 * Stores the terms acceptance value on the order before it is saved, so that
 * the consent record can be created with the final order ID in
 * `tccl_capture_after_order_processed`.
 *
 * @param WC_Order $order Order being created.
 * @param array    $data  Sanitised checkout data.
 */
function tccl_stash_pending_consent( $order, $data ) {
	$accepted = ! empty( $data['terms'] ) ? 1 : 0;
	$order->update_meta_data( '_tccl_pending_terms', $accepted );
}
add_action( 'woocommerce_checkout_create_order', 'tccl_stash_pending_consent', 10, 2 );

/**
 * Logs the consent record with the final order ID once WooCommerce has
 * processed the checkout. This avoids the email-window race condition of
 * the original implementation.
 *
 * @param int $order_id Final order ID.
 */
function tccl_capture_after_order_processed( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	// Idempotency: only log once per order.
	if ( $order->get_meta( '_tccl_recorded_at' ) ) {
		$order->delete_meta_data( '_tccl_pending_terms' );
		$order->save_meta_data();
		return;
	}

	$pending = $order->get_meta( '_tccl_pending_terms' );
	if ( '' === $pending ) {
		return;
	}

	$accepted = (int) $pending ? 1 : 0;
	$version  = (string) tccl_get_setting( 'consent_version', '1.0' );
	$text     = tccl_resolve_displayed_terms_text();

	$source_url = '';
	if ( function_exists( 'wc_get_checkout_url' ) ) {
		$source_url = (string) wc_get_checkout_url();
	}
	if ( '' === $source_url && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$source_url = esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) );
	}

	tccl_save_consent(
		array(
			'order_id'        => $order_id,
			'user_id'         => $order->get_user_id(),
			'email'           => $order->get_billing_email(),
			'consent_type'    => 'terms_and_privacy',
			'consent_version' => $version,
			'consent_text'    => $text,
			'consent_value'   => $accepted,
			'source_url'      => $source_url,
		)
	);

	$order->update_meta_data( '_tccl_terms_accepted', $accepted );
	$order->update_meta_data( '_tccl_terms_version', $version );
	$order->update_meta_data( '_tccl_recorded_at', current_time( 'mysql', true ) );
	$order->delete_meta_data( '_tccl_pending_terms' );
	$order->save_meta_data();
}
add_action( 'woocommerce_checkout_order_processed', 'tccl_capture_after_order_processed', 10, 1 );
