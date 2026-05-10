<?php
/**
 * Contact Form 7 integration loader.
 *
 * Loaded only when Contact Form 7 is active. Captures consent automatically
 * for any CF7 form submission that contains an [acceptance] field ticked by
 * the visitor. One consent_type per form ("cf7_form_{ID}") so that records
 * remain filterable per form in the admin Records tab.
 *
 * Off by default — the admin enables it in Settings → WordPress integrations.
 *
 * @package TermsConditionsConsentLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WPCF7_VERSION' ) ) {
	return;
}

/**
 * Captures the consent at successful CF7 submission.
 *
 * @param object $contact_form CF7 form object (WPCF7_ContactForm).
 */
function tccl_cf7_capture_acceptance( $contact_form ) {
	if ( ! (int) tccl_get_setting( 'cf7_log_acceptance', 0 ) ) {
		return;
	}

	if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'scan_form_tags' ) || ! method_exists( $contact_form, 'id' ) ) {
		return;
	}

	if ( ! class_exists( 'WPCF7_Submission' ) ) {
		return;
	}

	$submission = WPCF7_Submission::get_instance();
	if ( ! $submission ) {
		return;
	}
	$posted = $submission->get_posted_data();
	if ( ! is_array( $posted ) ) {
		$posted = array();
	}

	$tags = $contact_form->scan_form_tags();
	if ( empty( $tags ) ) {
		return;
	}

	$acceptance_tags = array();
	$email           = '';

	foreach ( $tags as $tag ) {
		if ( ! is_object( $tag ) || empty( $tag->basetype ) || empty( $tag->name ) ) {
			continue;
		}
		if ( 'acceptance' === $tag->basetype ) {
			$acceptance_tags[] = $tag;
			continue;
		}
		if ( '' === $email && 'email' === $tag->basetype ) {
			$value = isset( $posted[ $tag->name ] ) ? (string) $posted[ $tag->name ] : '';
			$email = sanitize_email( $value );
		}
	}

	if ( empty( $acceptance_tags ) || empty( $email ) ) {
		return;
	}

	$form_id = (int) $contact_form->id();
	$version = (string) tccl_get_setting( 'consent_version', '1.0' );

	// Resolve the URL of the page where the form was actually submitted.
	// CF7 submissions are AJAX requests originated from the page hosting the
	// form, so HTTP_REFERER is the reliable signal.
	$source_url = '';
	if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$source_url = esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) );
	}

	foreach ( $acceptance_tags as $tag ) {
		$accepted = ! empty( $posted[ $tag->name ] );
		if ( ! $accepted ) {
			continue;
		}

		$text = '';
		if ( isset( $tag->content ) ) {
			$text = (string) $tag->content;
		}
		if ( '' === trim( $text ) && method_exists( $tag, 'get_option' ) ) {
			// Some CF7 versions store the label as a tag option.
			$label = $tag->get_option( 'label' );
			if ( is_array( $label ) && ! empty( $label[0] ) ) {
				$text = (string) $label[0];
			}
		}

		tccl_save_consent(
			array(
				'user_id'         => get_current_user_id(),
				'email'           => $email,
				'consent_type'    => 'cf7_form_' . $form_id,
				'consent_version' => $version,
				'consent_text'    => $text,
				'consent_value'   => 1,
				'source_url'      => $source_url,
			)
		);
	}
}
add_action( 'wpcf7_mail_sent', 'tccl_cf7_capture_acceptance', 20, 1 );
