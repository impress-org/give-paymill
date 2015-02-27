/**
 * Give - Paymill Gateway Add-on JS
 */
var give_global_vars;
jQuery( document ).ready( function ( $ ) {

	// non ajaxed
	$( 'body' ).on( 'submit', '.give-form', function ( event ) {

		if ( $( this ).find( 'input.give-gateway:checked' ).val() == 'paymill' ) {

			event.preventDefault();

			give_paymill_process_card();

		}

	} );

} );


function give_paymill_response_handler( error, result ) {
	if ( error ) {
		// re-enable the submit button
		jQuery( '#give_purchase_form_wrap #give-purchase-button' ).attr( "disabled", false );

		if ( give_global_vars.complete_purchase ) {
			jQuery( '#give-purchase-button' ).val( give_global_vars.complete_purchase );
		} else {
			jQuery( '#give-purchase-button' ).val( 'Purchase' );
		}

		// show the errors on the form
		jQuery( '#give-paymill-payment-errors' ).text( error.apierror );

	} else {

		var form$ = jQuery( 'form.give-form' );
		// token contains id, last4, and card type
		var token = result.token;
		// insert the token into the form so it gets submitted to the server
		form$.append( "<input type='hidden' name='paymillToken' value='" + token + "' />" );
		// and submit
		form$.get(0).submit();

	}
}

function give_paymill_process_card() {

	// disable the submit button to prevent repeated clicks
	jQuery( '#give-purchase-button' ).attr( 'disabled', 'disabled' );

	// createToken returns immediately - the supplied callback submits the form if there are no errors
	paymill.createToken( {
		number    : jQuery( '.card-number' ).val(),
		cardholder: jQuery( '.card-name' ).val(),
		cvc       : jQuery( '.card-cvc' ).val(),
		exp_month : jQuery( '.card-expiry-month' ).val(),
		exp_year  : jQuery( '.card-expiry-year' ).val(),
		currency  : give_paymill_vars.currency,
		amount_int: Number( jQuery( '#give-amount' ).val() )
	}, give_paymill_response_handler );

	return false; // submit from callback
}
