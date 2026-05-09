<?php
/**
 * AyudaWP cross-promotion banner.
 *
 * Renders two random AyudaWP plugins and one random AyudaWP service at the
 * bottom of an admin page. The host plugin is excluded from the rotation.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'TCCL_Promo_Banner', false ) ) {
	return;
}

/**
 * Cross-promotion banner.
 */
class TCCL_Promo_Banner {

	/**
	 * Slug of the host plugin (excluded from rotation).
	 *
	 * @var string
	 */
	private $current_plugin_slug;

	/**
	 * CSS class prefix.
	 *
	 * @var string
	 */
	private $css_prefix;

	/**
	 * Constructor.
	 *
	 * @param string $current_plugin_slug WordPress.org slug of the host plugin.
	 * @param string $css_prefix          CSS class prefix used on the rendered elements.
	 */
	public function __construct( $current_plugin_slug, $css_prefix ) {
		$this->current_plugin_slug = $current_plugin_slug;
		$this->css_prefix          = $css_prefix;
	}

	/**
	 * Plugins catalog.
	 *
	 * @return array
	 */
	private function get_plugins_catalog() {
		return array(
			'vigilante'                                                => array(
				'icon'        => 'dashicons-shield',
				'title'       => __( 'Complete WordPress security', 'terms-conditions-consent-log' ),
				'description' => __( 'All-in-one security plugin: firewall, login protection, security headers, 2FA, file integrity monitoring, and activity logging.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Vigilante', 'terms-conditions-consent-log' ),
			),
			'gozer'                                                    => array(
				'icon'        => 'dashicons-admin-network',
				'title'       => __( 'Restrict site access', 'terms-conditions-consent-log' ),
				'description' => __( 'Force visitors to log in before accessing your site with extensive exception controls for pages, posts, and user roles.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Gozer', 'terms-conditions-consent-log' ),
			),
			'vigia'                                                    => array(
				'icon'        => 'dashicons-visibility',
				'title'       => __( 'Monitor AI crawler activity', 'terms-conditions-consent-log' ),
				'description' => __( 'Track which AI bots visit your site, analyze their behavior, and take control with blocking rules and robots.txt management.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install VigIA', 'terms-conditions-consent-log' ),
			),
			'ai-share-summarize'                                       => array(
				'icon'        => 'dashicons-share',
				'title'       => __( 'Boost your AI presence', 'terms-conditions-consent-log' ),
				'description' => __( 'Add social sharing and AI summarize buttons. Help visitors share your content and let AIs learn from your site while getting backlinks.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install AI Share & Summarize', 'terms-conditions-consent-log' ),
			),
			'ai-content-signals'                                       => array(
				'icon'        => 'dashicons-flag',
				'title'       => __( 'Control AI content usage', 'terms-conditions-consent-log' ),
				'description' => __( 'Cloudflare-endorsed plugin to define how AI systems can use your content: for training, search results, or both.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install AI Content Signals', 'terms-conditions-consent-log' ),
			),
			'wpo-tweaks'                                               => array(
				'icon'        => 'dashicons-performance',
				'title'       => __( 'Speed up your WordPress', 'terms-conditions-consent-log' ),
				'description' => __( 'Comprehensive performance optimizations: critical CSS, lazy loading, cache rules, and 30+ tweaks with zero configuration.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Zero Config Performance', 'terms-conditions-consent-log' ),
			),
			'no-gutenberg'                                             => array(
				'icon'        => 'dashicons-edit-page',
				'title'       => __( 'Back to Classic Editor', 'terms-conditions-consent-log' ),
				'description' => __( 'Completely remove Gutenberg, FSE styles, and block widgets. Restore the classic editing experience with better performance.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install No Gutenberg', 'terms-conditions-consent-log' ),
			),
			'anticache'                                                => array(
				'icon'        => 'dashicons-hammer',
				'title'       => __( 'Development toolkit', 'terms-conditions-consent-log' ),
				'description' => __( 'Bypass all caching during development. Auto-detects cache plugins, enables debug mode, and includes maintenance screen.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Anti-Cache Kit', 'terms-conditions-consent-log' ),
			),
			'auto-capitalize-names-ayudawp'                            => array(
				'icon'        => 'dashicons-editor-textcolor',
				'title'       => __( 'Fix customer names', 'terms-conditions-consent-log' ),
				'description' => __( 'Auto-capitalize names and addresses in WordPress and WooCommerce. Keep invoices and reports professionally formatted.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Auto Capitalize', 'terms-conditions-consent-log' ),
			),
			'easy-actions-scheduler-cleaner-ayudawp'                   => array(
				'icon'        => 'dashicons-database-remove',
				'title'       => __( 'Clean Action Scheduler', 'terms-conditions-consent-log' ),
				'description' => __( 'Remove millions of completed, failed, and old actions from WooCommerce Action Scheduler. Reduce database size instantly.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Scheduler Cleaner', 'terms-conditions-consent-log' ),
			),
			'native-seo-meta-tags'                                     => array(
				'icon'        => 'dashicons-embed-generic',
				'title'       => __( 'Automatic SEO meta tags', 'terms-conditions-consent-log' ),
				'description' => __( 'Title, meta description, Open Graph, Twitter Card and JSON-LD schema from native WordPress fields. No heavy SEO plugin needed.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Native SEO Meta Tags', 'terms-conditions-consent-log' ),
			),
			'native-sitemap-customizer'                                => array(
				'icon'        => 'dashicons-networking',
				'title'       => __( 'Customize your sitemap', 'terms-conditions-consent-log' ),
				'description' => __( 'Control WordPress native sitemap: exclude post types, taxonomies, specific posts, and authors. No bloat, just options.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Sitemap Customizer', 'terms-conditions-consent-log' ),
			),
			'noindexer'                                                => array(
				'icon'        => 'dashicons-editor-unlink',
				'title'       => __( 'Control search indexing', 'terms-conditions-consent-log' ),
				'description' => __( 'Tell search engines what not to index. Apply noindex per post, page, or entire post types with simple override controls.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install NoIndexer', 'terms-conditions-consent-log' ),
			),
			'post-visibility-control'                                  => array(
				'icon'        => 'dashicons-hidden',
				'title'       => __( 'Control post visibility', 'terms-conditions-consent-log' ),
				'description' => __( 'Hide posts from homepage, archives, feeds, or REST API while keeping them accessible via direct URL.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Post Visibility', 'terms-conditions-consent-log' ),
			),
			'widget-visibility-control'                                => array(
				'icon'        => 'dashicons-welcome-widgets-menus',
				'title'       => __( 'Smart widget display', 'terms-conditions-consent-log' ),
				'description' => __( 'Show or hide widgets based on pages, post types, categories, user roles, and more. Works with any theme.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Widget Visibility', 'terms-conditions-consent-log' ),
			),
			'search-replace-text-blocks'                               => array(
				'icon'        => 'dashicons-search',
				'title'       => __( 'Search & replace in blocks', 'terms-conditions-consent-log' ),
				'description' => __( 'Find and replace text across all your Gutenberg blocks. Bulk edit content without touching the database directly.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Search Replace Blocks', 'terms-conditions-consent-log' ),
			),
			'seo-read-more-buttons-ayudawp'                            => array(
				'icon'        => 'dashicons-admin-links',
				'title'       => __( 'Better read more links', 'terms-conditions-consent-log' ),
				'description' => __( 'Customize excerpt "read more" links with buttons, custom text, and nofollow option. Improve CTR and SEO.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install SEO Read More', 'terms-conditions-consent-log' ),
			),
			'show-only-lowest-prices-in-woocommerce-variable-products' => array(
				'icon'        => 'dashicons-tag',
				'title'       => __( 'Cleaner variable prices', 'terms-conditions-consent-log' ),
				'description' => __( 'Display only the lowest price for WooCommerce variable products instead of confusing price ranges.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Lowest Price', 'terms-conditions-consent-log' ),
			),
			'multiple-sale-prices-scheduler'                           => array(
				'icon'        => 'dashicons-calendar-alt',
				'title'       => __( 'Schedule sale prices', 'terms-conditions-consent-log' ),
				'description' => __( 'Set multiple future sale prices for WooCommerce products. Plan promotions in advance with start and end dates.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Sale Scheduler', 'terms-conditions-consent-log' ),
			),
			'easy-store-management-ayudawp'                            => array(
				'icon'        => 'dashicons-store',
				'title'       => __( 'Simplify store management', 'terms-conditions-consent-log' ),
				'description' => __( 'Clean up WordPress admin for Store Managers. Hide unnecessary menus, keep only orders, products, and customers, plus quick access shortcuts.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Easy Store', 'terms-conditions-consent-log' ),
			),
			'lightbox-images-for-divi'                                 => array(
				'icon'        => 'dashicons-format-gallery',
				'title'       => __( 'Lightbox for Divi', 'terms-conditions-consent-log' ),
				'description' => __( 'Add native lightbox functionality to Divi theme images. No jQuery, fast loading, fully customizable.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Divi Lightbox', 'terms-conditions-consent-log' ),
			),
			'scheduled-posts-showcase'                                 => array(
				'icon'        => 'dashicons-clock',
				'title'       => __( 'Show visitors what is coming up next', 'terms-conditions-consent-log' ),
				'description' => __( 'Display your scheduled and future posts on the frontend to gain and retain visits.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Scheduled Posts Showcase', 'terms-conditions-consent-log' ),
			),
			'periscopio'                                               => array(
				'icon'        => 'dashicons-rss',
				'title'       => __( 'Custom Dashboard News', 'terms-conditions-consent-log' ),
				'description' => __( 'Add your own custom feeds and links to the news and events dashboard widget and replace WordPress default one.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Install Periscope', 'terms-conditions-consent-log' ),
			),
		);
	}

