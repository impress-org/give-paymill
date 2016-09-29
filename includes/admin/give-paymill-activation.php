<?php
/**
 * Give Paymill Gateway Activation
 *
 * @package     Give
 * @copyright   Copyright (c) 2016, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Give Paymill Activation Banner
 *
 * Includes and initializes Give activation banner class.
 *
 * @since 1.1
 */
function give_paymill_activation_banner() {

    // Check for if give plugin activate or not.
    $is_give_active = defined( 'GIVE_PLUGIN_BASENAME' ) ? is_plugin_active( GIVE_PLUGIN_BASENAME ) : false ;

	//Check to see if Give is activated, if it isn't deactivate and show a banner
	if ( is_admin() && current_user_can( 'activate_plugins' ) && ! $is_give_active ) {

		add_action( 'admin_notices', 'give_paymill_activation_notice' );

		//Don't let this plugin activate
		deactivate_plugins( plugin_basename( __FILE__ ) );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		return false;

	}

	//Check for activation banner inclusion
	$activation_banner_file = GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php';
	if ( ! class_exists( 'Give_Addon_Activation_Banner' ) && file_exists( $activation_banner_file ) ) {
		include $activation_banner_file;
	}

	//Only runs on admin
	$args = array(
		'file'              => __FILE__,
		'name'              => esc_html__( 'Paymill Gateway', 'give-paymill' ),
		'version'           => GIVE_PAYMILL_VERSION,
		'settings_url'      => admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways' ),
		'documentation_url' => 'https://givewp.com/documentation/add-ons/paymill-gateway/',
		'support_url'       => 'https://givewp.com/support/',
		'testing'           => false
	);

	new Give_Addon_Activation_Banner( $args );

	return false;

}

add_action( 'admin_init', 'give_paymill_activation_banner' );

/**
 * Notice for No Core Activation
 *
 * @since 1.1
 */
function give_paymill_activation_notice() {
	echo '<div class="error"><p>' . __( '<strong>Activation Error:</strong> We noticed Give is not active. Please activate Give in order to use the Paymill Gateway.', 'give-paymill' ) . '</p></div>';
}