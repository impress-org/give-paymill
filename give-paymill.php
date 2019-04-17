<?php
/**
 * Plugin Name: Give - Paymill Gateway
 * Plugin URI:  https://givewp.com/addons/paymill-gateway/
 * Description: Process online donations via the Paymill payment gateway.
 * Version:     1.1.1
 * Author:      GiveWP
 * Author URI:  https://givewp.com
 * Text Domain: give-paymill
 * Domain Path: /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
if ( ! defined( 'GIVE_PAYMILL_VERSION' ) ) {
	define( 'GIVE_PAYMILL_VERSION', '1.1.1' );
}
if ( ! defined( 'GIVE_PAYMILL_MIN_GIVE_VERSION' ) ) {
	define( 'GIVE_PAYMILL_MIN_GIVE_VERSION', '2.0.0' );
}
if ( ! defined( 'GIVE_PAYMILL_PLUGIN_DIR' ) ) {
	define( 'GIVE_PAYMILL_PLUGIN_DIR', dirname( __FILE__ ) );
}
if ( ! defined( 'GIVE_PAYMILL_PLUGIN_URL' ) ) {
	define( 'GIVE_PAYMILL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'GIVE_PAYMILL_BASENAME' ) ) {
	define( 'GIVE_PAYMILL_BASENAME', plugin_basename( __FILE__ ) );
}

// Plugin includes
include( GIVE_PAYMILL_PLUGIN_DIR . '/includes/admin/give-paymill-activation.php' );
include( GIVE_PAYMILL_PLUGIN_DIR . '/includes/admin/give-paymill-admin.php' );

/**
 * Paymill Licensing.
 *
 * @access      public
 * @since       1.0
 */
function give_add_paymill_licensing() {
	if ( class_exists( 'Give_License' ) ) {
		new Give_License( __FILE__, 'Paymill Gateway', GIVE_PAYMILL_VERSION, 'WordImpress', 'paymill_license_key' );
	}
}

add_action( 'plugins_loaded', 'give_add_paymill_licensing' );

/**
 * Paymill i18n.
 *
 * @access      public
 * @since       1.0
 */
function give_paymill_textdomain() {

	// Set filter for plugin's languages directory
	$give_lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
	$give_lang_dir = apply_filters( 'give_paymill_languages_directory', $give_lang_dir );

	// Load the translations
	load_plugin_textdomain( 'give-paymill', false, $give_lang_dir );
}

add_action( 'init', 'give_paymill_textdomain' );

/**
 * Processes the payment
 *
 * @param $purchase_data
 */
function give_paymill_process_paymill_payment( $purchase_data ) {

	$give_options = give_get_settings();

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
		give_set_error( 'no_paymill_token', esc_html__( 'Missing token. Please contact support.', 'give-paymill' ) );
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

			// Impossible condition on purpose
			// For reference when building Recurring functionality
			if ( 1 === 2 && ( ! empty( $customer ) || $customer_exists ) ) {

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
					give_set_error( 'paymill_error', sprintf( esc_html__( 'Merchant Error: %s', 'give-paymill' ), $subscription['error'] ) );
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
					'currency'    => give_get_currency(),
					'token'       => sanitize_text_field( $_POST['paymillToken'] ),
					'client'      => $customer_id,
					'description' => give_payment_gateway_donation_summary( $purchase_data )
				);

				$transaction = $transactionsObject->create( $transaction_params );

				// record the pending payment
				$payment = give_insert_payment( $payment_data );

			} else {

				give_record_gateway_error( esc_html__( 'Customer Creation Failed', 'give-paymill' ), sprintf( esc_html__( 'Customer creation failed while processing a payment. Payment Data: %s', 'give-paymill' ), json_encode( $payment_data ) ), '' );

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

				give_record_gateway_error( esc_html__( 'Paymill Error', 'give-paymill' ), sprintf( esc_html__( 'Payment creation failed or payment not verified. Details: %s', 'give-paymill' ), json_encode( $transaction ) ) );
				give_set_error( 'payment_not_recorded', esc_html__( 'Your payment could not be recorded, please contact the site administrator.', 'give-paymill' ) );
				// if errors are present, send the user back to the purchase page so they can be corrected
				give_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['give-gateway'] );

			}

		} catch ( Exception $e ) {
			give_record_gateway_error( esc_html__( 'Paymill Error', 'give-paymill' ), sprintf( esc_html__( 'There was an error encountered while processing the payment. Details: %s', 'give-paymill' ), json_encode( $e ) ) );
			give_set_error( 'payment_error', esc_html__( 'There was an error processing your payment, please ensure you have entered your card number correctly.', 'give-paymill' ) );
			give_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['give-gateway'] );
		}
	} else {
		give_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['give-gateway'] );
	}
}

add_action( 'give_gateway_paymill', 'give_paymill_process_paymill_payment' );


