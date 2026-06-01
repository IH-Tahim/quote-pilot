/**
 * QuotePilot — Consent-gated partial lead capture.
 *
 * Watches the email and phone fields. ONLY sends a partial-save POST
 * (action: qp_save_lead) AFTER the relevant consent checkbox is ticked.
 *
 * Before consent is ticked: nothing is stored, nothing is sent.
 *
 * On full form submit: does nothing — QP_Submission (B5) handles the
 * booking, and QP_Leads (B7) marks any matching lead as converted via
 * the qp_booking_created hook.
 *
 * Uses QPData.consent to discover which box key gates contact data.
 * Debounced at 500 ms so it does not fire on every keystroke.
 *
 * @package QuotePilot
 */
( function () {
	'use strict';

	if ( typeof window.QPData === 'undefined' ) { return; }
	var D = window.QPData;

	var DEBOUNCE_MS   = 500;
	var debounceTimer = null;

	/* Track what we last sent to avoid duplicate requests */
	var lastEmail = '';
	var lastPhone = '';

	/* =================================================================
	 * Resolve which consent key gates contact data.
	 *
	 * consent_privacy covers personal data in split mode.
	 * consent_all is the single merged-mode box.
	 * ===============================================================*/

	function consentKey() {
		var mode = D.consent && D.consent.mode;
		return ( mode === 'merged' ) ? 'consent_all' : 'consent_privacy';
	}

	function isConsentTicked() {
		var el = document.querySelector( '[name="' + consentKey() + '"]' );
		return el && el.checked;
	}

	/* =================================================================
	 * Build safe partial payload (no password, no sensitive fields)
	 * ===============================================================*/

	function partialData() {
		var input = window.QPCurrentInput || {};

		return {
			service_id:      input.service_id      || '',
			bedrooms:        input.bedrooms         || 0,
			bathrooms:       input.bathrooms        || 0,
			extra_bathrooms: input.extra_bathrooms  || 0,
			preferred_date:  input.preferred_date   || '',
			estimated_total: window.QPLastResult ? window.QPLastResult.total : 0
		};
	}

	/* =================================================================
	 * Fire the partial-save POST
	 * ===============================================================*/

	function sendLead() {
		if ( ! isConsentTicked() ) { return; }

		var emailEl = document.getElementById( 'customer_email' );
		var phoneEl = document.getElementById( 'customer_phone' );

		var email = emailEl ? emailEl.value.trim() : '';
		var phone = phoneEl ? phoneEl.value.trim() : '';

		/* Require at least an email */
		if ( ! email ) { return; }

		/* Skip if nothing changed since the last send */
		if ( email === lastEmail && phone === lastPhone ) { return; }

		lastEmail = email;
		lastPhone = phone;

		var body = new URLSearchParams();
		body.append( 'action',       'qp_save_lead' );
		body.append( 'nonce',        D.nonce );
		body.append( 'email',        email );
		body.append( 'phone',        phone );
		body.append( 'consent_given', '1' );
		body.append( 'partial_data', JSON.stringify( partialData() ) );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', D.ajax_url, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		xhr.setRequestHeader( 'X-Requested-With', 'XMLHttpRequest' );
		/* Fire-and-forget — no UI feedback needed for a background save */
		xhr.send( body.toString() );
	}

	function schedule() {
		clearTimeout( debounceTimer );
		debounceTimer = setTimeout( sendLead, DEBOUNCE_MS );
	}

	/* =================================================================
	 * INIT — bind watchers
	 * ===============================================================*/

	function init() {
		var emailEl = document.getElementById( 'customer_email' );
		var phoneEl = document.getElementById( 'customer_phone' );

		if ( emailEl ) { emailEl.addEventListener( 'input', schedule ); }
		if ( phoneEl ) { phoneEl.addEventListener( 'input', schedule ); }

		/* If the user ticks consent after typing their email, send immediately */
		var consentEl = document.querySelector( '[name="' + consentKey() + '"]' );
		if ( consentEl ) {
			consentEl.addEventListener( 'change', function () {
				if ( consentEl.checked ) { schedule(); }
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} () );
