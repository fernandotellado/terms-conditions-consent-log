/* Consent box block editor — registered manually to avoid a build step.
 * Uses globals provided by WordPress: wp.blocks, wp.element, wp.blockEditor,
 * wp.components, wp.i18n. */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el        = wp.element.createElement;
	var Fragment  = wp.element.Fragment;
	var __        = wp.i18n.__;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var components = wp.components || {};
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var TextareaControl = components.TextareaControl;

	wp.blocks.registerBlockType( 'tccl/consent-box', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( {
				className: 'tccl-consent-box-block-editor'
			} );

			var sample = attributes.text && attributes.text.length
				? attributes.text
				: __( 'I have read and agree to the privacy policy.', 'terms-conditions-consent-log' );

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Consent box', 'terms-conditions-consent-log' ), initialOpen: true },
						el( TextareaControl, {
							label: __( 'Consent text', 'terms-conditions-consent-log' ),
							help: __( 'Text shown next to the checkbox. Basic HTML allowed (links, strong, em).', 'terms-conditions-consent-log' ),
							value: attributes.text,
							onChange: function ( value ) { setAttributes( { text: value } ); }
						} ),
						el( TextControl, {
							label: __( 'Consent type', 'terms-conditions-consent-log' ),
							help: __( 'Slug stored with each record. Use a different value per use case (e.g. newsletter_signup).', 'terms-conditions-consent-log' ),
							value: attributes.consentType,
							onChange: function ( value ) { setAttributes( { consentType: value } ); }
						} ),
						el( TextControl, {
							label: __( 'Consent version', 'terms-conditions-consent-log' ),
							help: __( 'Leave empty to use the global plugin version.', 'terms-conditions-consent-log' ),
							value: attributes.consentVersion,
							onChange: function ( value ) { setAttributes( { consentVersion: value } ); }
						} ),
						el( TextControl, {
							label: __( 'Submit button label', 'terms-conditions-consent-log' ),
							value: attributes.submitLabel,
							placeholder: __( 'Accept', 'terms-conditions-consent-log' ),
							onChange: function ( value ) { setAttributes( { submitLabel: value } ); }
						} ),
						el( TextControl, {
							label: __( 'Success message', 'terms-conditions-consent-log' ),
							value: attributes.successMessage,
							placeholder: __( 'Thank you, your acceptance has been recorded.', 'terms-conditions-consent-log' ),
							onChange: function ( value ) { setAttributes( { successMessage: value } ); }
						} ),
						el( SelectControl, {
							label: __( 'Require email', 'terms-conditions-consent-log' ),
							value: attributes.requireEmail || 'auto',
							options: [
								{ label: __( 'Auto (only when visitor is not logged in)', 'terms-conditions-consent-log' ), value: 'auto' },
								{ label: __( 'Yes', 'terms-conditions-consent-log' ), value: 'yes' },
								{ label: __( 'No', 'terms-conditions-consent-log' ), value: 'no' }
							],
							onChange: function ( value ) { setAttributes( { requireEmail: value } ); }
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{ className: 'tccl-consent-box tccl-consent-box--editor', style: { padding: '12px 14px', border: '1px dashed #c3c4c7', background: '#fafafa', borderRadius: '4px' } },
						el(
							'label',
							{ style: { display: 'flex', gap: '8px', alignItems: 'flex-start' } },
							el( 'input', { type: 'checkbox', disabled: true } ),
							el( 'span', { dangerouslySetInnerHTML: { __html: sample } } )
						),
						el(
							'p',
							{ style: { margin: '8px 0 0', fontSize: '12px', color: '#646970' } },
							__( 'Preview only. The live form (with submit button) shows on the front-end.', 'terms-conditions-consent-log' )
						)
					)
				)
			);
		},
		save: function () {
			// Dynamic block — rendered server-side via render_callback.
			return null;
		}
	} );
} )( window.wp );
