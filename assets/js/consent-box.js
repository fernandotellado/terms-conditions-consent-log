/* Terms & Conditions Consent Log — front-end consent box */
( function () {
	'use strict';

	function showFeedback( form, message, isError ) {
		var feedback = form.querySelector( '.tccl-consent-box__feedback' );
		if ( ! feedback ) {
			return;
		}
		feedback.textContent = message || '';
		feedback.classList.toggle( 'is-error', !! isError );
		feedback.classList.toggle( 'is-success', ! isError && !! message );
	}

	function disableSubmit( form ) {
		var btn = form.querySelector( '.tccl-consent-box__submit' );
		if ( btn ) {
			btn.disabled = true;
		}
	}

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		if ( ! form || ! form.classList || ! form.classList.contains( 'tccl-consent-box' ) ) {
			return;
		}
		event.preventDefault();

		var checkbox = form.querySelector( '.tccl-consent-box__checkbox' );
		if ( ! checkbox || ! checkbox.checked ) {
			showFeedback( form, '', false );
			return;
		}

		var emailInput = form.querySelector( 'input[type="email"]' );
		var textNode   = form.querySelector( '.tccl-consent-box__text' );
		var rest       = form.dataset.rest;
		var nonce      = form.dataset.nonce;
		if ( ! rest || ! nonce ) {
			return;
		}

		var payload = {
			consent_type:    form.dataset.type || 'consent_box',
			consent_version: form.dataset.version || '',
			consent_text:    textNode ? textNode.innerHTML : '',
			email:           emailInput ? emailInput.value : '',
			source_url:      window.location.href || ''
		};

		showFeedback( form, '', false );

		fetch( rest, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   nonce
			},
			body: JSON.stringify( payload )
		} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					return { ok: response.ok, data: data };
				} );
			} )
			.then( function ( result ) {
				if ( result.ok && result.data && result.data.ok ) {
					showFeedback( form, form.dataset.success || '', false );
					disableSubmit( form );
					return;
				}
				var message = ( result.data && result.data.message ) ? result.data.message : 'Error';
				showFeedback( form, message, true );
			} )
			.catch( function () {
				showFeedback( form, 'Error', true );
			} );
	} );
} )();
