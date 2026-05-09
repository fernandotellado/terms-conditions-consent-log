<?php
/**
 * Admin page: tabs for Records and Settings.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the admin menu under WooCommerce.
 */
function tccl_register_admin_menu() {
	add_submenu_page(
		'woocommerce',
		esc_html__( 'Consent log', 'terms-conditions-consent-log' ),
		esc_html__( 'Consent log', 'terms-conditions-consent-log' ),
		tccl_admin_capability(),
		'tccl-consents',
		'tccl_render_admin_page'
	);
}
add_action( 'admin_menu', 'tccl_register_admin_menu' );

/**
 * Enqueues admin assets only on the plugin page and on order edit screens.
 *
 * @param string $hook Current admin page hook suffix.
 */
function tccl_enqueue_admin_assets( $hook ) {
	$is_plugin = ( 'woocommerce_page_tccl-consents' === $hook );
	$is_hpos   = ( false !== strpos( (string) $hook, 'wc-orders' ) );
	$is_legacy = false;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of which order screen is being rendered, no state change.
	if ( 'post.php' === $hook && isset( $_GET['post'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of post type for the requested post.
		$post_type = get_post_type( absint( $_GET['post'] ) );
		$is_legacy = ( 'shop_order' === $post_type );
	}

	if ( ! $is_plugin && ! $is_hpos && ! $is_legacy ) {
		return;
	}

	wp_enqueue_style( 'tccl-admin', TCCL_PLUGIN_URL . 'assets/css/admin.css', array(), TCCL_VERSION );

	if ( $is_plugin ) {
		wp_enqueue_script( 'tccl-admin', TCCL_PLUGIN_URL . 'assets/js/admin.js', array(), TCCL_VERSION, true );
		add_thickbox();
	}
}
add_action( 'admin_enqueue_scripts', 'tccl_enqueue_admin_assets' );

/**
 * Saves Settings tab.
 */
function tccl_handle_settings_save() {
	if ( ! isset( $_POST['tccl_save_settings'] ) ) {
		return;
	}
	if ( ! current_user_can( tccl_admin_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'terms-conditions-consent-log' ) );
	}
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'tccl_save_settings' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'terms-conditions-consent-log' ) );
	}

	$current = tccl_get_all_settings();
	$updated = $current;

	$updated['terms_text']                  = isset( $_POST['terms_text'] ) ? wp_kses_post( wp_unslash( $_POST['terms_text'] ) ) : '';
	$updated['pre_checkout_text']           = isset( $_POST['pre_checkout_text'] ) ? wp_kses_post( wp_unslash( $_POST['pre_checkout_text'] ) ) : '';
	$updated['consent_version']             = isset( $_POST['consent_version'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_version'] ) ) : $current['consent_version'];
	$updated['track_ip']                    = isset( $_POST['track_ip'] ) ? 1 : 0;
	$updated['track_user_agent']            = isset( $_POST['track_user_agent'] ) ? 1 : 0;
	$updated['retention_days']              = isset( $_POST['retention_days'] ) ? absint( $_POST['retention_days'] ) : 0;
	$updated['delete_data_on_uninstall']    = isset( $_POST['delete_data_on_uninstall'] ) ? 1 : 0;
	$updated['email_admin_show_consent']    = isset( $_POST['email_admin_show_consent'] ) ? 1 : 0;
	$updated['email_customer_show_consent'] = isset( $_POST['email_customer_show_consent'] ) ? 1 : 0;

	$bump_version = isset( $_POST['tccl_bump_version'] ) ? 1 : 0;
	if ( $bump_version || ( $updated['terms_text'] !== $current['terms_text'] && $updated['consent_version'] === $current['consent_version'] ) ) {
		$updated['consent_version'] = tccl_suggest_next_version( $current['consent_version'] );
	}

	update_option( TCCL_OPTION_KEY, $updated );

	delete_transient( 'tccl_status_block' );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'    => 'tccl-consents',
				'tab'     => 'settings',
				'updated' => 1,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_init', 'tccl_handle_settings_save' );

/**
 * Anonymise (retention-based).
 */
function tccl_handle_anonymise() {
	if ( ! isset( $_POST['tccl_anonymise_now'] ) ) {
		return;
	}
	if ( ! current_user_can( tccl_admin_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'terms-conditions-consent-log' ) );
	}
	$nonce = isset( $_POST['_tccl_anonymise_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_tccl_anonymise_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'tccl_anonymise' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'terms-conditions-consent-log' ) );
	}

	$count = tccl_anonymise_old_records();

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'       => 'tccl-consents',
				'tab'        => 'settings',
				'anonymised' => $count,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_init', 'tccl_handle_anonymise' );

/**
 * Anonymise filtered records (Records tab).
 */
function tccl_handle_anonymise_filtered() {
	if ( ! isset( $_POST['tccl_anonymise_filtered'] ) ) {
		return;
	}
	if ( ! current_user_can( tccl_admin_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'terms-conditions-consent-log' ) );
	}
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'tccl_anonymise_filtered' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'terms-conditions-consent-log' ) );
	}

	$order_id_raw = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
	$filter       = array(
		'email'    => isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '',
		'order_id' => preg_replace( '/[^0-9]/', '', (string) $order_id_raw ),
	);

	$count = tccl_anonymise_records_by_filter( $filter );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'             => 'tccl-consents',
				'tab'              => 'records',
				'anonymised_count' => $count,
				'email'            => $filter['email'],
				'order_id'         => '' !== $filter['order_id'] ? $filter['order_id'] : '',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_init', 'tccl_handle_anonymise_filtered' );

