<?php
/**
 * Consent logging helpers.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saves a single consent record.
 *
 * Public API: other parts of the site (contact forms, comments, custom flows)
 * can call this to log their own consents.
 *
 * @param array $args {
 *     @type int    $order_id        Linked order ID. 0 if none.
 *     @type int    $user_id         WP user ID. 0 if anonymous.
 *     @type string $email           Required. Subject email.
 *     @type string $consent_type    Required. Free-form consent type slug.
 *     @type string $consent_version Document version in force.
 *     @type string $consent_text    Full text shown to the subject.
 *     @type int    $consent_value   1 = accepted, 0 = rejected.
 *     @type string $source_url      Optional. URL of the page where the consent was given.
 * }
 * @return int|false Insert ID on success, false on failure.
 */
function tccl_save_consent( $args ) {
	global $wpdb;

	$defaults = array(
		'order_id'        => 0,
		'user_id'         => 0,
		'email'           => '',
		'consent_type'    => '',
		'consent_version' => '',
		'consent_text'    => '',
		'consent_value'   => 0,
		'source_url'      => '',
	);
	$args     = wp_parse_args( $args, $defaults );
	$args     = apply_filters( 'tccl_save_consent_args', $args );

	if ( empty( $args['email'] ) || empty( $args['consent_type'] ) ) {
		return false;
	}

	$track_ua   = (int) tccl_get_setting( 'track_user_agent', 1 );
	$user_agent = '';
	if ( $track_ua && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	}

	$consent_text = wp_kses_post( $args['consent_text'] );
	$hash         = '' !== $consent_text ? hash( 'sha256', $consent_text ) : '';
	$source_url   = '';
	if ( ! empty( $args['source_url'] ) ) {
		$candidate = esc_url_raw( (string) $args['source_url'] );
		if ( '' !== $candidate ) {
			$source_url = mb_substr( $candidate, 0, 500 );
		}
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table, intentional.
	$result = $wpdb->insert(
		tccl_get_table_name(),
		array(
			'order_id'          => absint( $args['order_id'] ),
			'user_id'           => absint( $args['user_id'] ),
			'email'             => sanitize_email( $args['email'] ),
			'consent_type'      => sanitize_text_field( $args['consent_type'] ),
			'consent_version'   => sanitize_text_field( $args['consent_version'] ),
			'consent_text'      => $consent_text,
			'consent_text_hash' => $hash,
			'consent_value'     => $args['consent_value'] ? 1 : 0,
			'ip_address'        => tccl_get_client_ip(),
			'user_agent'        => $user_agent,
			'source_url'        => $source_url,
			'created_at'        => current_time( 'mysql', true ),
		),
		array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
	);

	if ( false === $result ) {
		return false;
	}

	$insert_id = (int) $wpdb->insert_id;
	do_action( 'tccl_consent_saved', $insert_id, $args );

	return $insert_id;
}

/**
 * Returns the client IP from REMOTE_ADDR. Forwarded headers are not trusted
 * because they can be spoofed without a verified reverse proxy.
 *
 * @return string
 */
function tccl_get_client_ip() {
	if ( ! (int) tccl_get_setting( 'track_ip', 1 ) ) {
		return '0.0.0.0';
	}
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		return rest_is_ip_address( $ip ) ? $ip : '0.0.0.0';
	}
	return '0.0.0.0';
}

/**
 * Returns true if the stored hash matches the recomputed hash of the stored text.
 *
 * @param object $record Row from the consent log.
 * @return bool
 */
function tccl_record_is_intact( $record ) {
	if ( empty( $record->consent_text_hash ) ) {
		return true; // Pre-1.1 records, no seal to verify against.
	}
	return hash_equals( $record->consent_text_hash, hash( 'sha256', (string) $record->consent_text ) );
}

/**
 * Counts records and how many are intact / tampered.
 *
 * @return array{total:int,intact:int,tampered:int,unsealed:int}
 */
