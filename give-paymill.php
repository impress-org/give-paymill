<?php
/*
Plugin Name: Give - Paymill Gateway
Plugin URL: http://wordimpress.com/addons/paymill-gatways
Description: Adds a payment gateway for Paymill.com
Version: 1.0.1
Author: WordImpress
Author URI: http://givewp.com
Contributors: Pippin Williamson, Devin Walker, webdevmattcrom, mordauk
*/

if ( ! defined( 'GIVE_PAYMILL_PLUGIN_DIR' ) ) {
	define( 'GIVE_PAYMILL_PLUGIN_DIR', dirname( __FILE__ ) );
}

if ( ! defined( 'GIVE_PAYMILL_PLUGIN_URL' ) ) {
	define( 'GIVE_PAYMILL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

define( 'GIVE_PAYMILL_VERSION', '1.0.1' );


/*--------------------------------------------------------------------------
LICENSING / UPDATES
--------------------------------------------------------------------------*/

function give_add_paymill_licensing() {
	if ( class_exists( 'Give_License' ) && is_admin() ) {
		$give_stripe_license = new Give_License( __FILE__, 'Paymill Gateway', GIVE_PAYMILL_VERSION, 'WordImpress', 'paymill_license_key' );
	}
}

add_action( 'plugins_loaded', 'give_add_paymill_licensing' );


/* ------------------------------------------------------------------------
i18n
--------------------------------------------------------------------------*/

function give_paymill_textdomain() {

	// Set filter for plugin's languages directory
	$give_lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
	$give_lang_dir = apply_filters( 'give_paymill_languages_directory', $give_lang_dir );

	// Load the translations
	load_plugin_textdomain( 'give_paymill', false, $give_lang_dir );
}

add_action( 'init', 'give_paymill_textdomain' );


// registers the gateway
function give_paymill_register_gateway( $gateways ) {
	// Format: ID => Name
	$gateways['paymill'] = array( 'admin_label' => 'Paymill', 'checkout_label' => __( 'Credit Card', 'give_paymill' ) );

	return $gateways;
}

add_filter( 'give_payment_gateways', 'give_paymill_register_gateway' );

// adds our ajax indicator and payment error container
function give_paymill_add_loader() {
	echo '<div id="give-paymill-ajax><img src="' . GIVE_PLUGIN_URL . 'assets/images/spinner.gif" class="give-cart-ajax" style="display: none;"/></div>';
	echo '<div id="give-paymill-payment-errors" class="give_error"></div>';
}

//add_action( 'give_after_cc_fields', 'give_paymill_add_loader' );

// processes the payment
function give_paymill_process_paymill_payment( $purchase_data ) {

	global $give_options;

	if ( ! class_exists( 'Services_Paymill_Base' ) ) {
		require_once GIVE_PAYMILL_PLUGIN_DIR . '/Paymill/Transactions.php';
		require_once GIVE_PAYMILL_PLUGIN_DIR . '/Paymill/Clients.php';
		require_once GIVE_PAYMILL_PLUGIN_DIR . '/Paymill/Subscriptions.php';
		require_once GIVE_PAYMILL_PLUGIN_DIR . '/Paymill/Offers.php';
		require_once GIVE_PAYMILL_PLUGIN_DIR . '/Paymill/Payments.php';
	}

	// make sure we don't have any left over errors present
	give_clear_errors();

	if ( ! isset( $_POST['paymillToken'] ) ) {
		// no token
		give_set_error( 'no_paymill_token', __( 'Missing token. Please contact support.', 'give_paymill' ) );
	}

	$errors = give_get_errors();

	if ( ! $errors ) {

		if ( give_is_test_mode() ) {
			$apiKey      = trim( $give_options['paymill_test_key'] );
			$apiEndpoint = 'https://api.paymill.de/v2/';
		} else {
			$apiKey      = trim( $give_options['paymill_live_key'] );
			$apiEndpoint = 'https://api.paymill.de/v2/';
		}

		try {

			$transactionsObject  = new Services_Paymill_Transactions( $apiKey, $apiEndpoint );
			$clientsObject       = new Services_Paymill_Clients( $apiKey, $apiEndpoint );
			$subscriptionsObject = new Services_Paymill_Subscriptions( $apiKey, $apiEndpoint );
			$paymentsObject      = new Services_Paymill_Payments( $apiKey, $apiEndpoint );
			$recurring_signup    = false;
			$customer_exists     = false;

			if ( is_user_logged_in() ) {
				$user = get_user_by( 'email', $purchase_data['user_email'] );
				if ( $user ) {
					$customer_id = get_user_meta( $user->ID, '_give_paymill_customer_id', true );
					if ( $customer_id ) {
						$customer_exists = true;
					}
				}
			}

			if ( ! $customer_exists ) {

				// Create a customer first so we can retrieve them later for future payments
				$customer = $clientsObject->create( array(
						'description' => $purchase_data['user_email'],
						'email'       => $purchase_data['user_email']
					)
				);

				$customer_id = $customer['id'];

				if ( is_user_logged_in() ) {
					update_user_meta( $user->ID, '_give_paymill_customer_id', $customer_id );
				}
			}

			// setup the payment details
			$payment_data = array(
				'price'           => $purchase_data['price'],
				'give_form_title' => $purchase_data['post_data']['give-form-title'],
				'give_form_id'    => intval( $purchase_data['post_data']['give-form-id'] ),
				'date'            => $purchase_data['date'],
				'user_email'      => $purchase_data['user_email'],
				'purchase_key'    => $purchase_data['purchase_key'],
				'currency'        => give_get_currency(),
				'user_info'       => $purchase_data['user_info'],
				'gateway'         => 'paymill',
				'status'          => 'pending'
			);


			if ( give_paymill_is_recurring_purchase( $purchase_data ) && ( ! empty( $customer ) || $customer_exists ) ) {

				// Process a recurring subscription purchase
				$plan_id = give_paymill_get_plan_id( $purchase_data );

				$payment_params = array(
					'client' => $customer_id,
					'token'  => sanitize_text_field( $_POST['paymillToken'] )
				);

				$payment = $paymentsObject->create( $payment_params );

				$sub_params = array(
					'client'  => $customer_id,
					'offer'   => $plan_id,
					'payment' => $payment['id']
				);

				$subscription = $subscriptionsObject->create( $sub_params );

				// If there is an error creating the subscription, return to checkout
				if ( ! empty( $subscription['error'] ) ) {
					give_set_error( 'paymill_error', sprintf( __( 'Merchant Error: %s', 'give_paymill' ), $subscription['error'] ) );
					give_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['give-gateway'] );
				}

				// record the pending payment
				$payment = give_insert_payment( $payment_data );

				update_user_meta( $user->ID, '_paymill_sub_id', $subscription['id'] );

				// Set user as subscriber
				Give_Recurring_Customer::set_as_subscriber( $user->ID );

				// store the customer recurring ID
				Give_Recurring_Customer::set_customer_id( $user->ID, $customer_id );

				// Set the customer status
				Give_Recurring_Customer::set_customer_status( $user->ID, 'active' );

				// Calculate the customer's new expiration date
				$new_expiration = Give_Recurring_Customer::calc_user_expiration( $user->ID, $payment );

				// Set the customer's new expiration date
				Give_Recurring_Customer::set_customer_expiration( $user->ID, $new_expiration );

				// Store the parent payment ID in the user meta
				Give_Recurring_Customer::set_customer_payment_id( $user->ID, $payment );

				$recurring_signup = true;

			} elseif ( ! empty( $customer ) || $customer_exists ) {

				// Process a normal one-time charge purchase
				$transaction_params = array(
					'amount'      => $purchase_data['price'] * 100, // amount in cents
					'currency'    => strtoupper( $give_options['currency'] ),
					'token'       => sanitize_text_field( $_POST['paymillToken'] ),
					'client'      => $customer_id,
					'description' => give_get_purchase_summary( $purchase_data )
				);

				$transaction = $transactionsObject->create( $transaction_params );

				// record the pending payment
				$payment = give_insert_payment( $payment_data );

			} else {

				give_record_gateway_error( __( 'Customer Creation Failed', 'give_paymill' ), sprintf( __( 'Customer creation failed while processing a payment. Payment Data: %s', ' give_paymill' ), json_encode( $payment_data ) ), $payment );

			}

			if ( ( isset( $transaction['id'] ) && $transaction['status'] == 'closed' ) || ( $recurring_signup && isset( $subscription['id'] ) ) ) {

				give_update_payment_status( $payment, 'publish' );

				if ( ! empty( $charge ) ) {
					give_insert_payment_note( $payment, 'Paymill Transaction ID: ' . $transaction['id'] );
				} elseif ( ! empty( $customer_id ) ) {
					give_insert_payment_note( $payment, 'Paymill Client ID: ' . $customer_id );
				}

				give_send_to_success_page();

			} else {

				give_record_gateway_error( __( 'Paymill Error', 'edd' ), sprintf( __( 'Payment creation failed or payment not verified. Transaction details: ', 'edd' ), json_encode( $transaction ) ) );
				give_set_error( 'payment_not_recorded', __( 'Your payment could not be recorded, please contact the site administrator.', 'give_paymill' ) );
				// if errors are present, send the user back to the purchase page so they can be corrected
				give_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['give-gateway'] );

			}

		}
		catch ( Exception $e ) {
			give_record_gateway_error( __( 'Paymill Error', 'edd' ), sprintf( __( 'There was an error encountered while processing the payment. Error details: ', 'edd' ), json_encode( $e ) ) );
			give_set_error( 'payment_error', __( 'There was an error processing your payment, please ensure you have entered your card number correctly.', 'give_paymill' ) );
			give_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['give-gateway'] );
		}
	} else {
		give_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['give-gateway'] );
	}
}

