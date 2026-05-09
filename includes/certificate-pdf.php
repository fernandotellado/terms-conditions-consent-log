<?php
/**
 * Consent certificate (printable HTML).
 *
 * Renders a one-page A4 view of a single consent record. The admin uses the
 * browser's "Save as PDF" / "Print" dialog (CSS @media print) to produce the
 * final PDF. No external library required, no dependencies.
 *
 * The file name is `certificate-pdf.php` for historical reasons; the output
 * is HTML built for print.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL that triggers the certificate view for a record.
 *
 * @param int $record_id Record ID.
 * @return string
 */
function tccl_certificate_download_url( $record_id ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'page'   => 'tccl-consents',
				'action' => 'tccl_download_certificate',
				'id'     => (int) $record_id,
			),
			admin_url( 'admin.php' )
		),
		'tccl_download_certificate_' . (int) $record_id
	);
}

/**
 * Handles the certificate request and outputs the printable HTML page.
 */
function tccl_handle_certificate_download() {
	if ( ! isset( $_GET['page'], $_GET['action'] ) ) {
		return;
	}
	if ( 'tccl-consents' !== $_GET['page'] || 'tccl_download_certificate' !== $_GET['action'] ) {
		return;
	}
	if ( ! current_user_can( tccl_admin_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'terms-conditions-consent-log' ) );
	}

	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	if ( ! $id ) {
		wp_die( esc_html__( 'Missing record ID.', 'terms-conditions-consent-log' ) );
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'tccl_download_certificate_' . $id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'terms-conditions-consent-log' ) );
	}

	$record = tccl_get_record( $id );
	if ( ! $record ) {
		wp_die( esc_html__( 'Record not found.', 'terms-conditions-consent-log' ) );
	}

	tccl_render_certificate_html( $record );
	exit;
}
add_action( 'admin_init', 'tccl_handle_certificate_download' );

/**
 * Streams the printable HTML.
 *
 * @param object $record Consent log row.
 */
