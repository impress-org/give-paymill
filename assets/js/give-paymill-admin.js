/**
 * Give - Paymill Gateway Add-on ADMIN JS
 */
var give_admin_paymill_vars;
jQuery.noConflict();
(function ( $ ) {

	//On DOM Ready
	$( function () {

		//Ensure Times isn't set to anything other than "0" for Paymill
		$( 'body' ).on( 'change', '#cmb2-metabox-form_field_options .give-time-field', function ( e ) {

			var this_val = $( this ).val();

			if ( this_val != '0' ) {

				alert( give_admin_paymill_vars.invalid_time );
				$( this ).val( 0 ).blur();

			}

		} );


		//Set the form values to week by default
		$( '#_give_period, select[name$="[_give_period]"]' ).each( function () {
			$( this ).val( 'week' );
		} );

		//Ensure the period isn't ever set to 'day'
		$( 'body' ).on( 'change', '#cmb2-metabox-form_field_options .cmb2_select', function ( e ) {

			var this_val = $( this ).val();

			if ( this_val === 'day' ) {

				alert( give_admin_paymill_vars.invalid_period );
				$( this ).val( 'week' ).blur();

			}

		} );


	} );

})( jQuery );