add_action( 'give_gateway_paymill', 'give_paymill_process_paymill_payment' );


/**
 * Create recurring payment plans when Give Forms are saved
 *
 * This is in order to support the Recurring Payments module
 *
 * @access      public
 * @since       1.0
 * @return      int
 */

function give_paymill_create_recurring_plans( $post_id = 0 ) {
	global $give_options, $post;

	if ( ! class_exists( 'Give_Recurring' ) ) {
		return $post_id;
	}

	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || isset( $_REQUEST['bulk_edit'] ) ) {
		return $post_id;
	}

	if ( isset( $post->post_type ) && $post->post_type == 'revision' ) {
		return $post_id;
	}

	if ( ! isset( $post->post_type ) || $post->post_type != 'give_forms' ) {
		return $post_id;
	}

	if ( ! current_user_can( 'edit_product', $post_id ) ) {
		return $post_id;
	}

	if ( ! class_exists( 'Services_Paymill_Base' ) ) {
		require_once GIVE_PAYMILL_PLUGIN_DIR . '/Paymill/Offers.php';
	}

	if ( give_is_test_mode() ) {
		$apiKey      = isset( $give_options['paymill_test_key'] ) ? trim( $give_options['paymill_test_key'] ) : false;
		$apiEndpoint = 'https://api.paymill.de/v2/';
	} else {
		$apiKey      = isset( $give_options['paymill_live_key'] ) ? trim( $give_options['paymill_live_key'] ) : false;
		$apiEndpoint = 'https://api.paymill.de/v2/';
	}

	$plans = array();
	if ( ! $apiKey ) {
		return;
	}

	try {

		$offers = new Services_Paymill_Offers( $apiKey, $apiEndpoint );

		if ( give_has_variable_prices( $post_id ) ) {

			$prices = give_get_variable_prices( $post_id );
			foreach ( $prices as $price_id => $price ) {

				if ( Give_Recurring()->is_price_recurring( $post_id, $price_id ) ) {

					$period = Give_Recurring()->get_period( $price_id, $post_id );

					if ( $period == 'day' ) {
						wp_die( __( 'Paymill only permits yearly, monthly, and weekly plans.', 'give_paymill' ), __( 'Error', 'give_paymill' ) );
					}

					if ( Give_Recurring()->get_times( $price_id, $post_id ) > 0 ) {
						wp_die( __( 'Paymill requires that the Times option be set to 0.', 'give_paymill' ), __( 'Error', 'give_paymill' ) );
					}

					$plans[] = array(
						'name'     => sanitize_key( $price['name'] ),
						'price'    => $price['amount'],
						'period'   => $period,
						'price_id' => $price_id
					);

				}
			}

		} else {

			if ( Give_Recurring()->is_recurring( $post_id ) ) {

				$period = Give_Recurring()->get_period_single( $post_id );

				if ( $period == 'day' ) {
					wp_die( __( 'Paymill only permits yearly, monthly, and weekly plans.', 'give_paymill' ), __( 'Error', 'give_paymill' ) );
				}

				if ( Give_Recurring()->get_times_single( $post_id ) > 0 ) {
					wp_die( __( 'Paymill requires that the Times option be set to 0.', 'give_paymill' ), __( 'Error', 'give_paymill' ) );
				}

				$plans[] = array(
					'name'   => sanitize_key( get_post_field( 'post_name', $post_id ) ),
					'price'  => give_get_form_price( $post_id ),
					'period' => $period
				);
			}
		}

		// Get all plans so we know which ones already exist
		$all_plans = $offers->get();
		$all_plans = wp_list_pluck( $all_plans, "name" );

		foreach ( $plans as $plan ) {

			// Create the plan ID
			$plan_id = $post_id . '_' . $plan['name'];
			$plan_id = apply_filters( 'give_paymill_recurring_plan_id', $plan_id, $plan );

			if ( in_array( $plan_id, $all_plans ) ) {
				continue;
			}

			$params = array(
				'amount'   => $plan['price'] * 100,
				'currency' => strtoupper( $give_options['currency'] ),
				'interval' => '1 ' . strtoupper( $plan['period'] ),
				'name'     => $plan_id
			);

			$offer = $offers->create( $params );

			if ( give_has_variable_prices( $post_id ) ) {
				foreach ( $prices as $price_id => $price ) {
					if ( $plan_id == $post_id . '_' . sanitize_key( $price['name'] ) ) {
						update_post_meta( $post_id, '_paymill_offer_id_price_' . (int) $price_id, $offer['id'] );
					}
				}
			} else {
				update_post_meta( $post_id, '_paymill_offer_id', $offer['id'] );
			}

		}
	}
	catch ( Exception $e ) {
		wp_die( __( 'There was an error creating a payment plan with Paymill.', 'give_paymill' ), __( 'Error', 'give_paymill' ) );
	}
}

