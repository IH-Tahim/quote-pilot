/**
 * QuotePilot — live price preview (stub).
 *
 * Filled in B6. This script mirrors the PHP price pipeline
 * (QP_Price_Engine) and conditional logic for an instant, in-browser
 * preview only. Its number is NEVER authoritative — PHP recalculates and
 * is the single source of truth on submit.
 *
 * Config is injected by PHP as the global `QPData` (see QP_Quote).
 *
 * @package QuotePilot
 */
( function () {
	'use strict';

	// B6: read QPData, bind input listeners, render the sticky summary.
	if ( typeof window.QPData === 'undefined' ) {
		return;
	}
}() );
