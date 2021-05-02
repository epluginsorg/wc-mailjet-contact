<?php
/**
 * Plugin Name: WC Mailjet Contact
 * Plugin URI:
 * Description: Create contact on the mailjet when order is placed and after sucessful creation of the contact send mail to the admin
 * Version: 1.0.0
 * Author: Mohit Mishra
 * Author URI: https://example.com/
 * Requires at least: 4.9.0
 * Tested up to: 5.7.1
 * WC requires at least: 3.3.0
 * WC tested up to: 5.2.2
 *
 * Text Domain: wc-mailjet-contact
 * Domain Path: /languages/
 *
 * Plugin URI:        https://example.com/
 *
 * @package           WC Mailjet Contact
 * Author URI:        https://example.com/
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WC_Mailjet_Contact' ) ) {
	class WC_Mailjet_Contact {
		/**
		 * Plugin Path
		 *
		 * @var string $path
		 */
		private static $plugin_path = '';
		/**
		 * Plugin Name
		 *
		 * @var string $plugin_name
		 */
		private static $plugin_name = 'wc-mailjet-contact';
		/**
		 * Plugin Version
		 *
		 * @var string $version
		 */
		private static $version = '1.0.0';
		/**
		 * Plugin URL
		 *
		 * @var string $url
		 */
		private static $plugin_url = '';

		/**
		 * Cloning is forbidden.
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'wc-mailjet-contact' ) );
		}
		/**
		 * Unserializing instances of this class is forbidden.
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'wc-mailjet-contact' ) );
		}
		/**
		 * Attach hooks and filters.
		 *
		 * @name init
		 * @version 1.0.0
		 */
		public static function init() {
			add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'wcm_woocommerce_settings_tabs_option' ), 50 );
			add_action( 'woocommerce_settings_tabs_' . self::$plugin_name, array( __CLASS__, 'wcm_program_settings_tab' ) );
			add_action( 'woocommerce_settings_save_' . self::$plugin_name, array( __CLASS__, 'wcm_program_settings_save' ) );
			add_action( 'woocommerce_thankyou', array( __CLASS__, 'wcm_send_api_create_contact' ), 10 );
		}

		/**
		 * Register a new tab into the WooCommerce
		 *
		 * @version 1.0.0
		 * @name wcm_woocommerce_settings_tabs_option
		 * @param array $settings_tabs  All settings tabs.
		 */
		public static function wcm_woocommerce_settings_tabs_option( $settings_tabs ) {
			$settings_tabs[ self::$plugin_name ] = esc_html__( 'Mailjet', 'wc-mailjet-contact' );

			return $settings_tabs;
		}

		/**
		 * Display the html of each sections using Setting API.
		 *
		 * @name wcm_get_seetings
		 * @since    1.0.0
		 */
		public static function wcm_get_seetings() {
			$settings = array(
				array(
					'title' => esc_html__( 'General Setting', 'wc-mailjet-contact' ),
					'type'  => 'title',
					'id'    => 'wc_mail_contact',
				),
				array(
					'title'    => esc_html__( 'Mailjet API URL', 'wc-mailjet-contact' ),
					'type'     => 'text',
					'id'       => 'wc_mailjet_api_url',
					'class'    => 'wc_mailjet_input_val',
					'desc_tip' => __( 'Set the url for the mailjet.', 'wc-mailjet-contact' ),
				),
				array(
					'title'    => esc_html__( 'Mailjet API Key', 'wc-mailjet-contact' ),
					'type'     => 'text',
					'id'       => 'wc_mailjet_api_key',
					'class'    => 'wc_mailjet_input_val',
					'desc_tip' => __( 'Set the api key for the mailjet.', 'wc-mailjet-contact' ),
				),
				array(
					'title'    => esc_html__( 'Mailjet Secert Key', 'wc-mailjet-contact' ),
					'type'     => 'text',
					'id'       => 'wc_mailjet_secret_key',
					'class'    => 'wc_mailjet_input_val',
					'desc_tip' => __( 'Set the secert for the mailjet.', 'wc-mailjet-contact' ),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'wc_mail_contact',
				),
			);
			$settings = apply_filters( 'wc_mailjet_settings', $settings );
			return $settings;
		}

		/**
		 * Save the data using Setting API
		 *
		 * @since    1.0.0
		 * @name wcm_program_settings_save
		 */
		public static function wcm_program_settings_save() {
			global $current_section;
			WC_Admin_Settings::save_fields( self::wcm_get_seetings() );
		}

		/**
		 * This function will display the settings.
		 *
		 * @since    1.0.0
		 * @name wcm_program_settings_tab
		 */
		public static function wcm_program_settings_tab() {
			woocommerce_admin_fields( self::wcm_get_seetings() );
		}
		/**
		 * Create a contact into the mailjet
		 *
		 * @since 1.0.0
		 * @name wcm_send_api_create_contact
		 * @param int $order_id ID of the WC_Order.
		 */
		public static function wcm_send_api_create_contact( $order_id ) {
			$order = wc_get_order( $order_id );
			$first_name = $order->get_billing_first_name();
			$last_name = $order->get_billing_last_name();
			$body = array(
				'name'  => $first_name,
				'email' => $order->get_billing_email(),
			);
			$response = wp_remote_post(
				self::wc_get_mailget_url_key(),
				array(
					'body'    => $body,
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( self::wc_get_mailget_api_key() . ':' . self::wc_get_mailget_secert_key() ),
					),
				)
			);
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				echo esc_html( "Something went wrong: $error_message" );
			} else {
				if ( ! empty( $response['response']['code'] ) && '201' == $response['response']['code'] ) {

					$to = self::wcm_get_admin_email();
					$subject = __( 'Contact exported sucessfully', 'wc-mailjet-contact' );
					$email_body = __( 'First name: ', 'wc-mailjet-contact' ) . $first_name . __( 'Last Name:', 'wc-mailjet-contact' ) . $last_name . __( 'Order Id:', 'wc-mailjet-contact' ) . $order_id;

					$headers = array( 'Content-Type: text/html; charset=UTF-8' );

					wp_mail( $to, $subject, $email_body, $headers );
				}
			}
		}

		/**
		 * Send mail to the admin
		 *
		 * @name wcm_get_admin_email
		 * @version 1.0.0
		 */
		public static function wcm_get_admin_email() {
			$admin_email = get_option( 'admin_email', false );
			return $admin_email;
		}
		/**
		 * Get API key
		 *
		 * @name wc_get_mailget_api_key
		 * @version 1.0.0
		 */
		public static function wc_get_mailget_api_key() {
			$api_key = get_option( 'wc_mailjet_api_key', false );
			return $api_key;
		}

		/**
		 * Get API key
		 *
		 * @name wc_get_mailget_secert_key
		 * @version 1.0.0
		 */
		public static function wc_get_mailget_secert_key() {
			$api_secert_key = get_option( 'wc_mailjet_secret_key', false );
			return $api_secert_key;
		}

		/**
		 * Get API key
		 *
		 * @name wc_get_mailget_api_key
		 * @version 1.0.0
		 */
		public static function wc_get_mailget_url_key() {
			$api_url = get_option( 'wc_mailjet_api_url', false );
			return $api_url;
		}
	}
}

add_action( 'plugins_loaded', array( 'WC_Mailjet_Contact', 'init' ) );

/**
 * Create Settings for the Plugin
 *
 * @name wc_get_mailget_settings_link
 * @since 1.0.0
 * @param array $settings_links  array of the link.
 */
function wc_get_mailget_settings_link( $settings_links ) {

	$settings_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=wc-mailjet-contact' ) . '">' . esc_html__( 'Settings', 'wc-mailjet-contact' ) . '</a>';
	return $settings_links;
}

// Add settings link on plugin page.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_get_mailget_settings_link' );