add_action( 'save_post', 'give_paymill_create_recurring_plans', 999 );


/**
 * Detect if the current purchase is for a recurring product
 *
 * @access      public
 * @since       1.5
 * @return      bool
 */

function give_paymill_is_recurring_purchase( $purchase_data ) {

	if ( ! class_exists( 'Give_Recurring' ) ) {
		return false;
	}

	if ( Give_Recurring()->is_purchase_recurring( $purchase_data ) ) {
		return true;
	}

	return false;
}


/**
 * Retrieve the plan ID from the purchased items
 *
 * @access      public
 * @since       1.0
 * @return      string|bool
 */

function give_paymill_get_plan_id( $purchase_data ) {
	foreach ( $purchase_data['downloads'] as $download ) {

		if ( give_has_variable_prices( $download['id'] ) ) {

			$prices  = give_get_variable_prices( $download['id'] );
			$plan_id = get_post_meta( $download['id'], '_paymill_offer_id_price_' . (int) $download['options']['price_id'], true );

		} else {

			$plan_id = get_post_meta( $download['id'], '_paymill_offer_id', true );

		}

		return $plan_id;
	}
}


/**
 * Filter the Recurring Payments cancellation link
 *
 * @access      public
 * @since       1.0
 * @return      string
 */

function give_paymill_recurring_cancel_link( $link = '', $user_id = 0 ) {

	$customer_id = get_user_meta( $user_id, '_paymill_sub_id', true );

	// Only modify Stripe customer's cancellation links
	if ( strpos( $customer_id, 'sub_' ) === false ) {
		return $link;
	}

	$cancel_url = wp_nonce_url( add_query_arg( array(
		'give_action' => 'cancel_recurring_paymill_customer',
		'customer_id' => $customer_id,
		'user_id'     => $user_id
	) ), 'give_paymill_cancel' );
	$link       = '<a href="%s" class="give-recurring-cancel" title="%s">%s</a>';
	$link       = sprintf(
		$link,
		$cancel_url,
		__( 'Cancel your subscription', 'give-recurring' ),
		empty( $atts['text'] ) ? __( 'Cancel Subscription', 'give-recurring' ) : esc_html( $atts['text'] )
	);

	$link .= '<script type="text/javascript">jQuery(document).ready(function($) {$(".give-recurring-cancel").on("click", function() { if(confirm("' . __( "Do you really want to cancel your subscription? You will retain access for the length of time you have paid for.", "give_paymill" ) . '")) {return true;}return false;});});</script>';

	return $link;

}

