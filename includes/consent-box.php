<?php
/**
 * Stand-alone consent box: shortcode + Gutenberg block + REST endpoint.
 *
 * Lets the admin drop a self-contained consent checkbox anywhere in the
 * site (page, post, widget area, HTML field of a form builder…) without
 * touching code. Each acceptance is recorded via the same public
 * `tccl_save_consent()` API as every other source.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the [tccl_consent_box] shortcode.
 *
 * Accepted attributes:
 * - text:             HTML/text shown beside the checkbox. Defaults to the inner content
 *                     of the shortcode, then to a generic privacy line.
 * - consent_type:     Slug stored with each record. Default `consent_box`.
 * - consent_version:  Version label. Defaults to the global plugin setting.
 * - submit_label:     Submit button label. Default "Accept".
 * - success:          Message shown after a successful POST.
 * - require_email:    `auto` (default), `yes` or `no`. `auto` requires email only when
 *                     the visitor is not logged in.
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $content Inner content (used as the consent text when `text` is empty).
 * @return string Rendered HTML.
 */
function tccl_consent_box_shortcode( $atts, $content = '' ) {
	$atts = shortcode_atts(
		array(
			'text'            => '',
			'consent_type'    => 'consent_box',
			'consent_version' => '',
			'submit_label'    => __( 'Accept', 'terms-conditions-consent-log' ),
			'success'         => __( 'Thank you, your acceptance has been recorded.', 'terms-conditions-consent-log' ),
			'require_email'   => 'auto',
		),
		$atts,
		'tccl_consent_box'
	);

	$text = '' !== trim( (string) $atts['text'] ) ? (string) $atts['text'] : do_shortcode( (string) $content );
	if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
		$default = (string) tccl_get_setting( 'consent_box_default_text', '' );
		$text    = '' !== trim( wp_strip_all_tags( $default ) )
			? $default
			: __( 'I have read and agree to the privacy policy.', 'terms-conditions-consent-log' );
	}

	$version = '' !== trim( (string) $atts['consent_version'] )
		? (string) $atts['consent_version']
		: (string) tccl_get_setting( 'consent_version', '1.0' );

	$require_email = strtolower( (string) $atts['require_email'] );
	$needs_email   = 'yes' === $require_email
		|| ( 'no' !== $require_email && ! is_user_logged_in() );

	wp_enqueue_script(
		'tccl-consent-box',
		TCCL_PLUGIN_URL . 'assets/js/consent-box.js',
		array(),
		TCCL_VERSION,
		true
	);
	wp_enqueue_style(
		'tccl-consent-box',
		TCCL_PLUGIN_URL . 'assets/css/consent-box.css',
		array(),
		TCCL_VERSION
	);

	$rest_url = rest_url( 'tccl/v1/consent' );
	$nonce    = wp_create_nonce( 'wp_rest' );

	ob_start();
	?>
	<form
		class="tccl-consent-box"
		data-rest="<?php echo esc_attr( $rest_url ); ?>"
		data-nonce="<?php echo esc_attr( $nonce ); ?>"
		data-type="<?php echo esc_attr( (string) $atts['consent_type'] ); ?>"
		data-version="<?php echo esc_attr( $version ); ?>"
		data-success="<?php echo esc_attr( (string) $atts['success'] ); ?>"
	>
		<label class="tccl-consent-box__row">
			<input type="checkbox" class="tccl-consent-box__checkbox" required>
			<span class="tccl-consent-box__text"><?php echo wp_kses_post( $text ); ?></span>
		</label>
		<?php if ( $needs_email ) : ?>
			<label class="tccl-consent-box__row tccl-consent-box__email">
				<span><?php esc_html_e( 'Your email', 'terms-conditions-consent-log' ); ?></span>
				<input type="email" name="email" required autocomplete="email">
			</label>
		<?php endif; ?>
		<button type="submit" class="tccl-consent-box__submit button">
			<?php echo esc_html( (string) $atts['submit_label'] ); ?>
		</button>
		<p class="tccl-consent-box__feedback" role="status" aria-live="polite"></p>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'tccl_consent_box', 'tccl_consent_box_shortcode' );

/**
 * Registers the REST endpoint that backs the shortcode/block.
 */
