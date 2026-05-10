<?php
/**
 * Order edit screen metabox showing the consent summary.
 * Compatible with both legacy and HPOS order screens.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the metabox on both legacy and HPOS order screens.
 */
function tccl_register_order_metabox() {
	$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );
	foreach ( $screens as $screen ) {
		add_meta_box(
			'tccl_consent_box',
			esc_html__( 'Consent record', 'terms-conditions-consent-log' ),
			'tccl_render_order_metabox',
			$screen,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'tccl_register_order_metabox' );

/**
 * Renders the metabox.
 *
 * @param WP_Post|WC_Order $post_or_order Post on legacy, order on HPOS.
 */
function tccl_render_order_metabox( $post_or_order ) {
	$order = ( $post_or_order instanceof WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
	if ( ! $order ) {
		echo '<p>—</p>';
		return;
	}

	$accepted    = (int) $order->get_meta( '_tccl_terms_accepted' );
	$version     = $order->get_meta( '_tccl_terms_version' );
	$recorded_at = $order->get_meta( '_tccl_recorded_at' );
	$current     = (string) tccl_get_setting( 'consent_version', '' );
	$is_outdated = ! empty( $version ) && '' !== $current && $version !== $current;

	$record = tccl_get_record_by_order( $order->get_id() );
	?>
	<p>
		<strong><?php esc_html_e( 'Terms &amp; privacy:', 'terms-conditions-consent-log' ); ?></strong>
		<?php if ( $accepted ) : ?>
			<span class="tccl-status tccl-status-ok">✓ <?php esc_html_e( 'Accepted', 'terms-conditions-consent-log' ); ?></span>
		<?php else : ?>
			<span class="tccl-status tccl-status-no">✗ <?php esc_html_e( 'Not accepted', 'terms-conditions-consent-log' ); ?></span>
		<?php endif; ?>
	</p>

	<dl class="tccl-meta-list">
		<dt><?php esc_html_e( 'Version', 'terms-conditions-consent-log' ); ?></dt>
		<dd>
			<code><?php echo esc_html( $version ? $version : '—' ); ?></code>
			<?php if ( $is_outdated ) : ?>
				<span class="tccl-status tccl-status-outdated"><?php esc_html_e( 'Outdated', 'terms-conditions-consent-log' ); ?></span>
			<?php endif; ?>
		</dd>
		<?php if ( $recorded_at ) : ?>
			<dt><?php esc_html_e( 'Recorded at', 'terms-conditions-consent-log' ); ?></dt>
			<dd><code><?php echo esc_html( $recorded_at ); ?></code> UTC</dd>
		<?php endif; ?>
		<?php if ( $record ) : ?>
			<dt><?php esc_html_e( 'Integrity', 'terms-conditions-consent-log' ); ?></dt>
			<dd>
				<?php if ( '' === $record->consent_text_hash ) : ?>
					<span class="tccl-status tccl-status-na"><?php esc_html_e( 'Unsealed', 'terms-conditions-consent-log' ); ?></span>
				<?php elseif ( tccl_record_is_intact( $record ) ) : ?>
					<span class="tccl-status tccl-status-ok" title="<?php echo esc_attr( $record->consent_text_hash ); ?>">✓ <?php esc_html_e( 'Verified', 'terms-conditions-consent-log' ); ?></span>
				<?php else : ?>
					<span class="tccl-status tccl-status-tampered">✗ <?php esc_html_e( 'TAMPERED', 'terms-conditions-consent-log' ); ?></span>
				<?php endif; ?>
			</dd>
		<?php endif; ?>
	</dl>

	<?php if ( $record && '' !== $record->consent_text ) : ?>
		<details class="tccl-details">
			<summary><?php esc_html_e( 'View accepted text', 'terms-conditions-consent-log' ); ?></summary>
			<div class="tccl-text-preview"><?php echo wp_kses_post( $record->consent_text ); ?></div>
		</details>
	<?php endif; ?>

	<p class="tccl-metabox-actions">
		<a href="<?php echo esc_url(
			add_query_arg(
				array(
					'page'     => 'tccl-consents',
					'order_id' => $order->get_id(),
				),
				admin_url( 'admin.php' )
			)
		); ?>" class="button button-small" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'View record', 'terms-conditions-consent-log' ); ?>
		</a>
		<?php if ( $record ) : ?>
			<a href="<?php echo esc_url( tccl_certificate_download_url( (int) $record->id ) ); ?>" class="button button-small" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Download PDF', 'terms-conditions-consent-log' ); ?>
			</a>
		<?php endif; ?>
	</p>
	<?php
}

/**
 * Adds a Consent column to the orders list (legacy and HPOS).
 *
 * @param array $columns Existing columns.
 * @return array
 */
function tccl_add_orders_column( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'order_status' === $key ) {
			$new['tccl_consent'] = esc_html__( 'Consent', 'terms-conditions-consent-log' );
		}
	}
	return $new;
}
add_filter( 'manage_edit-shop_order_columns', 'tccl_add_orders_column' );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'tccl_add_orders_column' );

/**
 * Renders the Consent column on legacy orders list.
 *
 * @param string $column Column key.
 */
function tccl_render_orders_column_legacy( $column ) {
	if ( 'tccl_consent' !== $column ) {
		return;
	}
	global $post;
	$order = wc_get_order( $post->ID );
	if ( $order ) {
		echo wp_kses_post( tccl_get_orders_column_html( $order ) );
	}
}
add_action( 'manage_shop_order_posts_custom_column', 'tccl_render_orders_column_legacy' );

/**
 * Renders the Consent column on HPOS orders list.
 *
 * @param string                     $column Column key.
 * @param WC_Order|WC_Abstract_Order $order  Order.
 */
function tccl_render_orders_column_hpos( $column, $order ) {
	if ( 'tccl_consent' !== $column ) {
		return;
	}
	echo wp_kses_post( tccl_get_orders_column_html( $order ) );
}
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'tccl_render_orders_column_hpos', 10, 2 );

/**
 * HTML for the Consent column.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function tccl_get_orders_column_html( $order ) {
	$accepted = (int) $order->get_meta( '_tccl_terms_accepted' );
	$version  = $order->get_meta( '_tccl_terms_version' );

	if ( '' === $version ) {
		return '<span class="tccl-status tccl-status-na" title="' . esc_attr__( 'No data (placed before plugin activation)', 'terms-conditions-consent-log' ) . '">—</span>';
	}

	$current     = (string) tccl_get_setting( 'consent_version', '' );
	$is_outdated = '' !== $current && $version !== $current;

	$record_url = add_query_arg(
		array(
			'page'     => 'tccl-consents',
			'order_id' => $order->get_id(),
		),
		admin_url( 'admin.php' )
	);
	$link_open  = '<a href="' . esc_url( $record_url ) . '" target="_blank" rel="noopener noreferrer" class="tccl-consent-link">';
	$link_close = '</a>';

	if ( $accepted ) {
		$class = $is_outdated ? 'tccl-status tccl-status-outdated' : 'tccl-status tccl-status-ok';
		$icon  = $is_outdated ? '⚠' : '✓';
		return $link_open . '<span class="' . esc_attr( $class ) . '" title="' . esc_attr( $version ) . '">' . esc_html( $icon ) . '</span>' . $link_close;
	}
	return $link_open . '<span class="tccl-status tccl-status-no">✗</span>' . $link_close;
}