/**
 * Verify-integrity action.
 */
function tccl_handle_verify_integrity() {
	if ( ! isset( $_POST['tccl_verify_integrity'] ) ) {
		return;
	}
	if ( ! current_user_can( tccl_admin_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'terms-conditions-consent-log' ) );
	}
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'tccl_verify_integrity' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'terms-conditions-consent-log' ) );
	}

	$totals = tccl_verify_all_records();

	set_transient(
		'tccl_verify_result',
		$totals,
		MINUTE_IN_SECONDS * 5
	);

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'     => 'tccl-consents',
				'tab'      => 'records',
				'verified' => 1,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_init', 'tccl_handle_verify_integrity' );

/**
 * Reads sanitized filter values from $_GET.
 *
 * `order_id` is kept as a string so that the prefix LIKE filter can match
 * partial inputs (e.g. "12" matches order #12, #120, #1234).
 *
 * @return array
 */
function tccl_get_filter_args() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter values for display; no nonce required.
	$order_id = '';
	if ( isset( $_GET['order_id'] ) ) {
		$raw      = sanitize_text_field( wp_unslash( $_GET['order_id'] ) );
		$order_id = preg_replace( '/[^0-9]/', '', (string) $raw );
	}

	$args = array(
		'email'        => isset( $_GET['email'] ) ? sanitize_text_field( wp_unslash( $_GET['email'] ) ) : '',
		'order_id'     => $order_id,
		'from'         => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
		'to'           => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '',
		'consent_type' => isset( $_GET['consent_type'] ) ? sanitize_key( wp_unslash( $_GET['consent_type'] ) ) : '',
	);
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	return $args;
}

/**
 * Builds a parametrised WHERE for filter args.
 *
 * @param array $filter Filter args.
 * @return array{0:string,1:array} where_sql, params
 */