add_filter( 'give_recurring_cancel_link', 'give_paymill_recurring_cancel_link', 10, 2 );


/**
 * Process a recurring payments cancellation
 *
 * @access      public
 * @since       1.0
 * @return      void
 */

function give_paymill_cancel_subscription( $data ) {
	if ( wp_verify_nonce( $data['_wpnonce'], 'give_paymill_cancel' ) ) {

		global $give_options;

		if ( ! class_exists( 'Services_Paymill_Subscriptions' ) ) {
			require_once GIVE_PAYMILL_PLUGIN_DIR . '/Paymill/Subscriptions.php';
		}

		if ( give_is_test_mode() ) {
			$apiKey      = trim( $give_options['paymill_test_key'] );
			$apiEndpoint = 'https://api.paymill.de/v2/';
		} else {
			$apiKey      = trim( $give_options['paymill_live_key'] );
			$apiEndpoint = 'https://api.paymill.de/v2/';
		}

		$subscriptionsObject = new Services_Paymill_Subscriptions( $apiKey, $apiEndpoint );

		try {

			$subscription = $subscriptionsObject->delete( urldecode( $data['customer_id'] ) );

			Give_Recurring_Customer::set_customer_status( $data['user_id'], 'cancelled' );

			wp_redirect(
				add_query_arg(
					'subscription',
					'cancelled',
					remove_query_arg( array( 'give_action', 'customer_id', 'user_id', '_wpnonce' ) )
				)
			);
			exit;

		}
		catch ( Exception $e ) {
			wp_die( '<pre>' . $e . '</pre>', __( 'Error', 'give_paymill' ) );
		}

	}
}

