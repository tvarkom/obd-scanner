/**
 * On-board Diagnostics page (WooCommerce → OBD Scanner).
 *
 * - While a scan runs ([data-tobd-refresh] on the page), reloads the page every 20 seconds, at
 *   most 30 times in a row (10 minutes), without the one-time result notice in the address.
 * - After "Connect and scan" or "Scan now" is clicked, says the request is running and ignores a
 *   second click.
 */
( function () {
	'use strict';

	var KEY = 'tvarkomObdReloads';
	var MAX_RELOADS = 30;

	function reloads( value ) {
		try {
			if ( value === undefined ) {
				return parseInt( window.sessionStorage.getItem( KEY ) || '0', 10 ) || 0;
			}
			window.sessionStorage.setItem( KEY, String( value ) );
		} catch ( e ) {
			// Storage blocked: no automatic reload, since its count couldn't be limited.
			return MAX_RELOADS;
		}
		return 0;
	}

	if ( document.querySelector( '[data-tobd-refresh]' ) ) {
		var count = reloads();
		if ( count < MAX_RELOADS ) {
			window.setTimeout( function () {
				reloads( count + 1 );
				var url = new URL( window.location.href );
				url.searchParams.delete( 'obd_result' );
				window.location.replace( url.toString() );
			}, 20000 );
		}
	} else {
		reloads( 0 );
	}

	Array.prototype.forEach.call( document.querySelectorAll( '.tobd-scan-form' ), function ( form ) {
		var sent = false;
		form.addEventListener( 'submit', function ( event ) {
			if ( sent ) {
				event.preventDefault();
				return;
			}
			sent = true;
			var button = form.querySelector( 'button[type="submit"]' );
			if ( button && window.tvarkomObd ) {
				button.textContent = window.tvarkomObd.busy;
				button.setAttribute( 'aria-busy', 'true' );
			}
		} );
	} );
}() );
