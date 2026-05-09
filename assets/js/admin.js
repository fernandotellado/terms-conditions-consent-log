/* Terms & Conditions Consent Log — admin JS (live filter + AJAX pagination) */
( function () {
	'use strict';

	var form = document.getElementById( 'tccl-filter-form' );
	var bodyContainer = document.getElementById( 'tccl-records-body' );
	if ( ! form || ! bodyContainer ) {
		return;
	}

	var ajaxUrl = form.getAttribute( 'data-ajax-url' );
	var nonce = form.getAttribute( 'data-nonce' );
	if ( ! ajaxUrl || ! nonce ) {
		return;
	}

	var debounceMs = 300;
	var timer = null;
	var inFlight = null;
	var lastQuery = '';
	var currentPaged = 1;

	function buildPayload( paged ) {
		var data = new FormData( form );
		data.set( 'action', 'tccl_filter_records' );
		data.set( '_ajax_nonce', nonce );
		data.set( 'paged', String( paged || 1 ) );
		return data;
	}

	function payloadKey( data ) {
		var pairs = [];
		data.forEach( function ( value, key ) {
			if ( '_ajax_nonce' === key ) {
				return;
			}
			pairs.push( encodeURIComponent( key ) + '=' + encodeURIComponent( value ) );
		} );
		return pairs.join( '&' );
	}

	function syncUrl( data ) {
		if ( ! window.history || ! window.history.replaceState ) {
			return;
		}
		var keep = [ 'page', 'tab', 'email', 'order_id', 'from', 'to', 'consent_type', 'paged' ];
		var qs = [];
		keep.forEach( function ( k ) {
			var v = data.get( k );
			if ( null === v || '' === v ) {
				return;
			}
			// Skip implicit paged=1 to keep URLs short.
			if ( 'paged' === k && '1' === String( v ) ) {
				return;
			}
			qs.push( encodeURIComponent( k ) + '=' + encodeURIComponent( v ) );
		} );
		var newUrl = window.location.pathname + ( qs.length ? '?' + qs.join( '&' ) : '' );
		window.history.replaceState( null, '', newUrl );
	}

	function activeElementInfo() {
		var el = document.activeElement;
		if ( ! el || ! form.contains( el ) ) {
			return null;
		}
		var info = {
			name: el.getAttribute( 'name' ) || null,
			selectionStart: null,
			selectionEnd: null,
		};
		try {
			if ( typeof el.selectionStart === 'number' ) {
				info.selectionStart = el.selectionStart;
				info.selectionEnd = el.selectionEnd;
			}
		} catch ( e ) {}
		return info;
	}

	function restoreFocus( info ) {
		if ( ! info || ! info.name ) {
			return;
		}
		var target = form.querySelector( '[name="' + info.name + '"]' );
		if ( ! target ) {
			return;
		}
		// If the input the user was typing into still has focus, do not touch
		// it — the browser already manages the caret correctly. Otherwise the
		// caret would jump back to where it was when the request started,
		// even if the user kept typing in the meantime.
		if ( document.activeElement === target ) {
			return;
		}
		target.focus();
		try {
			if ( null !== info.selectionStart && typeof target.setSelectionRange === 'function' ) {
				target.setSelectionRange( info.selectionStart, info.selectionEnd );
			}
		} catch ( e ) {}
	}

	function runQuery( paged, options ) {
		options = options || {};
		var nextPaged = paged || 1;
		var data = buildPayload( nextPaged );

		var key = payloadKey( data );
		if ( ! options.force && key === lastQuery ) {
			return;
		}
		lastQuery = key;
		currentPaged = nextPaged;

		var focusInfo = options.preserveFocus !== false ? activeElementInfo() : null;

		if ( inFlight ) {
			inFlight.abort();
		}

		var controller = ( typeof AbortController !== 'undefined' ) ? new AbortController() : null;
		inFlight = controller;

		form.classList.add( 'is-loading' );

		var fetchOpts = {
			method: 'POST',
			body: data,
			credentials: 'same-origin',
		};
		if ( controller ) {
			fetchOpts.signal = controller.signal;
		}

		fetch( ajaxUrl, fetchOpts )
			.then( function ( res ) { return res.json(); } )
			.then( function ( json ) {
				if ( ! json || ! json.success || ! json.data || ! json.data.html ) {
					return;
				}
				bodyContainer.innerHTML = json.data.html;
				syncUrl( data );
				if ( options.scrollToTable ) {
					var rect = bodyContainer.getBoundingClientRect();
					if ( rect.top < 0 ) {
						bodyContainer.scrollIntoView( { behavior: 'smooth', block: 'start' } );
					}
				}
			} )
			.catch( function ( err ) {
				if ( err && 'AbortError' === err.name ) {
					return;
				}
			} )
			.then( function () {
				form.classList.remove( 'is-loading' );
				if ( inFlight === controller ) {
					inFlight = null;
				}
				if ( focusInfo ) {
					restoreFocus( focusInfo );
				}
			} );
	}

	function scheduleQuery() {
		clearTimeout( timer );
		timer = setTimeout( function () { runQuery( 1 ); }, debounceMs );
	}

	// Keep the Export button URL and label in sync with the current filter.
	function updateExportButton() {
		var btn = document.getElementById( 'tccl-export-btn' );
		if ( ! btn ) {
			return;
		}
		var base = btn.getAttribute( 'data-base-url' );
		if ( ! base ) {
			return;
		}
		var labelAll = btn.getAttribute( 'data-label-all' ) || btn.textContent;
		var labelFiltered = btn.getAttribute( 'data-label-filtered' ) || btn.textContent;

		var data = new FormData( form );
		var filterKeys = [ 'email', 'order_id', 'from', 'to', 'consent_type' ];
		var qs = [];
		var hasFilter = false;
		filterKeys.forEach( function ( k ) {
			var v = data.get( k );
			if ( null === v ) {
				return;
			}
			v = String( v ).trim();
			if ( '' === v ) {
				return;
			}
			hasFilter = true;
			qs.push( encodeURIComponent( k ) + '=' + encodeURIComponent( v ) );
		} );

		var newHref = base;
		if ( qs.length ) {
			newHref += ( base.indexOf( '?' ) >= 0 ? '&' : '?' ) + qs.join( '&' );
		}
		btn.setAttribute( 'href', newHref );
		btn.textContent = hasFilter ? labelFiltered : labelAll;
	}

	// Live filter listeners.
	var inputs = form.querySelectorAll( 'input[type="text"], input[type="number"], input[type="email"], input[type="date"], select' );
	for ( var i = 0; i < inputs.length; i++ ) {
		var input = inputs[ i ];
		input.addEventListener( 'input', function () {
			updateExportButton();
			scheduleQuery();
		} );
		if ( 'SELECT' === input.tagName ) {
			input.addEventListener( 'change', function () {
				updateExportButton();
				scheduleQuery();
			} );
		}
	}

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		clearTimeout( timer );
		runQuery( 1 );
	} );

	function clampPaged( n ) {
		var input = bodyContainer.querySelector( '.tccl-current-page' );
		var total = bodyContainer.querySelector( '.total-pages' );
		var max = total ? parseInt( total.textContent, 10 ) || 1 : 1;
		var v = parseInt( n, 10 );
		if ( isNaN( v ) || v < 1 ) {
			v = 1;
		} else if ( v > max ) {
			v = max;
		}
		if ( input ) {
			input.value = String( v );
		}
		return v;
	}

	// AJAX pagination: delegate clicks on the paging buttons inside the body
	// container (which gets replaced on every refresh).
	bodyContainer.addEventListener( 'click', function ( e ) {
		var link = e.target.closest( 'a.first-page, a.prev-page, a.next-page, a.last-page' );
		if ( ! link ) {
			return;
		}
		e.preventDefault();
		var paged = parseInt( link.getAttribute( 'data-paged' ), 10 );
		if ( isNaN( paged ) || paged < 1 ) {
			paged = 1;
		}
		clearTimeout( timer );
		runQuery( paged, { preserveFocus: false, scrollToTable: true } );
	} );

	// AJAX pagination: handle the current-page input (Enter or change/blur).
	bodyContainer.addEventListener( 'keydown', function ( e ) {
		if ( ! e.target.classList || ! e.target.classList.contains( 'tccl-current-page' ) ) {
			return;
		}
		if ( 'Enter' !== e.key ) {
			return;
		}
		e.preventDefault();
		var paged = clampPaged( e.target.value );
		clearTimeout( timer );
		runQuery( paged, { preserveFocus: false, scrollToTable: false } );
	} );
	bodyContainer.addEventListener( 'change', function ( e ) {
		if ( ! e.target.classList || ! e.target.classList.contains( 'tccl-current-page' ) ) {
			return;
		}
		var paged = clampPaged( e.target.value );
		clearTimeout( timer );
		runQuery( paged, { preserveFocus: false, scrollToTable: false } );
	} );
} )();
