/**
 * QuotePilot — Live price preview.
 *
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  PREVIEW ONLY — THIS NUMBER IS NEVER AUTHORITATIVE.         ║
 * ║  PHP (QP_Price_Engine::calculate) recalculates on submit.   ║
 * ║  Never submit this value to the server or use it as truth.  ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * Mirrors QP_Price_Engine's pipeline ORDER exactly so the preview
 * matches the server total (same inputs → same number). Also mirrors
 * QP_Conditional::evaluate so hidden fields contribute zero.
 *
 * Pipeline order (must match PHP):
 *   base → flat add-ons → percent add-ons → flat surcharges →
 *   percent surcharges → discount → tax
 *
 * Config injected by PHP as window.QPData (QP_Quote::enqueue_assets).
 *
 * @package QuotePilot
 */
( function () {
	'use strict';

	if ( typeof window.QPData === 'undefined' ) { return; }
	var D = window.QPData;

	/* =================================================================
	 * CONDITIONAL LOGIC — mirrors QP_Conditional::evaluate
	 * ===============================================================*/

	function evalCondition( cond, input ) {
		var iv  = ( input[ cond.field ] !== undefined ) ? input[ cond.field ] : null;
		var val = cond.value;
		switch ( cond.op ) {
			case 'is':           return String( iv ) === String( val );
			case 'is_not':       return String( iv ) !== String( val );
			case 'greater_than': return parseFloat( iv ) > parseFloat( val );
			case 'less_than':    return parseFloat( iv ) < parseFloat( val );
			case 'contains':
				if ( Array.isArray( iv ) ) { return iv.indexOf( val ) !== -1; }
				if ( typeof iv === 'string' ) { return iv.indexOf( String( val ) ) !== -1; }
				return false;
			case 'checked':      return !!iv;
			default:             return false;
		}
	}

	function buildVisibility( rules, input ) {
		var vis = {};
		var allKeys = Object.keys( D.fields );
		allKeys.forEach( function ( k ) { vis[ k ] = true; } );

		if ( ! rules || ! rules.length ) { return vis; }

		allKeys.forEach( function ( fieldKey ) {
			var fieldRules = rules.filter( function ( r ) { return r.target_field === fieldKey; } );
			if ( ! fieldRules.length ) { return; }

			fieldRules.forEach( function ( rule ) {
				var action     = rule.action || 'show';
				var logic      = rule.logic  || 'all';
				var conditions = rule.conditions || [];
				if ( ! conditions.length ) { return; }

				var met = conditions.filter( function ( c ) { return evalCondition( c, input ); } ).length;
				var satisfied = ( logic === 'all' ) ? ( met === conditions.length ) : ( met > 0 );

				if ( action === 'show' && ! satisfied ) { vis[ fieldKey ] = false; }
				if ( action === 'hide' &&   satisfied ) { vis[ fieldKey ] = false; }
			} );
		} );

		return vis;
	}

	/* Helper: get a value only when the field is visible */
	function fv( key, input, vis, def ) {
		if ( vis && vis[ key ] === false ) { return def; }
		return ( input[ key ] !== undefined ) ? input[ key ] : def;
	}

	/* =================================================================
	 * READ FORM VALUES (keyed by canonical field names)
	 * ===============================================================*/

	function readForm() {
		var form = document.getElementById( 'qp-calculator-form' );
		if ( ! form ) { return {}; }

		var vals = {};
		var allFields = D.fields;

		Object.keys( allFields ).forEach( function ( key ) {
			var def = allFields[ key ];

			if ( def.multiple ) {
				/* checkbox arrays — name="addons_flat", name="addons_percent" */
				var cbs = form.querySelectorAll( '[name="' + key + '"]' );
				vals[ key ] = [];
				cbs.forEach( function ( cb ) {
					if ( cb.checked ) { vals[ key ].push( cb.value ); }
				} );
				return;
			}

			if ( def.input_type === 'radio' ) {
				var checked = form.querySelector( '[name="' + key + '"]:checked' );
				vals[ key ] = checked ? checked.value : '';
				return;
			}

			if ( def.input_type === 'checkbox' ) {
				var el = form.querySelector( '[name="' + key + '"]' );
				vals[ key ] = ( el && el.checked ) ? 1 : 0;
				return;
			}

			var input = form.querySelector( '[name="' + key + '"]' );
			vals[ key ] = input ? input.value : '';
		} );

		return vals;
	}

	/* =================================================================
	 * PRICE PREVIEW — mirrors QP_Price_Engine::calculate exactly
	 *
	 * REMINDER: preview only. PHP recalculates on submit.
	 * ===============================================================*/

	function r2( v ) { return Math.round( v * 100 ) / 100; }
	function cap( s ) { return s ? s.charAt( 0 ).toUpperCase() + s.slice( 1 ) : ''; }

	function calcPreview( input, vis ) {
		var empty = { items: [], base: 0, surcharge_total: 0, discount_total: 0, tax_rate: 0, tax_total: 0, total: 0 };
		var sid   = parseInt( fv( 'service_id', input, vis, 0 ), 10 );
		if ( ! sid || ! D.services[ sid ] ) { return empty; }

		var pricing = D.services[ sid ];
		var items   = [];
		var base    = 0;

		/* -- 1a. Service base price ----------------------------------- */
		var basePrice = r2( parseFloat( pricing.base_price ) || 0 );
		items.push( { label: 'Base Price', type: 'base', qty: 1, unit: basePrice, total: basePrice } );
		base = basePrice;

		/* -- 1b. Room size multiplier (applies to bedrooms/bathrooms) - */
		var roomSize = fv( 'room_size', input, vis, 'medium' );
		if ( [ 'small', 'medium', 'large' ].indexOf( roomSize ) === -1 ) { roomSize = 'medium'; }
		var roomMult = ( pricing.room_size_multipliers && pricing.room_size_multipliers[ roomSize ] )
			? parseFloat( pricing.room_size_multipliers[ roomSize ] ) : 1.0;

		/* -- 1c. Square footage -------------------------------------- */
		if ( pricing.enable_sqft ) {
			var sqft = parseFloat( fv( 'sqft', input, vis, 0 ) ) || 0;
			if ( sqft > 0 && pricing.price_per_sqft > 0 ) {
				var sqftLine = r2( sqft * pricing.price_per_sqft );
				items.push( { label: 'Square Footage', type: 'multiplier', qty: sqft, unit: r2( pricing.price_per_sqft ), total: sqftLine } );
				base += sqftLine;
			}
		}

		/* -- 1d. Bedrooms ------------------------------------------- */
		if ( pricing.enable_bedrooms ) {
			var beds = parseInt( fv( 'bedrooms', input, vis, 0 ), 10 ) || 0;
			if ( beds > 0 ) {
				var bedUnit = r2( pricing.price_per_bedroom * roomMult );
				var bedLine = r2( beds * bedUnit );
				items.push( { label: 'Bedrooms (' + cap( roomSize ) + ')', type: 'multiplier', qty: beds, unit: bedUnit, total: bedLine } );
				base += bedLine;
			}
		}

		/* -- 1e. Bathrooms & extra bathrooms ------------------------- */
		if ( pricing.enable_bathrooms ) {
			var baths  = parseInt( fv( 'bathrooms',       input, vis, 0 ), 10 ) || 0;
			var xbaths = parseInt( fv( 'extra_bathrooms', input, vis, 0 ), 10 ) || 0;

			if ( baths > 0 ) {
				var b1Unit = r2( pricing.price_per_bathroom * roomMult );
				var b1Line = r2( b1Unit );
				items.push( { label: 'First Bathroom (' + cap( roomSize ) + ')', type: 'multiplier', qty: 1, unit: b1Unit, total: b1Line } );
				base += b1Line;

				var rem = Math.max( 0, baths - 1 ) + xbaths;
				if ( rem > 0 ) {
					var xbUnit = r2( pricing.price_per_extra_bathroom * roomMult );
					var xbLine = r2( rem * xbUnit );
					items.push( { label: 'Additional Bathrooms (' + cap( roomSize ) + ')', type: 'multiplier', qty: rem, unit: xbUnit, total: xbLine } );
					base += xbLine;
				}
			} else if ( xbaths > 0 ) {
				var xbOnlyUnit = r2( pricing.price_per_extra_bathroom * roomMult );
				var xbOnlyLine = r2( xbaths * xbOnlyUnit );
				items.push( { label: 'Additional Bathrooms (' + cap( roomSize ) + ')', type: 'multiplier', qty: xbaths, unit: xbOnlyUnit, total: xbOnlyLine } );
				base += xbOnlyLine;
			}
		}

		/* -- 1f. Living rooms --------------------------------------- */
		if ( pricing.enable_living_room ) {
			var lrs    = parseInt( fv( 'living_rooms', input, vis, 0 ), 10 ) || 0;
			var lrMult = 1.0;
			var lrTag  = '';

			if ( pricing.living_room_size_multipliers && pricing.living_room_size_multipliers.enabled ) {
				var lrSize = fv( 'living_room_size', input, vis, 'medium' );
				if ( [ 'small', 'medium', 'large' ].indexOf( lrSize ) === -1 ) { lrSize = 'medium'; }
				lrMult = parseFloat( pricing.living_room_size_multipliers[ lrSize ] || 1.0 );
				lrTag  = ' (' + cap( lrSize ) + ')';
			}

			if ( lrs > 0 ) {
				var lrUnit = r2( pricing.price_per_living_room * lrMult );
				var lrLine = r2( lrs * lrUnit );
				items.push( { label: 'Living Rooms' + lrTag, type: 'multiplier', qty: lrs, unit: lrUnit, total: lrLine } );
				base += lrLine;
			}
		}

		/* -- 1g. Stories ------------------------------------------- */
		if ( pricing.enable_stories ) {
			var stories = parseInt( fv( 'stories', input, vis, 1 ), 10 ) || 1;
			var addFloors = Math.max( 0, stories - 1 );
			if ( addFloors > 0 ) {
				var stUnit = r2( parseFloat( pricing.price_per_story ) || 0 );
				var stLine = r2( addFloors * stUnit );
				items.push( { label: 'Building Stories Surcharge', type: 'multiplier', qty: addFloors, unit: stUnit, total: stLine } );
				base += stLine;
			}
		}

		/* -- 1h. Ovens --------------------------------------------- */
		if ( pricing.enable_oven ) {
			var ovens    = parseInt( fv( 'ovens',     input, vis, 0 ),          10 ) || 0;
			var ovenSize = fv( 'oven_size', input, vis, 'standard' );
			var ovenMult = ( ovenSize === 'large' ) ? ( parseFloat( pricing.oven_size_large_multiplier ) || 1.5 ) : 1.0;

			if ( ovens > 0 ) {
				var ovUnit = r2( pricing.price_per_oven * ovenMult );
				var ovLine = r2( ovens * ovUnit );
				items.push( { label: 'Oven Cleaning (' + cap( ovenSize ) + ')', type: 'multiplier', qty: ovens, unit: ovUnit, total: ovLine } );
				base += ovLine;
			}
		}

		base = r2( base );

		/* -- 2. Flat add-ons --------------------------------------- */
		var addonFlat = 0;
		if ( pricing.enable_addons && Array.isArray( pricing.addons ) ) {
			var selFlat = fv( 'addons_flat', input, vis, [] );
			if ( ! Array.isArray( selFlat ) ) { selFlat = []; }

			pricing.addons.forEach( function ( a ) {
				if ( a.type === 'flat' && selFlat.indexOf( a.slug ) !== -1 ) {
					var v = r2( parseFloat( a.value ) || 0 );
					items.push( { label: 'Add-on: ' + a.label, type: 'addon_flat', qty: 1, unit: v, total: v } );
					addonFlat += v;
				}
			} );
		}
		var sub1 = r2( base + addonFlat );

		/* -- 3. Percent add-ons (of subtotal_1) -------------------- */
		var addonPct = 0;
		if ( pricing.enable_addons && Array.isArray( pricing.addons ) ) {
			var selPct = fv( 'addons_percent', input, vis, [] );
			if ( ! Array.isArray( selPct ) ) { selPct = []; }

			pricing.addons.forEach( function ( a ) {
				if ( a.type === 'percent' && selPct.indexOf( a.slug ) !== -1 ) {
					var p = parseFloat( a.value ) || 0;
					var v = r2( sub1 * ( p / 100 ) );
					items.push( { label: 'Add-on: ' + a.label + ' (' + p + '%)', type: 'addon_percent', qty: 1, unit: v, total: v } );
					addonPct += v;
				}
			} );
		}
		var sub2 = r2( sub1 + addonPct );

		/* -- 4. Flat surcharges ------------------------------------ */
		var emFlat = 0;
		var isEmergency = fv( 'emergency', input, vis, 0 );
		if ( isEmergency && D.settings && D.settings.emergency_surcharge_enabled ) {
			if ( D.settings.emergency_surcharge_type === 'flat' ) {
				var ev = r2( parseFloat( D.settings.emergency_surcharge_value ) || 0 );
				if ( ev > 0 ) {
					items.push( { label: 'Emergency Surcharge', type: 'surcharge', qty: 1, unit: ev, total: ev } );
					emFlat = ev;
				}
			}
		}

		var dateFlat    = 0;
		var dateRule    = null;
		var prefDate    = fv( 'preferred_date', input, vis, '' );
		if ( prefDate && D.date_rules ) {
			D.date_rules.forEach( function ( rule ) {
				if ( rule.date === prefDate && rule.type === 'high_rate' ) { dateRule = rule; }
			} );
		}
		if ( dateRule && dateRule.surcharge_type === 'flat' ) {
			var dv = r2( parseFloat( dateRule.surcharge_value ) || 0 );
			if ( dv > 0 ) {
				items.push( { label: 'Peak Date Surcharge (' + prefDate + ')', type: 'surcharge', qty: 1, unit: dv, total: dv } );
				dateFlat = dv;
			}
		}
		var sub3 = r2( sub2 + emFlat + dateFlat );

		/* -- 5. Percent surcharges (of subtotal_3) ----------------- */
		var emPct = 0;
		if ( isEmergency && D.settings && D.settings.emergency_surcharge_enabled ) {
			if ( D.settings.emergency_surcharge_type === 'percent' ) {
				var ep = parseFloat( D.settings.emergency_surcharge_value ) || 0;
				var epv = r2( sub3 * ( ep / 100 ) );
				if ( epv > 0 ) {
					items.push( { label: 'Emergency Surcharge (' + ep + '%)', type: 'surcharge', qty: 1, unit: epv, total: epv } );
					emPct = epv;
				}
			}
		}

		var datePct = 0;
		if ( dateRule && dateRule.surcharge_type === 'percent' ) {
			var dp  = parseFloat( dateRule.surcharge_value ) || 0;
			var dpv = r2( sub3 * ( dp / 100 ) );
			if ( dpv > 0 ) {
				items.push( { label: 'Peak Date Surcharge (' + dp + '%)', type: 'surcharge', qty: 1, unit: dpv, total: dpv } );
				datePct = dpv;
			}
		}
		var sub4 = r2( sub3 + emPct + datePct );

		/* -- 6. Discount ------------------------------------------- */
		/* Coupon validation requires DB — omitted from preview.
		 * Server applies the real discount on submit.               */
		var discountTotal = 0;
		var sub5 = sub4;

		/* -- 7. GST / Tax ------------------------------------------ */
		var taxRate  = 0;
		var taxTotal = 0;
		if ( D.tax && D.tax.enabled ) {
			taxRate = parseFloat( D.tax.rate ) || 0;
			if ( taxRate > 0 ) {
				taxTotal = r2( sub5 * ( taxRate / 100 ) );
				items.push( { label: 'GST / Tax (' + taxRate + '%)', type: 'tax', qty: 1, unit: taxTotal, total: taxTotal } );
			}
		}

		var total = r2( sub5 + taxTotal );

		return {
			items:           items,
			base:            base,
			surcharge_total: r2( emFlat + dateFlat + emPct + datePct ),
			discount_total:  discountTotal,
			tax_rate:        taxRate,
			tax_total:       taxTotal,
			total:           total
		};
	}

	/* =================================================================
	 * DOM SHOW/HIDE — applies visibility to [data-field] containers
	 * ===============================================================*/

	function applyVisibility( vis ) {
		Object.keys( vis ).forEach( function ( key ) {
			document.querySelectorAll( '[data-field="' + key + '"]' ).forEach( function ( el ) {
				el.style.display = vis[ key ] ? '' : 'none';
			} );
		} );
	}

	/* =================================================================
	 * ADD-ONS RENDERING — populates #qp-addons-cards-grid dynamically
	 * ===============================================================*/

	function renderAddons( sid ) {
		var grid      = document.getElementById( 'qp-addons-cards-grid' );
		var container = document.getElementById( 'qp-addons-container' );
		if ( ! grid ) { return; }

		var pricing = D.services[ sid ];
		if ( ! pricing || ! pricing.enable_addons || ! pricing.addons || ! pricing.addons.length ) {
			if ( container ) { container.style.display = 'none'; }
			grid.innerHTML = '<p class="qp-addons-placeholder description">No add-ons available for this service.</p>';
			return;
		}

		if ( container ) { container.style.display = ''; }
		var currency = D.currency || '$';
		var html     = '';

		pricing.addons.forEach( function ( addon ) {
			var fieldName  = ( addon.type === 'percent' ) ? 'addons_percent' : 'addons_flat';
			var priceLabel = ( addon.type === 'percent' )
				? ( parseFloat( addon.value ).toFixed( 0 ) + '%' )
				: ( currency + parseFloat( addon.value ).toFixed( 2 ) );

			html += '<label class="qp-addon-card" tabindex="0" role="checkbox" aria-checked="false"' +
				' data-field-name="' + escAttr( fieldName ) + '"' +
				' data-addon-slug="' + escAttr( addon.slug ) + '">' +
				'<input type="checkbox" name="' + escAttr( fieldName ) + '"' +
				' value="' + escAttr( addon.slug ) + '" class="qp-checkbox-input" aria-hidden="true"' +
				' style="position:absolute;opacity:0;width:0;height:0;" />' +
				'<div class="qp-addon-card-left">' +
				'<div class="qp-addon-card-checkbox"></div>' +
				'<span class="qp-addon-card-label">' + escHtml( addon.label ) + '</span>' +
				'</div>' +
				'<span class="qp-addon-card-price">+ ' + escHtml( priceLabel ) + '</span>' +
				'</label>';
		} );

		grid.innerHTML = html;

		grid.querySelectorAll( '.qp-addon-card' ).forEach( function ( card ) {
			function toggle() {
				var cb = card.querySelector( 'input[type="checkbox"]' );
				if ( ! cb ) { return; }
				cb.checked = ! cb.checked;
				card.classList.toggle( 'selected', cb.checked );
				card.setAttribute( 'aria-checked', cb.checked ? 'true' : 'false' );
				recalculate();
			}
			card.addEventListener( 'click', toggle );
			card.addEventListener( 'keydown', function ( e ) {
				if ( e.key === ' ' || e.key === 'Enter' ) { e.preventDefault(); toggle(); }
			} );
		} );
	}

	/* =================================================================
	 * STICKY SUMMARY FOOTER — update amount + service name
	 * ===============================================================*/

	function fmtMoney( amount ) {
		return ( D.currency || '$' ) + parseFloat( amount ).toFixed( 2 );
	}

	function updateSummary( result, serviceName ) {
		var amountEl  = document.getElementById( 'qp-summary-amount' );
		var serviceEl = document.getElementById( 'qp-summary-service' );

		if ( amountEl )  { amountEl.textContent  = fmtMoney( result.total ); }
		if ( serviceEl && serviceName ) { serviceEl.textContent = serviceName; }

		/* Hidden display field — for reference only, server ignores it */
		var disp = document.getElementById( 'qp_display_total' );
		if ( disp ) { disp.value = result.total.toFixed( 2 ); }
	}

	/* =================================================================
	 * MAIN RECALC CYCLE
	 * ===============================================================*/

	function recalculate() {
		var input = readForm();
		var rules = ( D.conditional_rules && Array.isArray( D.conditional_rules ) ) ? D.conditional_rules : [];
		var vis   = buildVisibility( rules, input );

		applyVisibility( vis );

		/* Expose visibility for form-wizard.js (step validation) */
		window.QPVisibleFields = vis;

		var result = calcPreview( input, vis );

		var sid     = parseInt( input.service_id, 10 );
		var svcName = ( sid && D.services[ sid ] ) ? ( D.services[ sid ].title || '' ) : '';

		updateSummary( result, svcName );

		/* Expose for lead-capture.js */
		window.QPCurrentInput = input;
		window.QPLastResult   = result;
	}

	/* Public so form-wizard and lead-capture can trigger a recalc */
	window.QPRecalculate = recalculate;

	/* =================================================================
	 * INIT
	 * ===============================================================*/

	function init() {
		var form = document.getElementById( 'qp-calculator-form' );
		if ( ! form ) { return; }

		/* Re-render add-ons and recalc on any change */
		form.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.name === 'service_id' ) {
				renderAddons( parseInt( e.target.value, 10 ) );
			}
			recalculate();
		} );
		form.addEventListener( 'input', recalculate );

		/* Initial render */
		var preSelected = form.querySelector( '[name="service_id"]:checked' );
		if ( preSelected ) { renderAddons( parseInt( preSelected.value, 10 ) ); }

		recalculate();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
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

} () );
