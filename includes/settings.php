<?php
/**
 * Settings helpers.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a single setting value, falling back to the default.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Optional fallback when no value is stored.
 * @return mixed
 */
function tccl_get_setting( $key, $default = '' ) {
	$settings = wp_parse_args(
		get_option( TCCL_OPTION_KEY, array() ),
		tccl_get_default_settings()
	);
	return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
}

/**
 * Returns all settings merged with defaults.
 *
 * @return array
 */
function tccl_get_all_settings() {
	return wp_parse_args(
		get_option( TCCL_OPTION_KEY, array() ),
		tccl_get_default_settings()
	);
}

/**
 * Suggests a new version string when content has changed.
 * Format: MAJOR.MINOR-YYYY-MM-DD. Increments MINOR and updates the date.
 *
 * @param string $current Current version string.
 * @return string Suggested next version.
 */
function tccl_suggest_next_version( $current ) {
	$today = gmdate( 'Y-m-d' );
	if ( preg_match( '/^(\d+)\.(\d+)-\d{4}-\d{2}-\d{2}$/', $current, $m ) ) {
		$major = (int) $m[1];
		$minor = (int) $m[2] + 1;
		return $major . '.' . $minor . '-' . $today;
	}
	return '1.0-' . $today;
}

/**
 * Returns the capability required to manage the plugin.
 * Filter `tccl_admin_capability` to override.
 *
 * @return string
 */
function tccl_admin_capability() {
	return apply_filters( 'tccl_admin_capability', 'manage_woocommerce' );
}
