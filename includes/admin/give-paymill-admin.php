<?php
/**
 * Admin Functions
 *
 * @package     Give
 * @copyright   Copyright (c) 2016, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Paymill Gateway
 *
 * @access public
 * @since  1.0
 *
 * @param  array $gateways
 *
 * @return array
 */
function give_paymill_register_gateway( $gateways ) {
	$gateways['paymill'] = array(
		'admin_label'    => esc_html__( 'Paymill', 'give-paymill' ),
		'checkout_label' => esc_html__( 'Credit Card', 'give-paymill' )
	);

	return $gateways;
}

add_filter( 'give_payment_gateways', 'give_paymill_register_gateway' );


/**
 * Register the gateway settings
 *
 * @since 1.0
 *
 * @param array $settings
 *
 * @return array
 */
function give_paymill_add_settings( $settings ) {

	$paymill_settings = array(
		array(
			'name' => '<strong>' . __( 'Paymill', 'give-paymill' ) . '</strong>',
			'desc' => '<hr>',
			'id'   => 'give_title_paymill',
			'type' => 'give_title'
		),
		array(
			'name' => __( 'Live Private Key', 'give-paymill' ),
			'desc' => __( 'Enter your live API key, found in your Paymill Account Settings.', 'give-paymill' ),
			'id'   => 'paymill_live_key',
			'type' => 'api_key',
		),
		array(
			'id'   => 'paymill_live_public_key',
			'name' => __( 'Live Public Key', 'give-paymill' ),
			'desc' => __( 'Enter your live public API key, found in your Paymill Account Settings.', 'give-paymill' ),
			'type' => 'text',
		),
		array(
			'id'   => 'paymill_test_key',
			'name' => __( 'Test Private Key', 'give-paymill' ),
			'desc' => __( 'Enter your test API key, found in your Paymill Account Settings.', 'give-paymill' ),
			'type' => 'api_key',
		),
		array(
			'id'   => 'paymill_test_public_key',
			'name' => __( 'Test Public Key', 'give-paymill' ),
			'desc' => __( 'Enter your test public API key, found in your Paymill Account Settings.', 'give-paymill' ),
			'type' => 'text',
		)
	);

	return array_merge( $settings, $paymill_settings );
}

add_filter( 'give_settings_gateways', 'give_paymill_add_settings' );
