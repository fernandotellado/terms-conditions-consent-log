<?php
/**
 * CSV exporter for the consent log.
 * Streams in batches, respects the active filter, and prepends a metadata
 * header (site name, URL, export timestamp, applied filter, total rows) so
 * the resulting file is self-documenting when handed to a third party.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the column key → human label map used in the CSV header row.
 * Filterable via `tccl_csv_column_labels`.
 *
 * @return array<string,string>
 */
function tccl_csv_column_labels() {
	$labels = array(
		'id'                => __( 'ID', 'terms-conditions-consent-log' ),
		'order_id'          => __( 'Order ID', 'terms-conditions-consent-log' ),
		'user_id'           => __( 'User ID', 'terms-conditions-consent-log' ),
		'email'             => __( 'Email', 'terms-conditions-consent-log' ),
		'consent_type'      => __( 'Consent type', 'terms-conditions-consent-log' ),
		'consent_version'   => __( 'Document version', 'terms-conditions-consent-log' ),
		'consent_text'      => __( 'Accepted text', 'terms-conditions-consent-log' ),
		'consent_text_hash' => __( 'Integrity seal (SHA-256)', 'terms-conditions-consent-log' ),
		'consent_value'     => __( 'Accepted', 'terms-conditions-consent-log' ),
		'ip_address'        => __( 'IP address', 'terms-conditions-consent-log' ),
		'user_agent'        => __( 'User agent', 'terms-conditions-consent-log' ),
		'source_url'        => __( 'Source URL', 'terms-conditions-consent-log' ),
		'created_at'        => __( 'Recorded at (UTC)', 'terms-conditions-consent-log' ),
	);
	return apply_filters( 'tccl_csv_column_labels', $labels );
}

/**
 * Builds a one-line summary of the active filter for the CSV metadata block.
 *
 * @param array $filter Filter args.
 * @return string
 */
function tccl_csv_filter_summary( $filter ) {
	$parts = array();
	if ( ! empty( $filter['email'] ) ) {
		$parts[] = sprintf( '%s: %s*', __( 'Email starts with', 'terms-conditions-consent-log' ), $filter['email'] );
	}
	if ( '' !== (string) ( isset( $filter['order_id'] ) ? $filter['order_id'] : '' ) ) {
		$parts[] = sprintf( '%s: %s*', __( 'Order # starts with', 'terms-conditions-consent-log' ), $filter['order_id'] );
	}
	if ( ! empty( $filter['from'] ) ) {
		$parts[] = sprintf( '%s: %s', __( 'From', 'terms-conditions-consent-log' ), $filter['from'] );
	}
	if ( ! empty( $filter['to'] ) ) {
		$parts[] = sprintf( '%s: %s', __( 'To', 'terms-conditions-consent-log' ), $filter['to'] );
	}
	if ( ! empty( $filter['consent_type'] ) ) {
		$parts[] = sprintf( '%s: %s', __( 'Type', 'terms-conditions-consent-log' ), $filter['consent_type'] );
	}
	return empty( $parts ) ? __( 'None (full export)', 'terms-conditions-consent-log' ) : implode( '; ', $parts );
}

/**
 * Handles the export action.
 */
function tccl_handle_export() {
	if ( ! isset( $_GET['page'], $_GET['action'] ) ) {
		return;
	}
	if ( 'tccl-consents' !== $_GET['page'] || 'tccl_export_csv' !== $_GET['action'] ) {
		return;
	}
	if ( ! current_user_can( tccl_admin_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'terms-conditions-consent-log' ) );
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'tccl_export_csv' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'terms-conditions-consent-log' ) );
	}

	global $wpdb;
	$table = tccl_get_table_name();

	$filter                     = tccl_get_filter_args();
	list( $where_sql, $params ) = tccl_build_filter_where( $filter );

	$filename = 'tccl-consents-' . gmdate( 'Y-m-d-His' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required to keep large exports streaming on slow shared hostings.
	@set_time_limit( 0 );

	$output = fopen( 'php://output', 'w' );
	// UTF-8 BOM so Excel opens it correctly.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming a download to php://output, WP_Filesystem does not apply.
	fwrite( $output, "\xEF\xBB\xBF" );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
	$total     = empty( $params )
		? (int) $wpdb->get_var( $count_sql )
		: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
	// phpcs:enable

	// Metadata block (self-documenting export).
	$site_name = (string) get_bloginfo( 'name' );
	$site_url  = (string) home_url( '/' );
	fputcsv( $output, array( __( 'Site', 'terms-conditions-consent-log' ), $site_name ) );
	fputcsv( $output, array( __( 'Site URL', 'terms-conditions-consent-log' ), $site_url ) );
	fputcsv( $output, array( __( 'Exported at (UTC)', 'terms-conditions-consent-log' ), gmdate( 'Y-m-d H:i:s' ) ) );
	fputcsv( $output, array( __( 'Filter applied', 'terms-conditions-consent-log' ), tccl_csv_filter_summary( $filter ) ) );
	fputcsv( $output, array( __( 'Total records', 'terms-conditions-consent-log' ), $total ) );
	fputcsv( $output, array() ); // Blank line separator.

	// Column header row (nice, translated labels).
	$columns = apply_filters(
		'tccl_csv_columns',
		array( 'id', 'order_id', 'user_id', 'email', 'consent_type', 'consent_version', 'consent_text', 'consent_text_hash', 'consent_value', 'ip_address', 'user_agent', 'source_url', 'created_at' )
	);
	$labels       = tccl_csv_column_labels();
	$header_row   = array();
	foreach ( $columns as $col ) {
		$header_row[] = isset( $labels[ $col ] ) ? $labels[ $col ] : $col;
	}
	fputcsv( $output, $header_row );

	$batch  = 1000;
	$offset = 0;
	$yes    = __( 'Yes', 'terms-conditions-consent-log' );
	$no     = __( 'No', 'terms-conditions-consent-log' );

	do {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$sql          = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id ASC LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $batch, $offset ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ), ARRAY_A );
		// phpcs:enable

		if ( empty( $rows ) ) {
			break;
		}
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $columns as $col ) {
				$value = isset( $row[ $col ] ) ? $row[ $col ] : '';
				if ( 'consent_value' === $col ) {
					$value = (int) $value ? $yes : $no;
				}
				$line[] = $value;
			}
			fputcsv( $output, $line );
		}
		$offset += $batch;
		fflush( $output );
	} while ( count( $rows ) === $batch );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://output stream, WP_Filesystem does not apply.
	fclose( $output );
	exit;
}
add_action( 'admin_init', 'tccl_handle_export' );
