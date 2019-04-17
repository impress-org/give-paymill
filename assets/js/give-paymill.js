/**
 * Give - Paymill Gateway Add-on JS
 */
var give_global_vars;
jQuery( document ).ready( function ( $ ) {

	var this_form;

	// non AJAX
	$( 'body' ).on( 'submit', '.give-form', function ( event ) {

		this_form = $( this );

		if ( this_form.find( 'input.give-gateway:checked' ).val() == 'paymill' ) {

			event.preventDefault();

			give_paymill_process_card( this_form );

		}

	} );

	function give_paymill_response_handler( error, result ) {

		if ( error ) {
			// re-enable the submit button
			jQuery( '#give_purchase_form_wrap #give-purchase-button' ).attr( "disabled", false );
			jQuery( '.give-loading-animation' ).fadeOut();

			if ( give_global_vars.complete_purchase ) {
				jQuery( '#give-purchase-button' ).val( give_global_vars.complete_purchase );
			} else {
				jQuery( '#give-purchase-button' ).val( 'Purchase' );
			}

			// show the errors on the form
			jQuery( this_form ).find( '.cc-address' ).after( '<div id="give-paymill-payment-errors" class="give_error">' + error.apierror + '</div>' );

		} else {

			// token contains id, last4, and card type
			var token = result.token;
			// insert the token into the form so it gets submitted to the server
			this_form.append( "<input type='hidden' name='paymillToken' value='" + token + "' />" );
			// and submit
			this_form.get( 0 ).submit();

		}
	}

	function give_paymill_process_card( this_form ) {

		// disable the submit button to prevent repeated clicks
		jQuery( this_form ).find( '#give-purchase-button' ).attr( 'disabled', 'disabled' );

		// createToken returns immediately - the supplied callback submits the form if there are no errors
		paymill.createToken( {
			number    : jQuery( this_form ).find( '.card-number' ).val(),
			cardholder: jQuery( this_form ).find( '.card-name' ).val(),
			cvc       : jQuery( this_form ).find( '.card-cvc' ).val(),
			exp_month : jQuery( this_form ).find( '.card-expiry-month' ).val(),
			exp_year  : jQuery( this_form ).find( '.card-expiry-year' ).val(),
			currency  : give_paymill_vars.currency,
			amount_int: Number( jQuery( this_form ).find( '#give-amount' ).val() * 100 )
		}, give_paymill_response_handler );

		return false; // submit from callback
	}

} );