/**
 * Create Recurring Paymill Plans
 *
 * Create recurring payment plans when Give Forms are saved; This is in order to support the Recurring Payments module.
 *
 * @access      public
 * @since       1.0
 *
 * @param int $form_id
 *
 * @return int
 */
function give_paymill_create_recurring_plans( $form_id = 0 ) {
	global $post;

	$give_options = give_get_settings();

	//Safeguards.
	if ( ! class_exists( 'Give_Recurring' ) ) {
		return $form_id;
	}

	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || isset( $_REQUEST['bulk_edit'] ) ) {
		return $form_id;
	}

	if ( isset( $post->post_type ) && $post->post_type == 'revision' ) {
		return $form_id;
	}

	if ( ! isset( $post->post_type ) || $post->post_type != 'give_forms' ) {
		return $form_id;
	}

	if ( ! current_user_can( 'edit_product', $form_id ) ) {
		return $form_id;
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

		if ( give_has_variable_prices( $form_id ) ) {

			$prices = give_get_variable_prices( $form_id );

			foreach ( $prices as $price ) {

				$price_id = $price['_give_id']['level_id'];

				if ( Give_Recurring()->is_recurring( $form_id, $price_id ) ) {

					$period = Give_Recurring()->get_period( $price_id, $form_id );

					if ( $period == 'day' ) {
						wp_die( esc_html__( 'Paymill only permits yearly, monthly, and weekly plans.', 'give-paymill' ), esc_html__( 'Error', 'give-paymill' ) );
					}

					if ( Give_Recurring()->get_times( $price_id, $form_id ) > 0 ) {
						wp_die( esc_html__( 'Paymill requires that the Times option be set to 0.', 'give-paymill' ), esc_html__( 'Error', 'give-paymill' ) );
					}

					$plans[] = array(
						'name'     => sanitize_key( $price['_give_text'] ),
						'price'    => $price['_give_amount'],
						'period'   => $period,
						'price_id' => $price_id
					);

				}
			}

		} else {

			if ( Give_Recurring()->is_recurring( $form_id ) ) {

				$period = $period = Give_Recurring()->get_period( 0, $form_id );

				if ( $period == 'day' ) {
					wp_die( esc_html__( 'Paymill only permits yearly, monthly, and weekly plans.', 'give-paymill' ), esc_html__( 'Error', 'give-paymill' ) );
				}

				if ( Give_Recurring()->get_times( 0, $form_id ) > 0 ) {
					wp_die( esc_html__( 'Paymill requires that the Times option be set to 0.', 'give-paymill' ), esc_html__( 'Error', 'give-paymill' ) );
				}

				$plans[] = array(
					'name'   => sanitize_key( get_post_field( 'post_name', $form_id ) ),
					'price'  => give_get_form_price( $form_id ),
					'period' => $period
				);
			}
		}

		// Get all plans so we know which ones already exist
		$all_plans = $offers->get();
		$all_plans = wp_list_pluck( $all_plans, 'name' );

		foreach ( $plans as $plan ) {

			// Create the plan ID
			$plan_id = $form_id . '_' . $plan['name'];
			$plan_id = apply_filters( 'give_paymill_recurring_plan_id', $plan_id, $plan );

			if ( in_array( $plan_id, $all_plans ) ) {
				continue;
			}

			$params = array(
				'amount'   => $plan['price'] * 100,
				'currency' => give_get_currency(),
				'interval' => '1 ' . strtoupper( $plan['period'] ),
				'name'     => $plan_id
			);

			$offer = $offers->create( $params );

			if ( give_has_variable_prices( $form_id ) ) {

				$prices = give_get_variable_prices( $form_id );

				foreach ( $prices as $price ) {
					$price_id = $price['_give_id']['level_id'];

					if ( $plan_id == $form_id . '_' . sanitize_key( $price['_give_text'] ) ) {
						update_post_meta( $form_id, '_paymill_offer_id_price_' . (int) $price_id, $offer['id'] );
					}
				}
			} else {
				update_post_meta( $form_id, '_paymill_offer_id', $offer['id'] );
			}

		}
	} catch ( Exception $e ) {
		wp_die( esc_html__( 'There was an error creating a payment plan with Paymill.', 'give-paymill' ), esc_html__( 'Error', 'give-paymill' ) );
	}
}

// add_action( 'save_post', 'give_paymill_create_recurring_plans', 999 );

/**
 * Retrieve the plan ID from the purchased items
 *
 * @access      public
 * @since       1.0
 * @return      string|bool
 */