	/**
	 * Services catalog.
	 *
	 * @return array
	 */
	private function get_services_catalog() {
		return array(
			'maintenance' => array(
				'icon'        => 'dashicons-admin-tools',
				'title'       => __( 'Need help with your website?', 'terms-conditions-consent-log' ),
				'description' => __( 'Professional WordPress maintenance: security monitoring, regular backups, performance optimization, and priority support.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Learn more', 'terms-conditions-consent-log' ),
				'url'         => 'https://mantenimiento.ayudawp.com',
			),
			'consultancy' => array(
				'icon'        => 'dashicons-businessman',
				'title'       => __( 'WordPress consultancy', 'terms-conditions-consent-log' ),
				'description' => __( 'One-on-one online sessions to solve your WordPress doubts, get expert advice, and make better decisions for your project.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Book a session', 'terms-conditions-consent-log' ),
				'url'         => 'https://servicios.ayudawp.com/producto/consultoria-online-wordpress/',
			),
			'hacked'      => array(
				'icon'        => 'dashicons-sos',
				'title'       => __( 'Hacked website?', 'terms-conditions-consent-log' ),
				'description' => __( 'Fast recovery service for compromised WordPress sites. We clean malware, fix vulnerabilities, and restore your site security.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Get help now', 'terms-conditions-consent-log' ),
				'url'         => 'https://servicios.ayudawp.com/producto/wordpress-hackeado/',
			),
			'development' => array(
				'icon'        => 'dashicons-editor-code',
				'title'       => __( 'Custom development', 'terms-conditions-consent-log' ),
				'description' => __( 'Need a custom plugin, theme modifications, or specific functionality? We build tailored WordPress solutions for your needs.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Request a quote', 'terms-conditions-consent-log' ),
				'url'         => 'https://servicios.ayudawp.com/producto/desarrollo-wordpress/',
			),
			'hosting'     => array(
				'icon'        => 'dashicons-cloud-saved',
				'title'       => __( 'Hosting built for WordPress', 'terms-conditions-consent-log' ),
				'description' => __( 'Google Cloud servers, automatic geo-located daily backups, and 24/7 expert support. Speed, security, and migration tools included.', 'terms-conditions-consent-log' ),
				'button'      => __( 'Learn more', 'terms-conditions-consent-log' ),
				/* translators: SiteGround affiliate URL. Change this URL in translations to use a localized landing page. */
				'url'         => __( 'https://stgrnd.co/telladowpbox', 'terms-conditions-consent-log' ),
			),
		);
	}