function tccl_consent_box_register_rest() {
	register_rest_route(
		'tccl/v1',
		'/consent',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'tccl_consent_box_rest_save',
			'args'                => array(
				'consent_type'    => array(
					'type'     => 'string',
					'required' => true,
				),
				'consent_version' => array(
					'type' => 'string',
				),
				'consent_text'    => array(
					'type'     => 'string',
					'required' => true,
				),
				'email'           => array(
					'type' => 'string',
				),
				'source_url'      => array(
					'type' => 'string',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'tccl_consent_box_register_rest' );

/**
 * Saves a consent submitted from the front-end shortcode/block.
 *
 * Public endpoint protected by the `wp_rest` nonce that the front-end JS
 * sends in the `X-WP-Nonce` header. This is the same protection model used
 * by every WordPress core REST request from a logged-out visitor.
 *
 * @param WP_REST_Request $request Incoming request.
 * @return WP_REST_Response|WP_Error
 */
function tccl_consent_box_rest_save( WP_REST_Request $request ) {
	$nonce = (string) $request->get_header( 'x-wp-nonce' );
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error(
			'tccl_invalid_nonce',
			__( 'Invalid nonce.', 'terms-conditions-consent-log' ),
			array( 'status' => 403 )
		);
	}

	$user  = wp_get_current_user();
	$email = sanitize_email( (string) $request->get_param( 'email' ) );
	if ( '' === $email && $user instanceof WP_User && $user->ID ) {
		$email = (string) $user->user_email;
	}
	if ( '' === $email ) {
		return new WP_Error(
			'tccl_missing_email',
			__( 'Email is required.', 'terms-conditions-consent-log' ),
			array( 'status' => 400 )
		);
	}

	$source_url = esc_url_raw( (string) $request->get_param( 'source_url' ) );
	if ( '' === $source_url ) {
		$referer = (string) $request->get_header( 'referer' );
		if ( '' !== $referer ) {
			$source_url = esc_url_raw( $referer );
		}
	}

	$id = tccl_save_consent(
		array(
			'user_id'         => ( $user instanceof WP_User ) ? (int) $user->ID : 0,
			'email'           => $email,
			'consent_type'    => sanitize_text_field( (string) $request->get_param( 'consent_type' ) ),
			'consent_version' => sanitize_text_field( (string) $request->get_param( 'consent_version' ) ),
			'consent_text'    => wp_kses_post( (string) $request->get_param( 'consent_text' ) ),
			'consent_value'   => 1,
			'source_url'      => $source_url,
		)
	);

	if ( ! $id ) {
		return new WP_Error(
			'tccl_save_failed',
			__( 'Could not save consent.', 'terms-conditions-consent-log' ),
			array( 'status' => 500 )
		);
	}

	return rest_ensure_response(
		array(
			'ok' => true,
			'id' => (int) $id,
		)
	);
}

/**
 * Registers the Gutenberg block editor script with its WordPress
 * dependencies. We register it by handle (`tccl-consent-box-editor`)
 * before `register_block_type()` so block.json can reference it without
 * having to ship a build-time generated `edit.asset.php`.
 */
function tccl_consent_box_register_block_assets() {
	$rel_path = 'assets/blocks/consent-box/edit.js';
	$abs_path = TCCL_PLUGIN_DIR . $rel_path;
	if ( ! file_exists( $abs_path ) ) {
		return;
	}
	wp_register_script(
		'tccl-consent-box-editor',
		TCCL_PLUGIN_URL . $rel_path,
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
		TCCL_VERSION,
		true
	);
}
add_action( 'init', 'tccl_consent_box_register_block_assets', 5 );

/**
 * Registers the Gutenberg block. The block reuses the shortcode renderer
 * via `render_callback`, so editor and front-end stay in sync.
 */
function tccl_consent_box_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	$block_dir = TCCL_PLUGIN_DIR . 'assets/blocks/consent-box';
	if ( ! file_exists( $block_dir . '/block.json' ) ) {
		return;
	}
	register_block_type(
		$block_dir,
		array(
			'render_callback' => 'tccl_consent_box_block_render',
		)
	);
}
add_action( 'init', 'tccl_consent_box_register_block' );

/**
 * Renders the block by delegating to the shortcode handler.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function tccl_consent_box_block_render( $attributes ) {
	$atts = array(
		'text'            => isset( $attributes['text'] ) ? (string) $attributes['text'] : '',
		'consent_type'    => isset( $attributes['consentType'] ) ? (string) $attributes['consentType'] : 'consent_box',
		'consent_version' => isset( $attributes['consentVersion'] ) ? (string) $attributes['consentVersion'] : '',
		'submit_label'    => isset( $attributes['submitLabel'] ) ? (string) $attributes['submitLabel'] : '',
		'success'         => isset( $attributes['successMessage'] ) ? (string) $attributes['successMessage'] : '',
		'require_email'   => isset( $attributes['requireEmail'] ) ? (string) $attributes['requireEmail'] : 'auto',
	);
	// Strip empty values so shortcode_atts can fall back to its defaults.
	$atts = array_filter(
		$atts,
		static function ( $v ) {
			return '' !== $v;
		}
	);
	return tccl_consent_box_shortcode( $atts );
}