function tccl_build_filter_where( $filter ) {
	global $wpdb;

	$where  = array( '1=1' );
	$params = array();

	if ( ! empty( $filter['email'] ) ) {
		$where[]  = 'email LIKE %s';
		$params[] = $wpdb->esc_like( $filter['email'] ) . '%';
	}
	if ( '' !== (string) $filter['order_id'] ) {
		$where[]  = 'CAST(order_id AS CHAR) LIKE %s';
		$params[] = $wpdb->esc_like( (string) $filter['order_id'] ) . '%';
	}
	if ( ! empty( $filter['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter['from'] ) ) {
		$where[]  = 'created_at >= %s';
		$params[] = $filter['from'] . ' 00:00:00';
	}
	if ( ! empty( $filter['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filter['to'] ) ) {
		$where[]  = 'created_at <= %s';
		$params[] = $filter['to'] . ' 23:59:59';
	}
	if ( ! empty( $filter['consent_type'] ) ) {
		$where[]  = 'consent_type = %s';
		$params[] = $filter['consent_type'];
	}

	$where_sql = implode( ' AND ', $where );
	$where_sql = apply_filters( 'tccl_records_query_where', $where_sql, $filter );

	return array( $where_sql, $params );
}

/**
 * Returns true when at least one filter arg has a value.
 *
 * @param array $filter Filter args.
 * @return bool
 */
function tccl_filter_is_active( $filter ) {
	foreach ( $filter as $value ) {
		if ( ! empty( $value ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Returns the distinct consent_type values present in the table.
 *
 * @return array
 */
function tccl_get_distinct_types() {
	global $wpdb;
	$table = tccl_get_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $table is plugin-controlled.
	$rows = $wpdb->get_col( "SELECT DISTINCT consent_type FROM {$table} ORDER BY consent_type" );
	return is_array( $rows ) ? $rows : array();
}

/**
 * AJAX endpoint: returns the rendered HTML of the records body for the
 * current filter. Used by the live filter so the page does not reload and
 * the input keeps focus.
 */
function tccl_handle_ajax_filter_records() {
	if ( ! current_user_can( tccl_admin_capability() ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'terms-conditions-consent-log' ) ), 403 );
	}
	$nonce = isset( $_REQUEST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'tccl_filter_records' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'terms-conditions-consent-log' ) ), 403 );
	}

	$_GET = wp_unslash( $_REQUEST );

	global $wpdb;
	$table    = tccl_get_table_name();
	$filter   = tccl_get_filter_args();
	$paged    = isset( $_REQUEST['paged'] ) ? max( 1, absint( $_REQUEST['paged'] ) ) : 1;
	$per_page = 10;
	$offset   = ( $paged - 1 ) * $per_page;

	list( $where_sql, $params ) = tccl_build_filter_where( $filter );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
	$total     = empty( $params )
		? (int) $wpdb->get_var( $count_sql )
		: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

	$sql          = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
	$query_params = array_merge( $params, array( $per_page, $offset ) );
	$items        = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) );
	// phpcs:enable

	$current_version = (string) tccl_get_setting( 'consent_version', '' );

	ob_start();
	tccl_render_records_body( $items, $total, $current_version, $filter, $per_page, $paged );
	$html = ob_get_clean();

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_tccl_filter_records', 'tccl_handle_ajax_filter_records' );

/**
 * Renders the admin page (tab router).
 */
function tccl_render_admin_page() {
	if ( ! current_user_can( tccl_admin_capability() ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab selection is read-only display state, not a state change.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'records';
	if ( ! in_array( $tab, array( 'records', 'settings' ), true ) ) {
		$tab = 'records';
	}
	?>
	<div class="wrap tccl-wrap">
		<h1><?php esc_html_e( 'Terms &amp; Conditions Consent Log', 'terms-conditions-consent-log' ); ?></h1>

		<h2 class="nav-tab-wrapper">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tccl-consents&tab=records' ) ); ?>" class="nav-tab <?php echo 'records' === $tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Records', 'terms-conditions-consent-log' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tccl-consents&tab=settings' ) ); ?>" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Settings', 'terms-conditions-consent-log' ); ?>
			</a>
		</h2>

		<?php
		if ( 'settings' === $tab ) {
			tccl_render_settings_tab();
		} else {
			tccl_render_records_tab();
		}

		tccl_render_promo_separator();
		$promo = new TCCL_Promo_Banner( 'terms-conditions-consent-log', 'tccl' );
		$promo->render();
		?>
	</div>
	<?php
}

/**
 * Visual separator before the promo banner.
 */
function tccl_render_promo_separator() {
	echo '<hr class="tccl-promo-separator">';
}

/**
 * Renders the Records tab.
 */
function tccl_render_records_tab() {
	global $wpdb;
	$table = tccl_get_table_name();

	$filter   = tccl_get_filter_args();
	$active   = tccl_filter_is_active( $filter );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pagination state for display only.
	$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per_page = 10;
	$offset   = ( $paged - 1 ) * $per_page;

	list( $where_sql, $params ) = tccl_build_filter_where( $filter );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
	$total     = empty( $params )
		? (int) $wpdb->get_var( $count_sql )
		: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

	$sql          = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
	$query_params = array_merge( $params, array( $per_page, $offset ) );
	$items        = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) );
	// phpcs:enable

	$export_args = array_filter(
		array(
			'page'         => 'tccl-consents',
			'action'       => 'tccl_export_csv',
			'email'        => $filter['email'],
			'order_id'     => $filter['order_id'] ? $filter['order_id'] : '',
			'from'         => $filter['from'],
			'to'           => $filter['to'],
			'consent_type' => $filter['consent_type'],
		),
		static function ( $v ) {
			return '' !== $v && null !== $v;
		}
	);
	$export_url  = wp_nonce_url(
		add_query_arg( $export_args, admin_url( 'admin.php' ) ),
		'tccl_export_csv'
	);
	// Base URL for the JS to rebuild on filter changes (only page+action+nonce, no filter params).
	$export_base_url = wp_nonce_url(
		add_query_arg(
			array(
				'page'   => 'tccl-consents',
				'action' => 'tccl_export_csv',
			),
			admin_url( 'admin.php' )
		),
		'tccl_export_csv'
	);

	$current_version = (string) tccl_get_setting( 'consent_version', '' );
	$types           = tccl_get_distinct_types();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only notices set after a redirect we triggered ourselves.
	if ( isset( $_GET['anonymised_count'] ) ) {
		$count = absint( $_GET['anonymised_count'] );
		echo '<div class="notice notice-success is-dismissible"><p>';
		printf(
			esc_html(
				/* translators: %d: number of anonymised records. */
				_n( '%d filtered record anonymised.', '%d filtered records anonymised.', $count, 'terms-conditions-consent-log' )
			),
			absint( $count )
		);
		echo '</p></div>';
	}
	if ( isset( $_GET['verified'] ) ) {
		$totals = get_transient( 'tccl_verify_result' );
		if ( is_array( $totals ) ) {
			echo '<div class="notice notice-info is-dismissible"><p>';
			printf(
				/* translators: 1: total records, 2: intact records, 3: tampered records, 4: unsealed legacy records. */
				esc_html__( 'Verified %1$d records · %2$d intact · %3$d tampered · %4$d unsealed (legacy).', 'terms-conditions-consent-log' ),
				absint( $totals['total'] ),
				absint( $totals['intact'] ),
				absint( $totals['tampered'] ),
				absint( $totals['unsealed'] )
			);
			echo '</p></div>';
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	?>

	<form method="get" class="tccl-filter-form" id="tccl-filter-form" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'tccl_filter_records' ) ); ?>">
		<input type="hidden" name="page" value="tccl-consents">
		<input type="hidden" name="tab" value="records">

		<label class="tccl-filter-field">
			<span><?php esc_html_e( 'Email (starts with)', 'terms-conditions-consent-log' ); ?></span>
			<input type="text" name="email" value="<?php echo esc_attr( $filter['email'] ); ?>" placeholder="<?php esc_attr_e( 'customer@', 'terms-conditions-consent-log' ); ?>" autocomplete="off">
		</label>

		<label class="tccl-filter-field">
			<span><?php esc_html_e( 'Order # (starts with)', 'terms-conditions-consent-log' ); ?></span>
			<input type="text" inputmode="numeric" pattern="[0-9]*" name="order_id" value="<?php echo esc_attr( $filter['order_id'] ); ?>" placeholder="<?php esc_attr_e( '1234', 'terms-conditions-consent-log' ); ?>" autocomplete="off">
		</label>

		<label class="tccl-filter-field">
			<span><?php esc_html_e( 'From', 'terms-conditions-consent-log' ); ?></span>
			<input type="date" name="from" value="<?php echo esc_attr( $filter['from'] ); ?>" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'terms-conditions-consent-log' ); ?>">
		</label>

		<label class="tccl-filter-field">
			<span><?php esc_html_e( 'To', 'terms-conditions-consent-log' ); ?></span>
			<input type="date" name="to" value="<?php echo esc_attr( $filter['to'] ); ?>" placeholder="<?php esc_attr_e( 'YYYY-MM-DD', 'terms-conditions-consent-log' ); ?>">
		</label>

		<?php if ( ! empty( $types ) ) : ?>
			<label class="tccl-filter-field">
				<span><?php esc_html_e( 'Type', 'terms-conditions-consent-log' ); ?></span>
				<select name="consent_type">
					<option value=""><?php esc_html_e( 'Any', 'terms-conditions-consent-log' ); ?></option>
					<?php foreach ( $types as $type ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filter['consent_type'], $type ); ?>><?php echo esc_html( $type ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>

		<div class="tccl-filter-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tccl-consents&tab=records' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'terms-conditions-consent-log' ); ?></a>
			<a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary tccl-export" id="tccl-export-btn"
				data-base-url="<?php echo esc_url( $export_base_url ); ?>"
				data-label-all="<?php esc_attr_e( 'Export all to CSV', 'terms-conditions-consent-log' ); ?>"
				data-label-filtered="<?php esc_attr_e( 'Export filtered to CSV', 'terms-conditions-consent-log' ); ?>">
				<?php echo $active ? esc_html__( 'Export filtered to CSV', 'terms-conditions-consent-log' ) : esc_html__( 'Export all to CSV', 'terms-conditions-consent-log' ); ?>
			</a>
		</div>
	</form>

	<?php if ( $active ) : ?>
		<div class="tccl-records-toolbar">
			<form method="post" class="tccl-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Anonymise all records that match the current filter? Records are kept; PII is scrubbed.', 'terms-conditions-consent-log' ) ); ?>');">
				<?php wp_nonce_field( 'tccl_anonymise_filtered' ); ?>
				<input type="hidden" name="email" value="<?php echo esc_attr( $filter['email'] ); ?>">
				<input type="hidden" name="order_id" value="<?php echo $filter['order_id'] ? esc_attr( $filter['order_id'] ) : ''; ?>">
				<button type="submit" name="tccl_anonymise_filtered" value="1" class="button">
					<?php esc_html_e( 'Anonymise filtered', 'terms-conditions-consent-log' ); ?>
				</button>
			</form>
		</div>
	<?php endif; ?>

	<div id="tccl-records-body">
		<?php tccl_render_records_body( $items, $total, $current_version, $filter, $per_page, $paged ); ?>
	</div>

	<div class="tccl-verify-integrity">
		<form method="post" class="tccl-inline-form">
			<?php wp_nonce_field( 'tccl_verify_integrity' ); ?>
			<button type="submit" name="tccl_verify_integrity" value="1" class="button">
				<?php esc_html_e( 'Verify integrity', 'terms-conditions-consent-log' ); ?>
			</button>
		</form>
		<p class="description tccl-toolbar-help-text">
			<?php esc_html_e( 'Recomputes every record\'s SHA-256 and compares it to the sealed hash. Reports intact, tampered and unsealed (legacy) totals.', 'terms-conditions-consent-log' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Renders the count line + table + pagination. Called both from the initial
 * page render and from the AJAX endpoint.
 *
 * @param array  $items           Records.
 * @param int    $total           Total count for the filter.
 * @param string $current_version Current document version (for outdated badge).
 * @param array  $filter          Filter args.
 * @param int    $per_page        Per page.
 * @param int    $paged           Current page.
 */
function tccl_render_records_body( $items, $total, $current_version, $filter, $per_page, $paged ) {
	$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;
	?>
	<?php tccl_render_pagination_nav( $paged, $total, $total_pages, 'top' ); ?>

	<div class="tccl-table-wrap">
		<table class="wp-list-table widefat striped tccl-records-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'terms-conditions-consent-log' ); ?></th>
					<th><?php esc_html_e( 'Date (UTC)', 'terms-conditions-consent-log' ); ?></th>
					<th><?php esc_html_e( 'Email', 'terms-conditions-consent-log' ); ?></th>
					<th><?php esc_html_e( 'Order', 'terms-conditions-consent-log' ); ?></th>
					<th><?php esc_html_e( 'Type', 'terms-conditions-consent-log' ); ?></th>
					<th><?php esc_html_e( 'Version', 'terms-conditions-consent-log' ); ?></th>
					<th><?php esc_html_e( 'Status', 'terms-conditions-consent-log' ); ?></th>
					<th><?php esc_html_e( 'IP', 'terms-conditions-consent-log' ); ?></th>
					<th><?php esc_html_e( 'Integrity', 'terms-conditions-consent-log' ); ?></th>
					<th><?php esc_html_e( 'Accepted text + SHA-256', 'terms-conditions-consent-log' ); ?></th>
					<th class="tccl-actions-col"><?php esc_html_e( 'Actions', 'terms-conditions-consent-log' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="11"><?php esc_html_e( 'No records match your filter.', 'terms-conditions-consent-log' ); ?></td></tr>
				<?php else : ?>
					<?php
					foreach ( $items as $item ) :
						$is_outdated = '' !== $current_version && $item->consent_version !== $current_version;
						$is_intact   = tccl_record_is_intact( $item );
						?>
						<tr>
							<td><?php echo absint( $item->id ); ?></td>
							<td><code><?php echo esc_html( $item->created_at ); ?></code></td>
							<td class="tccl-cell-email"><?php echo esc_html( $item->email ); ?></td>
							<td>
								<?php if ( $item->order_id ) : ?>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $item->order_id ) . '&action=edit' ) ); ?>" target="_blank" rel="noopener noreferrer">#<?php echo absint( $item->order_id ); ?></a>
								<?php else : ?>—<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( $item->consent_type ); ?></code></td>
							<td><code><?php echo esc_html( $item->consent_version ); ?></code></td>
							<td>
								<?php if ( $item->consent_value ) : ?>
									<span class="tccl-status tccl-status-ok"><?php esc_html_e( 'YES', 'terms-conditions-consent-log' ); ?></span>
								<?php else : ?>
									<span class="tccl-status tccl-status-no"><?php esc_html_e( 'no', 'terms-conditions-consent-log' ); ?></span>
								<?php endif; ?>
								<?php if ( $is_outdated ) : ?>
									<span class="tccl-status tccl-status-outdated"><?php esc_html_e( 'Outdated', 'terms-conditions-consent-log' ); ?></span>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( $item->ip_address ); ?></code></td>
							<td>
								<?php if ( '' === $item->consent_text_hash ) : ?>
									<span class="tccl-status tccl-status-na"><?php esc_html_e( 'unsealed', 'terms-conditions-consent-log' ); ?></span>
								<?php elseif ( $is_intact ) : ?>
									<span class="tccl-status tccl-status-ok" title="<?php echo esc_attr( substr( $item->consent_text_hash, 0, 16 ) ); ?>…">✓</span>
								<?php else : ?>
									<span class="tccl-status tccl-status-tampered" title="<?php esc_attr_e( 'Stored text does not match its sealed hash', 'terms-conditions-consent-log' ); ?>"><?php esc_html_e( 'TAMPERED', 'terms-conditions-consent-log' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="tccl-text-cell">
								<details>
									<summary><?php echo esc_html( wp_html_excerpt( wp_strip_all_tags( $item->consent_text ), 80, '…' ) ); ?></summary>
									<div class="tccl-text-full">
										<div class="tccl-text-html"><?php echo wp_kses_post( $item->consent_text ); ?></div>
										<?php if ( '' !== $item->consent_text_hash ) : ?>
											<p class="tccl-hash">SHA-256: <code><?php echo esc_html( $item->consent_text_hash ); ?></code></p>
										<?php endif; ?>
									</div>
								</details>
							</td>
							<td class="tccl-actions-col">
								<a href="<?php echo esc_url( tccl_certificate_download_url( (int) $item->id ) ); ?>" class="button button-small" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Download PDF certificate', 'terms-conditions-consent-log' ); ?>">PDF</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php tccl_render_pagination_nav( $paged, $total, $total_pages, 'bottom' ); ?>
	<?php
}

/**
 * Renders a WP-list-table style pagination nav (top or bottom of the table).
 *
 * @param int    $paged       Current page (1-based).
 * @param int    $total       Total record count for the current filter.
 * @param int    $total_pages Total page count.
 * @param string $position    'top' or 'bottom'.
 */
function tccl_render_pagination_nav( $paged, $total, $total_pages, $position ) {
	$paged       = max( 1, (int) $paged );
	$total       = (int) $total;
	$total_pages = max( 0, (int) $total_pages );

	$first_disabled = $paged <= 1;
	$prev_disabled  = $paged <= 1;
	$next_disabled  = $paged >= $total_pages;
	$last_disabled  = $paged >= $total_pages;

	$first_url = '#' . ( $first_disabled ? '' : '1' );
	$prev_url  = '#' . ( $prev_disabled ? '' : ( $paged - 1 ) );
	$next_url  = '#' . ( $next_disabled ? '' : ( $paged + 1 ) );
	$last_url  = '#' . ( $last_disabled ? '' : $total_pages );
	?>
	<div class="tablenav <?php echo esc_attr( $position ); ?> tccl-tablenav">
		<div class="tablenav-pages<?php echo $total_pages <= 1 ? ' one-page' : ''; ?>">
			<span class="displaying-num">
				<?php
				printf(
					esc_html(
						/* translators: %s: number of records (formatted). */
						_n( '%s item', '%s items', $total, 'terms-conditions-consent-log' )
					),
					esc_html( number_format_i18n( $total ) )
				);
				?>
			</span>
			<?php if ( $total_pages > 1 ) : ?>
				<span class="pagination-links">
					<?php if ( $first_disabled ) : ?>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
					<?php else : ?>
						<a class="first-page button" href="<?php echo esc_url( $first_url ); ?>" data-paged="1">
							<span class="screen-reader-text"><?php esc_html_e( 'First page', 'terms-conditions-consent-log' ); ?></span>
							<span aria-hidden="true">«</span>
						</a>
					<?php endif; ?>

					<?php if ( $prev_disabled ) : ?>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
					<?php else : ?>
						<a class="prev-page button" href="<?php echo esc_url( $prev_url ); ?>" data-paged="<?php echo absint( $paged - 1 ); ?>">
							<span class="screen-reader-text"><?php esc_html_e( 'Previous page', 'terms-conditions-consent-log' ); ?></span>
							<span aria-hidden="true">‹</span>
						</a>
					<?php endif; ?>

					<span class="paging-input">
						<label for="tccl-current-page-<?php echo esc_attr( $position ); ?>" class="screen-reader-text"><?php esc_html_e( 'Current page', 'terms-conditions-consent-log' ); ?></label>
						<input class="current-page tccl-current-page" id="tccl-current-page-<?php echo esc_attr( $position ); ?>" type="text" inputmode="numeric" pattern="[0-9]*" name="paged" value="<?php echo absint( $paged ); ?>" size="<?php echo esc_attr( max( 1, strlen( (string) $total_pages ) ) ); ?>" aria-describedby="tccl-table-paging-<?php echo esc_attr( $position ); ?>">
						<span class="tablenav-paging-text">
							<?php esc_html_e( 'of', 'terms-conditions-consent-log' ); ?>
							<span class="total-pages" id="tccl-table-paging-<?php echo esc_attr( $position ); ?>"><?php echo absint( $total_pages ); ?></span>
						</span>
					</span>

					<?php if ( $next_disabled ) : ?>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
					<?php else : ?>
						<a class="next-page button" href="<?php echo esc_url( $next_url ); ?>" data-paged="<?php echo absint( $paged + 1 ); ?>">
							<span class="screen-reader-text"><?php esc_html_e( 'Next page', 'terms-conditions-consent-log' ); ?></span>
							<span aria-hidden="true">›</span>
						</a>
					<?php endif; ?>

					<?php if ( $last_disabled ) : ?>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
					<?php else : ?>
						<a class="last-page button" href="<?php echo esc_url( $last_url ); ?>" data-paged="<?php echo absint( $total_pages ); ?>">
							<span class="screen-reader-text"><?php esc_html_e( 'Last page', 'terms-conditions-consent-log' ); ?></span>
							<span aria-hidden="true">»</span>
						</a>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Renders the Settings tab.
 */
function tccl_render_settings_tab() {
	$settings = tccl_get_all_settings();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only notices set after a redirect we triggered ourselves.
	if ( isset( $_GET['updated'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'terms-conditions-consent-log' ) . '</p></div>';
	}
	if ( isset( $_GET['anonymised'] ) ) {
		$count = absint( $_GET['anonymised'] );
		echo '<div class="notice notice-success is-dismissible"><p>';
		printf(
			esc_html(
				/* translators: %d: number of anonymised records. */
				_n( '%d record anonymised.', '%d records anonymised.', $count, 'terms-conditions-consent-log' )
			),
			absint( $count )
		);
		echo '</p></div>';
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	?>
	<form method="post" action="">
		<?php wp_nonce_field( 'tccl_save_settings' ); ?>

		<h2><?php esc_html_e( 'Checkout texts', 'terms-conditions-consent-log' ); ?></h2>
		<p class="description tccl-section-intro">
			<?php esc_html_e( 'Both fields are optional. Whatever the customer is actually shown is what gets stored with each record.', 'terms-conditions-consent-log' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="tccl_terms_text"><?php esc_html_e( 'Terms checkbox text', 'terms-conditions-consent-log' ); ?></label></th>
				<td>
					<textarea id="tccl_terms_text" name="terms_text" rows="3" cols="60" class="large-text" placeholder="<?php echo esc_attr( tccl_get_terms_text_placeholder_hint() ); ?>"><?php echo esc_textarea( $settings['terms_text'] ); ?></textarea>
					<p class="description">
						<strong><?php esc_html_e( 'Optional', 'terms-conditions-consent-log' ); ?>.</strong>
						<?php esc_html_e( 'Replaces the text next to the terms checkbox in the checkout. Leave empty to use the WooCommerce native text (shown as the placeholder above). Basic HTML is allowed (links, strong, em).', 'terms-conditions-consent-log' ); ?>
						<br>
						<?php esc_html_e( 'GDPR requires explicit consent — the customer must actively check the box. The plugin enforces nothing here; WooCommerce already does.', 'terms-conditions-consent-log' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="tccl_pre_checkout_text"><?php esc_html_e( 'Pre-checkout informational text', 'terms-conditions-consent-log' ); ?></label></th>
				<td>
					<textarea id="tccl_pre_checkout_text" name="pre_checkout_text" rows="4" cols="60" class="large-text" placeholder="<?php esc_attr_e( 'Example: We are the data controller for the data you provide here. Purpose: process your order. Recipients: shipping and payment providers. You can access, rectify and delete your data, more info in our privacy policy.', 'terms-conditions-consent-log' ); ?>"><?php echo esc_textarea( $settings['pre_checkout_text'] ); ?></textarea>
					<p class="description">
						<strong><?php esc_html_e( 'Optional, but legally relevant', 'terms-conditions-consent-log' ); ?>.</strong>
						<?php esc_html_e( 'GDPR article 13 requires you to inform the data subject about the controller, the purpose, the legal basis, the recipients and their rights, before consent is collected. If your site does not show that information at checkout via another route, fill this field. If your privacy policy or another notice already does, you can leave it empty.', 'terms-conditions-consent-log' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Document version', 'terms-conditions-consent-log' ); ?></h2>
		<p class="description tccl-section-intro">
			<?php esc_html_e( 'A label for the current wording of your terms. Older records keep the version they signed and are never modified, so you can always prove which text each customer accepted. Records on a previous version show as "Outdated" — that is informative, not an error.', 'terms-conditions-consent-log' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="tccl_consent_version"><?php esc_html_e( 'Current version', 'terms-conditions-consent-log' ); ?></label></th>
				<td>
					<input type="text" id="tccl_consent_version" name="consent_version" value="<?php echo esc_attr( $settings['consent_version'] ); ?>" class="regular-text">
					<p class="description">
						<?php esc_html_e( 'Suggested format: MAJOR.MINOR-YYYY-MM-DD (e.g. 1.2-2026-05-09). What matters is that the string changes whenever your text changes.', 'terms-conditions-consent-log' ); ?>
					</p>
					<p>
						<label>
							<input type="checkbox" name="tccl_bump_version" value="1">
							<?php
							printf(
								/* translators: %s: suggested next version. */
								esc_html__( 'Bump version on save (suggestion: %s).', 'terms-conditions-consent-log' ),
								esc_html( tccl_suggest_next_version( $settings['consent_version'] ) )
							);
							?>
						</label>
					</p>
					<p class="description">
						<?php esc_html_e( 'You usually do not need to touch this field. If you change the terms text and forget to bump the version, the plugin bumps it automatically when you save.', 'terms-conditions-consent-log' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Tracking and retention', 'terms-conditions-consent-log' ); ?></h2>
		<p class="description tccl-section-intro">
			<?php esc_html_e( 'IP and user-agent are not required by GDPR, but they reinforce the credibility of each record.', 'terms-conditions-consent-log' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'IP address', 'terms-conditions-consent-log' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="track_ip" value="1" <?php checked( 1, (int) $settings['track_ip'] ); ?>>
						<?php esc_html_e( 'Record the client IP address', 'terms-conditions-consent-log' ); ?>
					</label>
					<p class="description">
						<strong><?php esc_html_e( 'Recommended', 'terms-conditions-consent-log' ); ?>.</strong>
						<?php esc_html_e( 'Read from REMOTE_ADDR. Forwarded headers are not trusted (they can be spoofed without a verified reverse proxy). Disable only if your privacy policy says you do not log IPs.', 'terms-conditions-consent-log' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'User agent', 'terms-conditions-consent-log' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="track_user_agent" value="1" <?php checked( 1, (int) $settings['track_user_agent'] ); ?>>
						<?php esc_html_e( 'Record the browser user agent', 'terms-conditions-consent-log' ); ?>
					</label>
					<p class="description">
						<strong><?php esc_html_e( 'Recommended', 'terms-conditions-consent-log' ); ?>.</strong>
						<?php esc_html_e( 'Reinforces evidence (helps spot automated submissions). Disable if your privacy policy says you do not log it.', 'terms-conditions-consent-log' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="tccl_retention_days"><?php esc_html_e( 'Retention (days)', 'terms-conditions-consent-log' ); ?></label></th>
				<td>
					<input type="number" id="tccl_retention_days" name="retention_days" min="0" max="3650" step="1" value="<?php echo esc_attr( $settings['retention_days'] ); ?>" class="small-text">
					<p class="description">
						<strong><?php esc_html_e( 'GDPR requires you to define a retention period', 'terms-conditions-consent-log' ); ?>.</strong>
						<?php esc_html_e( 'After this many days, records are eligible for anonymisation (PII removed but the record is kept as proof of consent). 0 = indefinite — only acceptable if you can justify it (e.g. statute of limitations for civil claims, typically 5 years in Spain).', 'terms-conditions-consent-log' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Anonymise old records', 'terms-conditions-consent-log' ); ?></h2>
		<p class="description tccl-section-intro">
			<?php esc_html_e( 'Removes PII (email, IP, user-agent) from records older than the retention period. The records themselves are kept as proof of consent.', 'terms-conditions-consent-log' ); ?>
		</p>
		<?php
		$retention = (int) tccl_get_setting( 'retention_days', 0 );
		$disabled  = $retention <= 0;
		?>
		<p>
			<input type="hidden" name="_tccl_anonymise_nonce" value="<?php echo esc_attr( wp_create_nonce( 'tccl_anonymise' ) ); ?>">
			<button type="submit" name="tccl_anonymise_now" value="1" class="button" <?php disabled( $disabled, true ); ?> formnovalidate onclick="return confirm('<?php echo esc_js( __( 'This will anonymise all records older than the retention period. Continue?', 'terms-conditions-consent-log' ) ); ?>');">
				<?php esc_html_e( 'Anonymise now', 'terms-conditions-consent-log' ); ?>
			</button>
			<?php if ( $disabled ) : ?>
				<span class="description"><?php esc_html_e( 'Set a retention period above to enable this action.', 'terms-conditions-consent-log' ); ?></span>
			<?php endif; ?>
		</p>

		<h2><?php esc_html_e( 'Order emails', 'terms-conditions-consent-log' ); ?></h2>
		<p class="description tccl-section-intro">
			<?php esc_html_e( 'Off by default. Not required by GDPR — the record itself is the legal evidence.', 'terms-conditions-consent-log' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Admin email', 'terms-conditions-consent-log' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="email_admin_show_consent" value="1" <?php checked( 1, (int) $settings['email_admin_show_consent'] ); ?>>
						<?php esc_html_e( 'Show a consent line in the New order email (admin)', 'terms-conditions-consent-log' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Useful so the shop manager sees ✓ Terms accepted · version · IP at a glance, without opening each order. Recommended for shops with several people processing orders.', 'terms-conditions-consent-log' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Customer email', 'terms-conditions-consent-log' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="email_customer_show_consent" value="1" <?php checked( 1, (int) $settings['email_customer_show_consent'] ); ?>>
						<?php esc_html_e( 'Show a consent line in the order confirmation email (customer)', 'terms-conditions-consent-log' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Reinforces transparency toward the customer. Some customers expect a confirmation that their acceptance was recorded; others may find it unusual. Off by default; turn it on if it fits your brand.', 'terms-conditions-consent-log' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Uninstall', 'terms-conditions-consent-log' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'On uninstall', 'terms-conditions-consent-log' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( 1, (int) $settings['delete_data_on_uninstall'] ); ?>>
						<?php esc_html_e( 'Delete all plugin data when the plugin is uninstalled', 'terms-conditions-consent-log' ); ?>
					</label>
					<div class="notice notice-warning inline tccl-uninstall-warning">
						<p>
							<strong><?php esc_html_e( 'This destroys consent evidence.', 'terms-conditions-consent-log' ); ?></strong>
							<?php esc_html_e( 'If this option is enabled, uninstalling the plugin will permanently drop the consent table and remove all settings. Only deactivating the plugin does NOT trigger this — uninstall does.', 'terms-conditions-consent-log' ); ?>
						</p>
						<p>
							<?php esc_html_e( 'Strongly recommended: export the full log to CSV before uninstalling.', 'terms-conditions-consent-log' ); ?>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=tccl-consents&action=tccl_export_csv' ), 'tccl_export_csv' ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Export now', 'terms-conditions-consent-log' ); ?>
							</a>
						</p>
					</div>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" name="tccl_save_settings" value="1" class="button button-primary">
				<?php esc_html_e( 'Save settings', 'terms-conditions-consent-log' ); ?>
			</button>
		</p>
	</form>

	<?php
}