function give_paymill_get_plan_id( $purchase_data ) {

	$form_id  = $purchase_data['post_data']['give-form-id'];
	$price_id = ( isset( $purchase_data['post_data']['give-price-id'] ) ? $purchase_data['post_data']['give-price-id'] : 0 );

	if ( give_has_variable_prices( $form_id ) ) {

		$plan_id = get_post_meta( $form_id, '_paymill_offer_id_price_' . (int) $price_id, true );

	} else {

		$plan_id = get_post_meta( $form_id, '_paymill_offer_id', true );

	}
	$post_meta = get_post_meta( $form_id );

	return $plan_id;

}


/**
 * Filter the Recurring Payments cancellation link.
 *
 * @access      public
 * @since       1.0
 * @return      string
 *
 * @param string $link
 * @param int    $user_id
 *
 * @return string
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
		__( 'Cancel your subscription', 'give-paymill' ),
		empty( $atts['text'] ) ? esc_html__( 'Cancel Subscription', 'give-paymill' ) : esc_html( $atts['text'] )
	);

	$link .= '<script type="text/javascript">jQuery(document).ready(function($) {$(".give-recurring-cancel").on("click", function() { if(confirm("' . esc_html__( "Do you really want to cancel your subscription?", 'give-paymill' ) . '")) {return true;}return false;});});</script>';

	return $link;

}

// add_filter( 'give_recurring_cancel_link', 'give_paymill_recurring_cancel_link', 10, 2 );


/**
 * Process a recurring payments cancellation.
 *
 * @access      public
 * @since       1.0
 * @return      void
 */
function give_paymill_cancel_subscription( $data ) {

	if ( wp_verify_nonce( $data['_wpnonce'], 'give_paymill_cancel' ) ) {

		$give_options = give_get_settings();

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

		} catch ( Exception $e ) {
			wp_die( '<pre>' . $e . '</pre>', esc_html__( 'Error', 'give-paymill' ) );
		}

	}
}

// add_action( 'give_cancel_recurring_paymill_customer', 'give_paymill_cancel_subscription' );


/**
 * Listen for Paymill events, primarily recurring payments.
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

		$give_options = give_get_settings();

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

				// check to confirm this is a paymill subscriber
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


/**
 * Frontend Scripts
 *
 * Enqueue the scripts to the frontend of the website
 */
function give_paymill_js() {
	$give_options = give_get_settings();

	$publishable_key = null;

	if ( give_is_test_mode() ) {
		$publishable_key = isset( $give_options['paymill_test_public_key'] ) ? trim( $give_options['paymill_test_public_key'] ) : '';
	} else {
		$publishable_key = isset( $give_options['paymill_live_public_key'] ) ? trim( $give_options['paymill_live_public_key'] ) : '';
	}

	wp_enqueue_script( 'paymill-js', 'https://bridge.paymill.com', array( 'jquery' ) );
	wp_enqueue_script( 'give-paymill-js', GIVE_PAYMILL_PLUGIN_URL . 'assets/js/give-paymill.js', array(
		'jquery',
		'paymill-js'
	), GIVE_PAYMILL_VERSION );

	$paymill_vars = array(
		'currency' => give_get_currency()
	);

	wp_localize_script( 'give-paymill-js', 'give_paymill_vars', $paymill_vars );

}

add_action( 'wp_enqueue_scripts', 'give_paymill_js', 100 );


/**
 * Frontend Scripts
 *
 * Enqueue the scripts to the frontend of the website
 *
 * @param $hook
 */
function give_paymill_admin_js( $hook ) {

	global $post_type;

	if ( $post_type !== 'give_forms' ) {
		return;
	}

	wp_register_script( 'give-paymill-admin-forms-js', GIVE_PAYMILL_PLUGIN_URL . 'assets/js/give-paymill-admin.js', 'jquery', GIVE_PAYMILL_VERSION );
	wp_enqueue_script( 'give-paymill-admin-forms-js' );

	//Localize strings & variables for JS.
	wp_localize_script( 'give-paymill-admin-forms-js', 'give_admin_paymill_vars', array(
		'give_version'   => GIVE_VERSION,
		'invalid_time'   => __( 'Paymill requires that the Times option be set to 0.', 'give-paymill' ),
		'invalid_period' => __( 'Paymill only permits yearly, monthly, and weekly plans.', 'give-paymill' )
	) );

}

add_action( 'admin_enqueue_scripts', 'give_paymill_admin_js' );

/**
 * Paymill public key.
 */
function give_paymill_public_key() {

	$give_options = give_get_settings();

	if ( give_is_test_mode() ) {
		$public_key = isset( $give_options['paymill_test_public_key'] ) ? trim( $give_options['paymill_test_public_key'] ) : '';
	} else {
		$public_key = isset( $give_options['paymill_live_public_key'] ) ? trim( $give_options['paymill_live_public_key'] ) : '';
	}

	echo '<script type="text/javascript">var PAYMILL_PUBLIC_KEY = "' . $public_key . '";</script>';
}

add_action( 'wp_head', 'give_paymill_public_key' );
