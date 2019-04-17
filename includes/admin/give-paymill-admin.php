<?php
/**
 * Admin Functions
 *
 * @package     Give
 * @copyright   Copyright (c) 2019, GiveWP
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Proceed only, if class Give_Paymill_Admin_Settings not exists.
 *
 * @since 1.1.0
 */
if ( ! class_exists( 'Give_Paymill_Admin_Settings' ) ) {

	/**
	 * Class Give_Paymill_Admin_Settings
	 *
	 * @since 1.1.0
	 */
	class Give_Paymill_Admin_Settings {


		/**
		 * Give_Paymill_Admin_Settings constructor.
		 *
		 * @since  1.1.0
		 * @access public
		 */
		public function __construct() {

			add_filter( 'give_get_sections_gateways', array( $this, 'register_sections' ) );
			add_action( 'give_get_settings_gateways', array( $this, 'register_settings' ) );
		}


		/**
		 * Register Admin Settings.
		 *
		 * @param array $settings List of admin settings.
		 *
		 * @since  1.1.0
		 * @access public
		 *
		 * @return array
		 */
		function register_settings( $settings ) {

			switch ( give_get_current_setting_section() ) {

				case 'paymill':

					$settings = array(
						array(
							'id'   => 'give_title_paymill',
							'type' => 'title',
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
						),
						array(
							'id'   => 'give_title_paymill',
							'type' => 'sectionend',
						),
					);

					break;

			}// End switch().

			return $settings;
		}


		/**
		 * Register Section for Gateway Settings.
		 *
		 * @param array $sections List of sections.
		 *
		 * @since  1.1.0
		 * @access public
		 *
		 * @return mixed
		 */
		public function register_sections( $sections ) {

			$sections['paymill'] = __( 'Paymill', 'give-paymill' );

			return $sections;
		}


	}
}

new Give_Paymill_Admin_Settings();
