<?php
/**
 * WordPress Privacy Tools integration.
 *
 * Hooks into the native Tools > Export Personal Data and
 * Tools > Erase Personal Data screens so consent records are part of the
 * official GDPR data subject workflows.
 *
 * Erasure anonymises (it does not delete) since the record itself is the
 * lawful basis to keep the proof of consent.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the exporter.
 *
 * @param array $exporters Existing exporters.
 * @return array
 */
function tccl_register_personal_data_exporter( $exporters ) {
	$exporters['terms-conditions-consent-log'] = array(
		'exporter_friendly_name' => __( 'Terms & Conditions Consent Log', 'terms-conditions-consent-log' ),
		'callback'               => 'tccl_personal_data_exporter',
	);
	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'tccl_register_personal_data_exporter', 10 );

/**
 * Exporter callback.
 *
 * @param string $email_address Subject email.
 * @param int    $page          1-based page index.
 * @return array{data:array,done:bool}
 */
function tccl_personal_data_exporter( $email_address, $page = 1 ) {
	global $wpdb;
	$table    = tccl_get_table_name();
	$per_page = 100;
	$offset   = ( max( 1, (int) $page ) - 1 ) * $per_page;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is plugin-controlled.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name only.
			"SELECT * FROM {$table} WHERE email = %s ORDER BY id ASC LIMIT %d OFFSET %d",
			$email_address,
			$per_page,
			$offset
		)
	);

	$data = array();

	if ( ! empty( $rows ) ) {
		foreach ( $rows as $row ) {
			$item_data = array(
				array(
					'name'  => __( 'Recorded at (UTC)', 'terms-conditions-consent-log' ),
					'value' => $row->created_at,
				),
				array(
					'name'  => __( 'Type', 'terms-conditions-consent-log' ),
					'value' => $row->consent_type,
				),
				array(
					'name'  => __( 'Document version', 'terms-conditions-consent-log' ),
					'value' => $row->consent_version,
				),
				array(
					'name'  => __( 'Accepted', 'terms-conditions-consent-log' ),
					'value' => $row->consent_value ? __( 'Yes', 'terms-conditions-consent-log' ) : __( 'No', 'terms-conditions-consent-log' ),
				),
				array(
					'name'  => __( 'Order ID', 'terms-conditions-consent-log' ),
					'value' => $row->order_id,
				),
				array(
					'name'  => __( 'IP address', 'terms-conditions-consent-log' ),
					'value' => $row->ip_address,
				),
				array(
					'name'  => __( 'User agent', 'terms-conditions-consent-log' ),
					'value' => $row->user_agent,
				),
				array(
					'name'  => __( 'Source URL', 'terms-conditions-consent-log' ),
					'value' => isset( $row->source_url ) ? (string) $row->source_url : '',
				),
				array(
					'name'  => __( 'Accepted text', 'terms-conditions-consent-log' ),
					'value' => $row->consent_text,
				),
				array(
					'name'  => __( 'Integrity seal (SHA-256)', 'terms-conditions-consent-log' ),
					'value' => $row->consent_text_hash,
				),
			);

			$data[] = array(
				'group_id'    => 'tccl-consents',
				'group_label' => __( 'Consent log', 'terms-conditions-consent-log' ),
				'item_id'     => 'tccl-consent-' . (int) $row->id,
				'data'        => $item_data,
			);
		}
	}

	$done = count( (array) $rows ) < $per_page;

	return array(
		'data' => $data,
		'done' => $done,
	);
}

/**
 * Registers the eraser.
 *
 * @param array $erasers Existing erasers.
 * @return array
 */
function tccl_register_personal_data_eraser( $erasers ) {
	$erasers['terms-conditions-consent-log'] = array(
		'eraser_friendly_name' => __( 'Terms & Conditions Consent Log', 'terms-conditions-consent-log' ),
		'callback'             => 'tccl_personal_data_eraser',
	);
	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'tccl_register_personal_data_eraser', 10 );

/**
 * Eraser callback.
 *
 * Anonymises (does not delete): the record itself is the lawful basis
 * to keep evidence of the consent given.
 *
 * @param string $email_address Subject email.
 * @param int    $page          1-based page index.
 * @return array
 */
function tccl_personal_data_eraser( $email_address, $page = 1 ) {
	global $wpdb;
	$table    = tccl_get_table_name();
	$per_page = 100;
	$offset   = ( max( 1, (int) $page ) - 1 ) * $per_page;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is plugin-controlled.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name only.
			"SELECT id FROM {$table} WHERE email = %s ORDER BY id ASC LIMIT %d OFFSET %d",
			$email_address,
			$per_page,
			$offset
		)
	);

	$retained_count = 0;
	$messages       = array();

	if ( ! empty( $rows ) ) {
		foreach ( $rows as $row ) {
			$id = (int) $row->id;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$updated = $wpdb->update(
				$table,
				array(
					'email'      => 'anon-' . $id . '@anon.local',
					'ip_address' => '0.0.0.0',
					'user_agent' => '',
				),
				array( 'id' => $id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			if ( false !== $updated ) {
				++$retained_count;
			}
		}
	}

	if ( $retained_count > 0 ) {
		$messages[] = sprintf(
			/* translators: %d: number of records anonymised but kept. */
			_n(
				'%d consent record was anonymised. The record itself is kept as evidence of lawful processing.',
				'%d consent records were anonymised. The records themselves are kept as evidence of lawful processing.',
				$retained_count,
				'terms-conditions-consent-log'
			),
			$retained_count
		);
	}

	$done = count( (array) $rows ) < $per_page;

	return array(
		'items_removed'  => false,
		'items_retained' => $retained_count > 0,
		'messages'       => $messages,
		'done'           => $done,
	);
}