add_action( 'give_cancel_recurring_paymill_customer', 'give_paymill_cancel_subscription' );


/**
 * Listen for Paymill events, primarily recurring payments
 *
 * @access      public
 * @since       1.0
 * @return      void
 */

function give_paymill_event_listener() {

	if ( ! class_exists( 'Give_Recurring' ) ) {
		return;
	}

	if ( isset( $_GET['give-listener'] ) && $_GET['give-listener'] == 'paymill' ) {

		global $give_options;

		if ( ! class_exists( 'Services_Paymill_Subscriptions' ) ) {
			require_once GIVE_PAYMILL_PLUGIN_DIR . '/Paymill/Subscriptions.php';
		}

		if ( give_is_test_mode() ) {
			$apiKey      = trim( $give_options['paymill_test_key'] );
			$apiEndpoint = 'https://api.paymill.de/v2/';
		} else {
			$apiKey      = trim( $give_options['paymill_live_key'] );
			$apiEndpoint = 'https://api.paymill.de/v2/';
		}


		// retrieve the request's body and parse it as JSON
		$body       = @file_get_contents( 'php://input' );
		$event_json = json_decode( $body );
		$event      = $event_json->event;

		switch ( $event->event_type ) :

			case 'subscription.succeeded' :

				// Process a subscription payment

				$subscription = $event->event_resource->subscription;
				$transaction  = $event->event_resource->transaction;

				// retrieve the customer who made this payment (only for subscriptions)
				$user_id = Give_Recurring_Customer::get_user_id_by_customer_id( $subscription->client );

				// check to confirm this is a stripe subscriber
				if ( $user_id ) {

					// Retrieve the original payment details
					$parent_payment_id = Give_Recurring_Customer::get_customer_payment_id( $user_id );
					$customer_email    = give_get_payment_user_email( $parent_payment_id );

					// Store the payment
					Give_Recurring()->record_subscription_payment( $parent_payment_id, $transaction->amount / 100, $transaction->id );

					// Set the customer's status to active
					Give_Recurring_Customer::set_customer_status( $user_id, 'active' );

					// Calculate the customer's new expiration date
					$new_expiration = Give_Recurring_Customer::calc_user_expiration( $user_id, $parent_payment_id );

					// Set the customer's new expiration date
					Give_Recurring_Customer::set_customer_expiration( $user_id, $new_expiration );

				}

				break;

			case 'subscription.deleted' :

				// Process a cancellation

				$subscription = $event->event_resource->subscription;

				// retrieve the customer who made this payment (only for subscriptions)
				$user_id = Give_Recurring_Customer::get_user_id_by_customer_id( $subscription->client );

				$parent_payment_id = Give_Recurring_Customer::get_customer_payment_id( $user_id );

				// Set the customer's status to active
				Give_Recurring_Customer::set_customer_status( $user_id, 'cancelled' );

				give_update_payment_status( $parent_payment_id, 'cancelled' );

				break;

		endswitch;

		exit;

	}
}

