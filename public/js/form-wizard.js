/**
 * QuotePilot — Multi-step wizard navigation and form submission.
 *
 * Manages step transitions, per-step inline validation, the submit
 * AJAX POST, and the confirmation/error screen. Reads config from
 * window.QPData only — never hardcodes field names or URLs.
 *
 * Depends on: quote-calculator.js (window.QPRecalculate, window.QPVisibleFields)
 *
 * @package QuotePilot
 */
( function () {
	'use strict';

	if ( typeof window.QPData === 'undefined' ) { return; }
	var D = window.QPData;

	var TOTAL_STEPS = 5;
	var currentStep = 1;

	var STEP_TITLES = {
		1: 'Select Service',
		2: 'Property Details',
		3: 'Extras & Add-ons',
		4: 'Scheduling & Promotions',
		5: 'Your Details'
	};

	/* Field → step map (used to navigate to the right step on server errors) */
	var FIELD_STEP = {
		service_id: 1,
		sqft: 2, bedrooms: 2, bathrooms: 2, extra_bathrooms: 2,
		living_rooms: 2, living_room_size: 2, room_size: 2, stories: 2,
		ovens: 3, oven_size: 3, addons_flat: 3, addons_percent: 3,
		preferred_date: 4, preferred_time: 4, emergency: 4, coupon_code: 4,
		customer_name: 5, customer_email: 5, customer_phone: 5,
		customer_address: 5, consent_all: 5, consent_privacy: 5,
		consent_terms: 5, consent_marketing: 5, create_account: 5, password: 5
	};

	/* ---- Element refs ---- */
	var form, btnNext, btnBack, progressBar, stepTitle, errorsAlert;

	/* =================================================================
	 * INIT
	 * ===============================================================*/

	function init() {
		form       = document.getElementById( 'qp-calculator-form' );
		if ( ! form ) { return; }

		btnNext    = document.getElementById( 'qp-btn-next' );
		btnBack    = document.getElementById( 'qp-btn-back' );
		progressBar = document.getElementById( 'qp-progress-bar' );
		stepTitle  = document.getElementById( 'qp-step-title' );
		errorsAlert = document.getElementById( 'qp-form-errors-alert' );

		if ( btnNext ) { btnNext.addEventListener( 'click', onNext ); }
		if ( btnBack ) { btnBack.addEventListener( 'click', onBack ); }

		/* Password field reveal when create_account ticked */
		var acctCb  = document.getElementById( 'create_account' );
		var passCtr = document.getElementById( 'qp-password-container' );
		if ( acctCb && passCtr ) {
			acctCb.addEventListener( 'change', function () {
				passCtr.style.display = acctCb.checked ? '' : 'none';
				if ( ! acctCb.checked ) {
					var pw = document.getElementById( 'password' );
					if ( pw ) { pw.value = ''; }
				}
			} );
		}

		/* Option-card keyboard/click selection for service cards */
		bindOptionCards();

		updateUI();
	}

	/* =================================================================
	 * OPTION CARD BINDING (service cards, room size, stories, oven size)
	 * ===============================================================*/

	function bindOptionCards() {
		/* Service cards */
		document.querySelectorAll( '.qp-service-card' ).forEach( function ( card ) {
			card.addEventListener( 'click', function () {
				document.querySelectorAll( '.qp-service-card' ).forEach( function ( c ) { c.classList.remove( 'selected' ); } );
				card.classList.add( 'selected' );
			} );
		} );

		/* Small option cards (room_size, stories, oven_size, etc.) */
		document.querySelectorAll( '.qp-option-card-small' ).forEach( function ( card ) {
			card.addEventListener( 'click', function () {
				var radio = card.querySelector( 'input[type="radio"]' );
				if ( ! radio ) { return; }
				/* Deselect siblings */
				document.querySelectorAll( '[name="' + radio.name + '"]' ).forEach( function ( r ) {
					var parent = r.closest( '.qp-option-card-small' );
					if ( parent ) { parent.classList.remove( 'selected' ); }
				} );
				card.classList.add( 'selected' );
				radio.checked = true;
				radio.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
		} );
	}

	/* =================================================================
	 * NAVIGATION
	 * ===============================================================*/

	function onNext() {
		if ( currentStep === TOTAL_STEPS ) {
			submitForm();
			return;
		}

		var errors = validateStep( currentStep );
		if ( errors.length ) {
			renderErrors( errors );
			return;
		}

		clearErrors();
		goTo( currentStep + 1 );
	}

	function onBack() {
		if ( currentStep > 1 ) { clearErrors(); goTo( currentStep - 1 ); }
	}

	function goTo( n ) {
		if ( n < 1 || n > TOTAL_STEPS ) { return; }

		var cur = form.querySelector( '.qp-step.active' );
		if ( cur ) { cur.classList.remove( 'active' ); }

		currentStep = n;

		var next = form.querySelector( '.qp-step[data-step="' + n + '"]' );
		if ( next ) { next.classList.add( 'active' ); }

		updateUI();

		/* Scroll form into view */
		var container = document.getElementById( 'qp-quote-form-container' );
		if ( container ) { container.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
	}

	function updateUI() {
		/* Progress bar */
		var pct = Math.round( ( currentStep / TOTAL_STEPS ) * 100 );
		if ( progressBar ) {
			progressBar.style.width = pct + '%';
			progressBar.setAttribute( 'aria-valuenow', currentStep );
		}

		/* Step dots */
		document.querySelectorAll( '.qp-step-dot' ).forEach( function ( dot ) {
			var s = parseInt( dot.getAttribute( 'data-step' ), 10 );
			dot.classList.remove( 'active', 'completed' );
			if ( s === currentStep )  { dot.classList.add( 'active' ); }
			if ( s < currentStep )    { dot.classList.add( 'completed' ); }
		} );

		/* Step title */
		if ( stepTitle ) { stepTitle.textContent = STEP_TITLES[ currentStep ] || ''; }

		/* Back button visibility */
		if ( btnBack ) { btnBack.style.display = currentStep > 1 ? '' : 'none'; }

		/* Next / Submit button label */
		if ( btnNext ) {
			var textEl  = btnNext.querySelector( '.qp-btn-text' );
			var arrowEl = btnNext.querySelector( '.dashicons' );
			var isLast  = ( currentStep === TOTAL_STEPS );
			if ( textEl )  { textEl.textContent    = isLast ? 'Submit Booking' : 'Next'; }
			if ( arrowEl ) { arrowEl.style.display  = isLast ? 'none' : ''; }
		}
	}

	/* =================================================================
	 * PER-STEP INLINE VALIDATION
	 * ===============================================================*/

	function validateStep( step ) {
		var errors = [];

		if ( step === 1 ) {
			if ( ! form.querySelector( '[name="service_id"]:checked' ) ) {
				errors.push( { field: 'service_id', message: 'Please select a service to continue.' } );
			}
		}

		if ( step === 4 ) {
			var dateEl = form.querySelector( '[name="preferred_date"]' );
			if ( dateEl && ! dateEl.value.trim() ) {
				errors.push( { field: 'preferred_date', message: 'Please choose a preferred date.' } );
			}
			var timeEl = form.querySelector( '[name="preferred_time"]' );
			if ( timeEl && ! timeEl.value.trim() ) {
				errors.push( { field: 'preferred_time', message: 'Please choose a preferred time slot.' } );
			}
		}

		if ( step === 5 ) {
			/* Required contact fields */
			[ 'customer_name', 'customer_email', 'customer_phone', 'customer_address' ].forEach( function ( key ) {
				var el = form.querySelector( '[name="' + key + '"]' );
				if ( el && ! el.value.trim() ) {
					var label = ( D.fields && D.fields[ key ] ) ? D.fields[ key ].label : key;
					errors.push( { field: key, message: label + ' is required.' } );
				}
				if ( key === 'customer_email' && el && el.value.trim() ) {
					if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( el.value.trim() ) ) {
						errors.push( { field: key, message: 'Please enter a valid email address.' } );
					}
				}
			} );

			/* Required consent boxes */
			var consentMode  = D.consent && D.consent.mode;
			var consentBoxes = D.consent && D.consent.boxes ? D.consent.boxes : {};

			if ( consentMode === 'merged' ) {
				var allCb = form.querySelector( '[name="consent_all"]' );
				if ( ! allCb || ! allCb.checked ) {
					errors.push( { field: 'consent_all', message: 'You must agree to the Terms & Conditions and Privacy Policy.' } );
				}
			} else {
				Object.keys( consentBoxes ).forEach( function ( key ) {
					if ( consentBoxes[ key ].required ) {
						var cb = form.querySelector( '[name="' + key + '"]' );
						if ( ! cb || ! cb.checked ) {
							errors.push( { field: key, message: consentBoxes[ key ].label + ' is required.' } );
						}
					}
				} );
			}

			/* Password required if create_account is ticked */
			var acctCb = form.querySelector( '[name="create_account"]' );
			if ( acctCb && acctCb.checked ) {
				var pw = form.querySelector( '[name="password"]' );
				if ( pw && pw.value.length < 8 ) {
					errors.push( { field: 'password', message: 'Password must be at least 8 characters.' } );
				}
			}
		}

		return errors;
	}

	/* =================================================================
	 * ERROR DISPLAY (inline, beside the failing field)
	 * ===============================================================*/

	function renderErrors( errors ) {
		clearErrors();

		errors.forEach( function ( err ) {
			var el = form.querySelector( '[name="' + err.field + '"]' );
			if ( el ) {
				var span = document.createElement( 'span' );
				span.className = 'qp-field-error';
				span.setAttribute( 'role', 'alert' );
				span.textContent = err.message;
				el.classList.add( 'qp-input-error' );
				el.setAttribute( 'aria-describedby', span.id = 'qp-err-' + err.field );
				el.parentNode.insertBefore( span, el.nextSibling );
			}
		} );

		/* Top banner shows the first error for screen readers */
		if ( errorsAlert && errors.length ) {
			errorsAlert.style.display = '';
			errorsAlert.setAttribute( 'role', 'alert' );
			errorsAlert.textContent = errors[ 0 ].message;
		}
	}

	function clearErrors() {
		form.querySelectorAll( '.qp-field-error' ).forEach( function ( el ) { el.remove(); } );
		form.querySelectorAll( '.qp-input-error' ).forEach( function ( el ) {
			el.classList.remove( 'qp-input-error' );
			el.removeAttribute( 'aria-describedby' );
		} );
		if ( errorsAlert ) {
			errorsAlert.style.display = 'none';
			errorsAlert.textContent = '';
		}
	}

	/* =================================================================
	 * FORM SUBMISSION
	 * ===============================================================*/

	function submitForm() {
		var errors = validateStep( TOTAL_STEPS );
		if ( errors.length ) { renderErrors( errors ); return; }
		clearErrors();

		setLoading( true );

		var fd = new FormData( form );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', D.ajax_url, true );
		xhr.setRequestHeader( 'X-Requested-With', 'XMLHttpRequest' );

		xhr.onload = function () {
			setLoading( false );
			if ( xhr.status >= 200 && xhr.status < 300 ) {
				try {
					var resp = JSON.parse( xhr.responseText );
					if ( resp.success ) {
						showConfirmation( resp.data );
					} else {
						handleServerError( resp.data );
					}
				} catch ( e ) {
					showGeneralError( 'An unexpected error occurred. Please try again.' );
				}
			} else {
				showGeneralError( 'Network error. Please check your connection and try again.' );
			}
		};

		xhr.onerror = function () {
			setLoading( false );
			showGeneralError( 'Connection error. Please try again.' );
		};

		xhr.send( fd );
	}

	function setLoading( on ) {
		if ( ! btnNext ) { return; }
		btnNext.disabled = on;
		btnNext.classList.toggle( 'qp-btn-loading', on );
		var textEl = btnNext.querySelector( '.qp-btn-text' );
		if ( textEl ) { textEl.textContent = on ? 'Submitting…' : 'Submit Booking'; }
	}

	/* =================================================================
	 * CONFIRMATION PANEL
	 * Shows SERVER breakdown — NEVER the JS preview number.
	 * ===============================================================*/

	function showConfirmation( data ) {
		var container = document.getElementById( 'qp-quote-form-container' );
		if ( ! container ) { return; }

		var breakdown = data.breakdown || {};
		var payment   = data.payment   || {};
		var currency  = D.currency || '$';

		/* Itemised rows — from SERVER response */
		var rowsHtml = '';
		if ( Array.isArray( breakdown.items ) ) {
			breakdown.items.forEach( function ( item ) {
				var sign  = parseFloat( item.line_total ) < 0 ? '' : '';
				rowsHtml += '<div class="qp-confirm-item">' +
					'<span class="qp-confirm-item-label">' + escHtml( item.item_label ) + '</span>' +
					'<span class="qp-confirm-item-amount">' +
						sign + currency + Math.abs( parseFloat( item.line_total ) ).toFixed( 2 ) +
					'</span></div>';
			} );
		}

		var totalFmt = breakdown.total_formatted
			|| ( currency + parseFloat( breakdown.total || 0 ).toFixed( 2 ) );

		/* Payment section */
		var payHtml;
		if ( payment.next_step === 'payment' && payment.amount_due > 0 ) {
			payHtml = '<div class="qp-confirm-payment-info">' +
				'<strong>Payment Required</strong>' +
				'<p>Amount due now: ' + currency + parseFloat( payment.amount_due ).toFixed( 2 ) + '</p>' +
				'<p>You will receive a payment link shortly.</p>' +
				'</div>';
		} else {
			payHtml = '<div class="qp-confirm-success-note">' +
				'<p>We\'ll be in touch shortly to confirm your booking details.</p>' +
				'</div>';
		}

		/* WhatsApp link if the server included one */
		var waHtml = '';
		if ( data.whatsapp_url ) {
			waHtml = '<a href="' + escAttr( data.whatsapp_url ) + '" class="qp-whatsapp-btn"' +
				' target="_blank" rel="noopener noreferrer">Chat on WhatsApp</a>';
		}

		container.innerHTML =
			'<div class="qp-confirmation-panel">' +
				'<div class="qp-confirm-checkmark" aria-hidden="true">✓</div>' +
				'<h2 class="qp-confirm-heading">Booking Received!</h2>' +
				'<p class="qp-confirm-ref">Your booking reference: <strong>#' + escHtml( data.booking_id ) + '</strong></p>' +
				'<div class="qp-confirm-breakdown">' +
					'<h3>Quote Summary (confirmed by server)</h3>' +
					rowsHtml +
					'<div class="qp-confirm-total"><strong>Total: ' + escHtml( totalFmt ) + '</strong></div>' +
				'</div>' +
				payHtml +
				waHtml +
			'</div>';

		container.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	/* =================================================================
	 * SERVER ERROR HANDLING
	 * ===============================================================*/

	function handleServerError( data ) {
		if ( ! data ) { showGeneralError( 'Submission failed. Please try again.' ); return; }

		var message = data.message || 'Submission failed. Please try again.';
		var fields  = Array.isArray( data.fields ) ? data.fields : [];

		if ( fields.length ) {
			var errors = fields.map( function ( f ) { return { field: f, message: message }; } );

			/* Navigate to the step that owns the failing field */
			var targetStep = FIELD_STEP[ fields[ 0 ] ] || currentStep;
			if ( targetStep !== currentStep ) { goTo( targetStep ); }

			renderErrors( errors );
		} else {
			showGeneralError( message );
		}
	}

	function showGeneralError( msg ) {
		if ( errorsAlert ) {
			errorsAlert.style.display = '';
			errorsAlert.setAttribute( 'role', 'alert' );
			errorsAlert.textContent = msg;
		}
	}

	/* ---- Minimal escape helpers ---- */
	function escHtml( s ) {
		var d = document.createElement( 'div' );
		d.appendChild( document.createTextNode( String( s ) ) );
		return d.innerHTML;
	}
	function escAttr( s ) {
		return String( s ).replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
	}

	/* =================================================================
	 * BOOT
	 * ===============================================================*/

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} () );
