<?php
/**
 * WooCommerce integration loader.
 *
 * Loaded only when WooCommerce is active. Pulls in checkout capture,
 * order email lines, the order edit screen metabox, and declares HPOS
 * compatibility.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

require_once __DIR__ . '/checkout.php';
require_once __DIR__ . '/emails.php';
require_once __DIR__ . '/order-metabox.php';

// WooCommerce HPOS (custom order tables) compatibility declaration.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				TCCL_PLUGIN_FILE,
				true
			);
		}
	}
);
