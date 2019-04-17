<?php
/**
 * Give Paymill Gateway Activation
 *
 * @package     Give
 * @copyright   Copyright (c) 2016, GiveWP
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
	$is_give_active = defined( 'GIVE_PLUGIN_BASENAME' ) ? is_plugin_active( GIVE_PLUGIN_BASENAME ) : false;

	//Check to see if Give is activated, if it isn't deactivate and show a banner
	if ( current_user_can( 'activate_plugins' ) && ! $is_give_active ) {

		add_action( 'admin_notices', 'give_paymill_activation_notice' );

		//Don't let this plugin activate
		deactivate_plugins( GIVE_PAYMILL_BASENAME );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		return false;

	}


	//Check minimum Give version.
	if ( defined( 'GIVE_VERSION' ) && version_compare( GIVE_VERSION, GIVE_PAYMILL_MIN_GIVE_VERSION, '<' ) ) {

		add_action( 'admin_notices', 'give_paymill_min_version_notice' );

		//Don't let this plugin activate.
		deactivate_plugins( GIVE_PAYMILL_BASENAME );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		return false;

	}

	// Check for activation banner inclusion.
	if (
		! class_exists( 'Give_Addon_Activation_Banner' )
		&& file_exists( GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php' )
	) {
		include GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php';
	}

	// Initialize activation welcome banner.
	if ( class_exists( 'Give_Addon_Activation_Banner' ) ) {

		//Only runs on admin
		$args = array(
			'file'              => __FILE__,
			'name'              => esc_html__( 'Paymill Gateway', 'give-paymill' ),
			'version'           => GIVE_PAYMILL_VERSION,
			'settings_url'      => admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=paymill' ),
			'documentation_url' => 'http://docs.givewp.com/addon-paymill',
			'support_url'       => 'https://givewp.com/support/',
			'testing'           => false
		);

		new Give_Addon_Activation_Banner( $args );
	}

	return false;

}

add_action( 'admin_init', 'give_paymill_activation_banner' );

/**
 * Notice for No Core Activation.
 *
 * @since 1.1
 */
function give_paymill_activation_notice() {
	echo '<div class="error"><p>' . __( '<strong>Activation Error:</strong> You must have the <a href="https://givewp.com/" target="_blank">Give</a> plugin installed and activated for the Paymill add-on to activate.', 'give-paymill' ) . '</p></div>';
}

/**
 * Notice for no core activation.
 *
 * @since 1.1
 */
function give_paymill_min_version_notice() {
	echo '<div class="error"><p>' . sprintf( __( '<strong>Activation Error:</strong> You must have <a href="%s" target="_blank">Give</a> version %s+ for the Paymill add-on to activate.', 'give-paymill' ), 'https://givewp.com', GIVE_PAYMILL_MIN_GIVE_VERSION ) . '</p></div>';
}


/**
 * Plugins row action links
 *
 * @since 1.1
 *
 * @param array $actions An array of plugin action links.
 *
 * @return array An array of updated action links.
 */
function give_paymill_plugin_action_links( $actions ) {
	$new_actions = array(
		'settings' => sprintf(
			'<a href="%1$s">%2$s</a>',
			admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=paymill' ),
			esc_html__( 'Settings', 'give-paymill' )
		),
	);

	return array_merge( $new_actions, $actions );
}

add_filter( 'plugin_action_links_' . GIVE_PAYMILL_BASENAME, 'give_paymill_plugin_action_links' );


/**
 * Plugin row meta links
 *
 * @since 1.1
 *
 * @param array  $plugin_meta An array of the plugin's metadata.
 * @param string $plugin_file Path to the plugin file, relative to the plugins directory.
 *
 * @return array
 */
function give_paymill_plugin_row_meta( $plugin_meta, $plugin_file ) {
	if ( $plugin_file != GIVE_PAYMILL_BASENAME ) {
		return $plugin_meta;
	}

	$new_meta_links = array(
		sprintf(
			'<a href="%1$s" target="_blank">%2$s</a>',
			esc_url( add_query_arg( array(
					'utm_source'   => 'plugins-page',
					'utm_medium'   => 'plugin-row',
					'utm_campaign' => 'admin',
				), 'http://docs.givewp.com/addon-paymill' )
			),
			esc_html__( 'Documentation', 'give-paymill' )
		),
		sprintf(
			'<a href="%1$s" target="_blank">%2$s</a>',
			esc_url( add_query_arg( array(
					'utm_source'   => 'plugins-page',
					'utm_medium'   => 'plugin-row',
					'utm_campaign' => 'admin',
				), 'https://givewp.com/addons/' )
			),
			esc_html__( 'Add-ons', 'give-paymill' )
		),
	);

	return array_merge( $plugin_meta, $new_meta_links );
}

add_filter( 'plugin_row_meta', 'give_paymill_plugin_row_meta', 10, 2 );