function tccl_render_certificate_html( $record ) {
	$site_name = (string) get_bloginfo( 'name' );
	$site_url  = (string) home_url( '/' );
	$generated = gmdate( 'Y-m-d H:i:s' );
	$intact    = tccl_record_is_intact( $record );

	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	?><!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="utf-8">
	<title><?php
		echo esc_html(
			sprintf(
				/* translators: %d: record ID. */
				__( 'Consent certificate #%d', 'terms-conditions-consent-log' ),
				(int) $record->id
			)
		);
	?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		* { box-sizing: border-box; }
		html, body { margin: 0; padding: 0; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
			background: #f4f4f4;
			color: #1d2327;
			font-size: 12pt;
			line-height: 1.5;
		}
		.page {
			width: 210mm;
			min-height: 297mm;
			margin: 24px auto;
			padding: 24mm 20mm;
			background: #fff;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}
		.toolbar {
			max-width: 210mm;
			margin: 0 auto 16px;
			text-align: right;
			padding: 0 8px;
		}
		.toolbar button, .toolbar a {
			font: inherit;
			padding: 8px 16px;
			margin-left: 8px;
			background: #2271b1;
			color: #fff;
			border: 0;
			border-radius: 3px;
			text-decoration: none;
			cursor: pointer;
		}
		.toolbar a.secondary {
			background: #50575e;
		}
		header.cert-header {
			border-bottom: 2px solid #1d2327;
			padding-bottom: 12px;
			margin-bottom: 24px;
		}
		header.cert-header .site-name {
			font-size: 16pt;
			font-weight: 700;
			margin: 0;
		}
		h1 {
			text-align: center;
			font-size: 20pt;
			margin: 8px 0 4px;
		}
		.subtitle {
			text-align: center;
			font-size: 10pt;
			color: #50575e;
			margin: 0 0 24px;
		}
		section {
			margin-bottom: 18px;
		}
		section h2 {
			font-size: 11pt;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: #50575e;
			margin: 0 0 8px;
			padding-bottom: 4px;
			border-bottom: 1px solid #dcdcde;
		}
		table.fields {
			width: 100%;
			border-collapse: collapse;
		}
		table.fields th, table.fields td {
			padding: 6px 10px;
			text-align: left;
			border: 1px solid #dcdcde;
			vertical-align: top;
			font-size: 10.5pt;
		}
		table.fields th {
			background: #f6f7f7;
			width: 30%;
			font-weight: 600;
		}
		.accepted-text {
			padding: 14px 16px;
			background: #fafafa;
			border: 1px solid #dcdcde;
			border-radius: 3px;
			white-space: pre-wrap;
			font-size: 11pt;
			line-height: 1.6;
			word-break: break-word;
		}
		.seal {
			padding: 12px 14px;
			background: #fafafa;
			border: 1px solid #dcdcde;
			border-radius: 3px;
		}
		.seal code {
			display: block;
			font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
			font-size: 9.5pt;
			word-break: break-all;
			line-height: 1.4;
		}
		.seal .status-ok {
			color: #1a7f37;
			font-weight: 700;
		}
		.seal .status-tampered {
			color: #b30000;
			font-weight: 700;
		}
		.seal .status-na {
			color: #777;
			font-weight: 600;
		}
		.seal p {
			margin: 6px 0 0;
			font-size: 10pt;
			color: #50575e;
			font-style: italic;
		}
		footer.cert-footer {
			margin-top: 28px;
			padding-top: 12px;
			border-top: 1px solid #dcdcde;
			font-size: 9pt;
			color: #50575e;
			text-align: center;
		}

		@page {
			size: A4;
			margin: 18mm 16mm;
		}
		@media print {
			body { background: #fff; }
			.toolbar { display: none; }
			.page {
				margin: 0;
				width: auto;
				min-height: 0;
				padding: 0;
				box-shadow: none;
			}
		}
	</style>
</head>
<body>
	<div class="toolbar">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=tccl-consents' ) ); ?>" class="secondary">← <?php esc_html_e( 'Back to records', 'terms-conditions-consent-log' ); ?></a>
		<button type="button" onclick="window.print()"><?php esc_html_e( 'Print / Save as PDF', 'terms-conditions-consent-log' ); ?></button>
	</div>

	<div class="page">
		<header class="cert-header">
			<p class="site-name"><?php echo esc_html( $site_name ); ?></p>
		</header>

		<h1><?php esc_html_e( 'Certificate of consent', 'terms-conditions-consent-log' ); ?></h1>
		<p class="subtitle"><?php esc_html_e( 'Article 7.1 GDPR — proof of explicit consent', 'terms-conditions-consent-log' ); ?></p>

		<section>
			<h2><?php esc_html_e( 'Record', 'terms-conditions-consent-log' ); ?></h2>
			<table class="fields">
				<tbody>
					<tr><th><?php esc_html_e( 'Record ID', 'terms-conditions-consent-log' ); ?></th><td><?php echo absint( $record->id ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Recorded at (UTC)', 'terms-conditions-consent-log' ); ?></th><td><?php echo esc_html( $record->created_at ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Email', 'terms-conditions-consent-log' ); ?></th><td><?php echo esc_html( $record->email ); ?></td></tr>
					<tr><th><?php esc_html_e( 'User ID', 'terms-conditions-consent-log' ); ?></th><td><?php echo $record->user_id ? absint( $record->user_id ) : '—'; ?></td></tr>
					<tr><th><?php esc_html_e( 'Order ID', 'terms-conditions-consent-log' ); ?></th><td><?php echo $record->order_id ? absint( $record->order_id ) : '—'; ?></td></tr>
					<tr><th><?php esc_html_e( 'Consent type', 'terms-conditions-consent-log' ); ?></th><td><?php echo esc_html( $record->consent_type ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Document version', 'terms-conditions-consent-log' ); ?></th><td><?php echo esc_html( $record->consent_version ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Accepted', 'terms-conditions-consent-log' ); ?></th><td><?php echo $record->consent_value ? esc_html__( 'Yes', 'terms-conditions-consent-log' ) : esc_html__( 'No', 'terms-conditions-consent-log' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'IP address', 'terms-conditions-consent-log' ); ?></th><td><?php echo esc_html( $record->ip_address ); ?></td></tr>
					<tr><th><?php esc_html_e( 'User agent', 'terms-conditions-consent-log' ); ?></th><td><?php echo esc_html( $record->user_agent ); ?></td></tr>
				</tbody>
			</table>
		</section>

		<section>
			<h2><?php esc_html_e( 'Accepted text', 'terms-conditions-consent-log' ); ?></h2>
			<div class="accepted-text"><?php echo wp_kses_post( $record->consent_text ); ?></div>
		</section>

		<section>
			<h2><?php esc_html_e( 'Integrity seal (SHA-256)', 'terms-conditions-consent-log' ); ?></h2>
			<div class="seal">
				<code><?php echo '' !== $record->consent_text_hash ? esc_html( $record->consent_text_hash ) : '—'; ?></code>
				<p>
					<?php if ( '' === $record->consent_text_hash ) : ?>
						<span class="status-na"><?php esc_html_e( 'This record predates the integrity-sealing feature; no seal is available.', 'terms-conditions-consent-log' ); ?></span>
					<?php elseif ( $intact ) : ?>
						<span class="status-ok">✓ <?php esc_html_e( 'Verified', 'terms-conditions-consent-log' ); ?></span> ·
						<?php esc_html_e( 'The hash above matches the stored accepted text. Any later edit would break the seal and invalidate this certificate.', 'terms-conditions-consent-log' ); ?>
					<?php else : ?>
						<span class="status-tampered">✗ <?php esc_html_e( 'TAMPERED', 'terms-conditions-consent-log' ); ?></span> ·
						<?php esc_html_e( 'The hash does NOT match the stored accepted text. The record has been altered after it was sealed.', 'terms-conditions-consent-log' ); ?>
					<?php endif; ?>
				</p>
				<p><?php esc_html_e( 'This is a cryptographic integrity check, not an electronic signature.', 'terms-conditions-consent-log' ); ?></p>
			</div>
		</section>

		<footer class="cert-footer">
			<?php
			printf(
				/* translators: 1: site URL, 2: generation timestamp UTC. */
				esc_html__( 'Generated by %1$s on %2$s UTC.', 'terms-conditions-consent-log' ),
				esc_html( $site_url ),
				esc_html( $generated )
			);
			?>
		</footer>
	</div>
</body>
</html>
	<?php
}