add_action( 'init', 'give_paymill_event_listener' );


// adds the settings to the Payment Gateways section
function give_paymill_add_settings( $settings ) {

	$paymill_settings = array(
		array(
			'name' => '<strong>' . __( 'Paymill Settings', 'give_paymill' ) . '</strong>',
			'desc' => '<hr>',
			'id'   => 'give_title_paymill',
			'type' => 'give_title'
		),
		array(
			'name' => __( 'Live Private Key', 'give_paymill' ),
			'desc' => __( 'Enter your live API key, found in your Paymill Account Settings', 'give_paymill' ),
			'id'   => 'paymill_live_key',
			'type' => 'text',
		),
		array(
			'id'   => 'paymill_live_public_key',
			'name' => __( 'Live Public Key', 'give_paymill' ),
			'desc' => __( 'Enter your live public API key, found in your Paymill Account Settings', 'give_paymill' ),
			'type' => 'text',
		),
		array(
			'id'   => 'paymill_test_key',
			'name' => __( 'Test Private Key', 'give_paymill' ),
			'desc' => __( 'Enter your test API key, found in your Paymill Account Settings', 'give_paymill' ),
			'type' => 'text',
		),
		array(
			'id'   => 'paymill_test_public_key',
			'name' => __( 'Test Public Key', 'give_paymill' ),
			'desc' => __( 'Enter your test public API key, found in your Paymill Account Settings', 'give_paymill' ),
			'type' => 'text',
		)
	);

	return array_merge( $settings, $paymill_settings );
}

add_filter( 'give_settings_gateways', 'give_paymill_add_settings' );


function give_paymill_js() {
	global $give_options;

	$publishable_key = null;

	if ( give_is_test_mode() ) {
		$publishable_key = isset( $give_options['paymill_test_public_key'] ) ? trim( $give_options['paymill_test_public_key'] ) : '';
	} else {
		$publishable_key = isset( $give_options['paymill_live_public_key'] ) ? trim( $give_options['paymill_live_public_key'] ) : '';
	}

	wp_enqueue_script( 'paymill-js', 'https://bridge.paymill.com', array( 'jquery' ) );
	wp_enqueue_script( 'give-paymill-js', GIVE_PAYMILL_PLUGIN_URL . 'give-paymill.js', array(
		'jquery',
		'paymill-js'
	), GIVE_PAYMILL_VERSION );

	$paymill_vars = array(
		'currency'  => strtoupper( $give_options['currency'] )
	);

	wp_localize_script( 'give-paymill-js', 'give_paymill_vars', $paymill_vars );

}

add_action( 'wp_enqueue_scripts', 'give_paymill_js', 100 );

function give_paymill_public_key() {

	global $give_options;

	if ( give_is_test_mode() ) {
		$public_key = isset( $give_options['paymill_test_public_key'] ) ? trim( $give_options['paymill_test_public_key'] ) : '';
	} else {
		$public_key = isset( $give_options['paymill_live_public_key'] ) ? trim( $give_options['paymill_live_public_key'] ) : '';
	}

	echo '<script type="text/javascript">var PAYMILL_PUBLIC_KEY = "' . $public_key . '";</script>';
}

add_action( 'wp_head', 'give_paymill_public_key' );