	/**
	 * Pick random plugins.
	 *
	 * @param int $count Amount to return.
	 * @return array
	 */
	private function get_random_plugins( $count = 2 ) {
		$plugins = $this->get_plugins_catalog();
		unset( $plugins[ $this->current_plugin_slug ] );

		if ( empty( $plugins ) ) {
			return array();
		}

		$random_keys = array_rand( $plugins, min( $count, count( $plugins ) ) );
		if ( ! is_array( $random_keys ) ) {
			$random_keys = array( $random_keys );
		}

		$result = array();
		foreach ( $random_keys as $key ) {
			$result[ $key ] = $plugins[ $key ];
		}
		return $result;
	}

	/**
	 * Pick a random service.
	 *
	 * @return array
	 */
	private function get_random_service() {
		$services   = $this->get_services_catalog();
		$random_key = array_rand( $services );
		return $services[ $random_key ];
	}

	/**
	 * Render the promo banner (3 columns).
	 */
	public function render() {
		$plugins = $this->get_random_plugins( 2 );
		$service = $this->get_random_service();
		$prefix  = $this->css_prefix;
		?>
		<div class="<?php echo esc_attr( $prefix ); ?>-promo-notice">
			<h4><?php esc_html_e( 'Starter kit for your site', 'terms-conditions-consent-log' ); ?></h4>
			<div class="<?php echo esc_attr( $prefix ); ?>-promo-columns">

				<?php foreach ( $plugins as $slug => $plugin ) : ?>
					<div class="<?php echo esc_attr( $prefix ); ?>-promo-column">
						<span class="dashicons <?php echo esc_attr( $plugin['icon'] ); ?>"></span>
						<h5><?php echo esc_html( $plugin['title'] ); ?></h5>
						<p><?php echo esc_html( $plugin['description'] ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $slug . '&TB_iframe=true&width=772&height=618' ) ); ?>" class="button thickbox">
							<?php echo esc_html( $plugin['button'] ); ?>
						</a>
					</div>
				<?php endforeach; ?>

				<div class="<?php echo esc_attr( $prefix ); ?>-promo-column">
					<span class="dashicons <?php echo esc_attr( $service['icon'] ); ?>"></span>
					<h5><?php echo esc_html( $service['title'] ); ?></h5>
					<p><?php echo esc_html( $service['description'] ); ?></p>
					<a href="<?php echo esc_url( $service['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
						<?php echo esc_html( $service['button'] ); ?>
					</a>
				</div>

			</div>
		</div>
		<?php
	}
}
