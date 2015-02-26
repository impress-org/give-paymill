var give_global_vars;
jQuery(document).ready(function($) {

	// non ajaxed
	$('body').on('submit', '#give_purchase_form', function(event) {

		if( $('input[name="edd-gateway"]').val() == 'paymill' ) {

			event.preventDefault();

			give_paymill_process_card();

		}

	});

});


function give_paymill_response_handler(error, result) {
	if (error) {
		// re-enable the submit button
		jQuery('#give_purchase_form #edd-purchase-button').attr("disabled", false);

		jQuery('#edd-paymill-ajax img, .edd-cart-ajax').hide();
		if( give_global_vars.complete_purchase )
			jQuery('#edd-purchase-button').val(give_global_vars.complete_purchase);
		else
			jQuery('#edd-purchase-button').val('Purchase');

		// show the errors on the form
		jQuery('#edd-paymill-payment-errors').text(error.apierror);

	} else {
		var form$ = jQuery("#give_purchase_form");
		// token contains id, last4, and card type
		var token = result.token;
		// insert the token into the form so it gets submitted to the server
		form$.append("<input type='hidden' name='paymillToken' value='" + token + "' />");

		// and submit
		form$.get(0).submit();

	}
}

function give_paymill_process_card() {

	// disable the submit button to prevent repeated clicks
	jQuery('#give_purchase_form #edd-purchase-button').attr('disabled', 'disabled');
	jQuery('#edd-paymill-ajax img').show();

	// createToken returns immediately - the supplied callback submits the form if there are no errors
	paymill.createToken({
		number:         jQuery('.card-number').val(),
		cardholder:     jQuery('.card-name').val(),
		cvc:            jQuery('.card-cvc').val(),
		exp_month:      jQuery('.card-expiry-month').val(),
		exp_year: 	    jQuery('.card-expiry-year').val(),
		currency:       give_paymill_vars.currency,
		amount_int:     Number( give_paymill_vars.amount_int )
	}, give_paymill_response_handler);

	return false; // submit from callback
}