function tccl_verify_all_records() {
	global $wpdb;
	$table = tccl_get_table_name();

	$totals = array(
		'total'    => 0,
		'intact'   => 0,
		'tampered' => 0,
		'unsealed' => 0,
	);

	$batch  = 1000;
	$offset = 0;

	do {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is plugin-controlled.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, consent_text, consent_text_hash FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d", $batch, $offset ) );
		if ( empty( $rows ) ) {
			break;
		}
		foreach ( $rows as $row ) {
			++$totals['total'];
			if ( '' === $row->consent_text_hash ) {
				++$totals['unsealed'];
				continue;
			}
			if ( hash_equals( $row->consent_text_hash, hash( 'sha256', (string) $row->consent_text ) ) ) {
				++$totals['intact'];
			} else {
				++$totals['tampered'];
			}
		}
		$offset += $batch;
	} while ( count( $rows ) === $batch );

	return $totals;
}

/**
 * Anonymises records older than the configured retention period.
 *
 * Records themselves are kept (proof of lawful processing) but PII fields
 * are scrubbed.
 *
 * @return int Number of rows updated.
 */
function tccl_anonymise_old_records() {
	$days = (int) tccl_get_setting( 'retention_days', 0 );
	if ( $days <= 0 ) {
		return 0;
	}

	global $wpdb;
	$table         = tccl_get_table_name();
	$anon_pattern  = $wpdb->esc_like( 'anon-' ) . '%' . $wpdb->esc_like( '@anon.local' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is plugin-controlled.
	$updated = (int) $wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name only.
			"UPDATE {$table}
			 SET email = CONCAT('anon-', id, '@anon.local'),
				 ip_address = '0.0.0.0',
				 user_agent = ''
			 WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
			   AND email NOT LIKE %s",
			$days,
			$anon_pattern
		)
	);

	if ( $updated > 0 ) {
		do_action( 'tccl_consent_anonymised', $updated );
	}

	return $updated;
}

/**
 * Anonymises records that match a filter (by email and/or order_id).
 * Used by the "Anonymise filtered results" button on the Records tab.
 *
 * @param array $filter {
 *     @type string $email    Email or partial email to match (LIKE prefix).
 *     @type int    $order_id Order ID to match exactly.
 * }
 * @return int Rows updated.
 */
function tccl_anonymise_records_by_filter( $filter ) {
	global $wpdb;
	$table        = tccl_get_table_name();
	$anon_pattern = $wpdb->esc_like( 'anon-' ) . '%' . $wpdb->esc_like( '@anon.local' );

	$where  = array( 'email NOT LIKE %s' );
	$params = array( $anon_pattern );

	if ( ! empty( $filter['email'] ) ) {
		$where[]  = 'email LIKE %s';
		$params[] = $wpdb->esc_like( $filter['email'] ) . '%';
	}
	if ( '' !== (string) ( isset( $filter['order_id'] ) ? $filter['order_id'] : '' ) ) {
		$where[]  = 'CAST(order_id AS CHAR) LIKE %s';
		$params[] = $wpdb->esc_like( (string) $filter['order_id'] ) . '%';
	}

	// Refuse to run with no caller-provided filter (the LIKE 'anon-' guard alone is not enough).
	if ( count( $where ) === 1 ) {
		return 0;
	}

	$where_sql = implode( ' AND ', $where );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$sql = "UPDATE {$table}
			SET email = CONCAT('anon-', id, '@anon.local'),
				ip_address = '0.0.0.0',
				user_agent = ''
			WHERE {$where_sql}";

	$updated = (int) $wpdb->query( $wpdb->prepare( $sql, $params ) );
	// phpcs:enable

	if ( $updated > 0 ) {
		do_action( 'tccl_consent_anonymised', $updated );
	}

	return $updated;
}

/**
 * Returns a single record by ID, or null.
 *
 * @param int $id Record ID.
 * @return object|null
 */
function tccl_get_record( $id ) {
	global $wpdb;
	$table = tccl_get_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is plugin-controlled.
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
}

/**
 * Returns the most recent record linked to an order, or null.
 *
 * @param int $order_id Order ID.
 * @return object|null
 */
function tccl_get_record_by_order( $order_id ) {
	global $wpdb;
	$table = tccl_get_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is plugin-controlled.
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY id DESC LIMIT 1", (int) $order_id ) );
}
