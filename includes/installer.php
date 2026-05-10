<?php
/**
 * Database installation and schema management.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the consent log table and seeds default settings.
 */
function tccl_install() {
	global $wpdb;
	$table           = tccl_get_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		order_id BIGINT(20) UNSIGNED DEFAULT 0,
		user_id BIGINT(20) UNSIGNED DEFAULT 0,
		email VARCHAR(255) NOT NULL,
		consent_type VARCHAR(50) NOT NULL,
		consent_version VARCHAR(50) NOT NULL,
		consent_text LONGTEXT NOT NULL,
		consent_text_hash CHAR(64) NOT NULL DEFAULT '',
		consent_value TINYINT(1) NOT NULL DEFAULT 0,
		ip_address VARCHAR(45) NOT NULL,
		user_agent TEXT NOT NULL,
		source_url VARCHAR(500) NOT NULL DEFAULT '',
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY order_id (order_id),
		KEY email (email),
		KEY consent_type (consent_type),
		KEY created_at (created_at)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	$existing = get_option( TCCL_OPTION_KEY );
	if ( false === $existing ) {
		update_option( TCCL_OPTION_KEY, tccl_get_default_settings() );
	}

	update_option( 'tccl_db_version', TCCL_DB_VERSION );
}

/**
 * Runs install when the schema version doesn't match.
 */
function tccl_maybe_install() {
	if ( get_option( 'tccl_db_version' ) !== TCCL_DB_VERSION ) {
		tccl_install();
	}
}

/**
 * Default plugin settings.
 *
 * Empty `terms_text` and `pre_checkout_text` mean the plugin does not
 * override the WooCommerce native checkbox or inject any informational
 * text. The admin opts in by editing those fields in Settings.
 *
 * @return array
 */
function tccl_get_default_settings() {
	return array(
		'terms_text'                  => '',
		'pre_checkout_text'           => '',
		'consent_version'             => '1.0-' . gmdate( 'Y-m-d' ),
		'track_ip'                    => 1,
		'track_user_agent'            => 1,
		'retention_days'              => 0,
		'delete_data_on_uninstall'    => 0,
		'email_admin_show_consent'    => 0,
		'email_customer_show_consent' => 0,
		'log_comment_consent'         => 0,
		'cf7_log_acceptance'          => 0,
	);
}
