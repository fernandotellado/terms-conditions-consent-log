<?php
/**
 * Plugin Name: Terms & Conditions Consent Log
 * Plugin URI: https://servicios.ayudawp.com
 * Description: Records WooCommerce checkout terms acceptance with timestamp, IP, version, and the exact text shown to the customer. Tamper-evident SHA-256 sealing, downloadable PDF certificate, and native WordPress Privacy Tools integration. Article 7.1 GDPR-compliant evidence in a dedicated table.
 * Version: 1.0.0
 * Author: Fernando Tellado
 * Author URI: https://tellado.es
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: terms-conditions-consent-log
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 10.7
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TCCL_VERSION', '1.0.0' );
define( 'TCCL_DB_VERSION', '1.1' );
define( 'TCCL_PLUGIN_FILE', __FILE__ );
define( 'TCCL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TCCL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TCCL_OPTION_KEY', 'tccl_settings' );
define( 'TCCL_TABLE_SUFFIX', 'tccl_consents' );

require_once TCCL_PLUGIN_DIR . 'includes/installer.php';
require_once TCCL_PLUGIN_DIR . 'includes/settings.php';
require_once TCCL_PLUGIN_DIR . 'includes/logger.php';
require_once TCCL_PLUGIN_DIR . 'includes/checkout.php';
require_once TCCL_PLUGIN_DIR . 'includes/emails.php';
require_once TCCL_PLUGIN_DIR . 'includes/order-metabox.php';
require_once TCCL_PLUGIN_DIR . 'includes/admin-page.php';
require_once TCCL_PLUGIN_DIR . 'includes/export-csv.php';
require_once TCCL_PLUGIN_DIR . 'includes/privacy-tools.php';
require_once TCCL_PLUGIN_DIR . 'includes/certificate-pdf.php';
require_once TCCL_PLUGIN_DIR . 'includes/class-tccl-promo-banner.php';

register_activation_hook( __FILE__, 'tccl_install' );
add_action( 'plugins_loaded', 'tccl_maybe_install' );

/**
 * Loads the plugin text domain.
 *
 * Required while the plugin ships translations of its own under /languages.
 * Once the plugin lives on WordPress.org and translations come from
 * translate.wordpress.org, this function can be removed (since WP 4.6).
 */
function tccl_load_textdomain() {
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required to load the bundled translations until the plugin is hosted on WordPress.org.
	load_plugin_textdomain(
		'terms-conditions-consent-log',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'init', 'tccl_load_textdomain', 5 );

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

/**
 * Returns the full table name with the WordPress prefix.
 *
 * @return string
 */
function tccl_get_table_name() {
	global $wpdb;
	return $wpdb->prefix . TCCL_TABLE_SUFFIX;
}

/**
 * Adds a "Settings" link to the plugin row on the plugins list.
 *
 * @param array $links Existing action links.
 * @return array
 */
function tccl_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=tccl-consents&tab=settings' ) ),
		esc_html__( 'Settings', 'terms-conditions-consent-log' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'tccl_plugin_action_links' );
