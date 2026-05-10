<?php
/**
 * WordPress comments integration.
 *
 * Captures the native `wp-comment-cookies-consent` checkbox (added in WP
 * 4.9.6) when the visitor opts in, and stores it as a consent record of
 * type `comment_consent`. Off by default — admin enables it from
 * Settings → WordPress integrations.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs a consent record when a visitor leaves a comment with the cookies
 * consent checkbox ticked.
 *
 * @param int        $comment_id   Newly inserted comment ID.
 * @param int|string $approved     1, 0 or 'spam'.
 * @param array      $commentdata  Sanitised comment data.
 */
function tccl_wp_comments_capture( $comment_id, $approved, $commentdata ) {
	unset( $approved ); // Spam comments still represent a real submission and a tick.

	if ( ! (int) tccl_get_setting( 'log_comment_consent', 0 ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core validates the comment submission before this hook fires.
	if ( empty( $_POST['wp-comment-cookies-consent'] ) ) {
		return;
	}

	$email = isset( $commentdata['comment_author_email'] ) ? sanitize_email( (string) $commentdata['comment_author_email'] ) : '';
	if ( empty( $email ) ) {
		return;
	}

	$user_id = isset( $commentdata['user_id'] ) ? (int) $commentdata['user_id'] : 0;

	$source_url = '';
	if ( ! empty( $commentdata['comment_post_ID'] ) ) {
		$permalink = get_permalink( (int) $commentdata['comment_post_ID'] );
		if ( $permalink ) {
			$source_url = (string) $permalink;
		}
	}
	if ( '' === $source_url && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$source_url = esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) );
	}

	tccl_save_consent(
		array(
			'user_id'         => $user_id,
			'email'           => $email,
			'consent_type'    => 'comment_consent',
			'consent_version' => (string) tccl_get_setting( 'consent_version', '1.0' ),
			'consent_text'    => __( 'Save my name, email, and website in this browser for the next time I comment.', 'terms-conditions-consent-log' ),
			'consent_value'   => 1,
			'source_url'      => $source_url,
		)
	);
}
add_action( 'comment_post', 'tccl_wp_comments_capture', 20, 3 );
