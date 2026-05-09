<?php
/**
 * Uninstall handler.
 *
 * Only deletes data when the admin explicitly opts in via the
 * `delete_data_on_uninstall` setting. The default is OFF so that an
 * accidental uninstall does not destroy consent evidence.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$tccl_settings = get_option( 'tccl_settings', array() );
$tccl_delete   = is_array( $tccl_settings ) && ! empty( $tccl_settings['delete_data_on_uninstall'] );

if ( ! $tccl_delete ) {
	return;
}

global $wpdb;

$tccl_table = $wpdb->prefix . 'tccl_consents';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name controlled by the plugin.
$wpdb->query( "DROP TABLE IF EXISTS {$tccl_table}" );

delete_option( 'tccl_settings' );
delete_option( 'tccl_db_version' );

$tccl_meta_keys = array( '_tccl_terms_accepted', '_tccl_terms_version', '_tccl_recorded_at', '_tccl_pending_terms' );
foreach ( $tccl_meta_keys as $tccl_key ) {
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Runs only on uninstall, not on every request.
	delete_metadata( 'post', 0, $tccl_key, '', true );
	if ( class_exists( '\\Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\OrdersTableDataStoreMeta' ) ) {
		$tccl_hpos_table = $wpdb->prefix . 'wc_orders_meta';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $tccl_hpos_table, array( 'meta_key' => $tccl_key ) );
	}
}
