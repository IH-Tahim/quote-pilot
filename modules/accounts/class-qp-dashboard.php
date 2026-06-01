<?php
/**
 * Customer Dashboard shortcode handler.
 *
 * Registers the `[quotepilot_dashboard]` shortcode. Handles guest login,
 * customer registration, and renders the customer account portal containing
 * booking history, status updates, and itemised pricing breakdowns.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Dashboard' ) ) :

	/**
	 * Class QP_Dashboard
	 */
	class QP_Dashboard {

		/**
		 * Register the shortcode and form action handlers.
		 *
		 * @return void
		 */
		public static function register() {
			add_shortcode( 'quotepilot_dashboard', array( __CLASS__, 'render' ) );
			add_action( 'template_redirect', array( __CLASS__, 'handle_auth' ) );
		}

		/**
		 * Handle front-end login, registration, and logout POST requests.
		 *
		 * @return void
		 */
		public static function handle_auth() {
			if ( is_admin() ) {
				return;
			}

			// Handle Logout
			if ( isset( $_GET['qp_action'] ) && 'logout' === $_GET['qp_action'] ) {
				wp_logout();
				wp_safe_redirect( remove_query_arg( 'qp_action' ) );
				exit;
			}

			if ( empty( $_POST['qp_action'] ) ) {
				return;
			}

			// Verify Nonce
			if ( ! isset( $_POST['qp_nonce'] ) || ! wp_verify_nonce( $_POST['qp_nonce'], 'qp_dashboard_action' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'quote-pilot' ) );
			}

			global $qp_dashboard_error, $qp_dashboard_success;
			$qp_dashboard_error   = '';
			$qp_dashboard_success = '';

			$action = sanitize_text_field( wp_unslash( $_POST['qp_action'] ) );

			// 1. HANDLE LOGIN
			if ( 'login' === $action ) {
				$username = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
				$password = isset( $_POST['pwd'] ) ? $_POST['pwd'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw passwords should not be sanitized.
				$remember = ! empty( $_POST['rememberme'] );

				if ( empty( $username ) || empty( $password ) ) {
					$qp_dashboard_error = esc_html__( 'Please enter both your email/username and password.', 'quote-pilot' );
					return;
				}

				$creds = array(
					'user_login'    => $username,
					'user_password' => $password,
					'remember'      => $remember,
				);

				$user = wp_signon( $creds, is_ssl() );

				if ( is_wp_error( $user ) ) {
					$qp_dashboard_error = $user->get_error_message();
				} else {
					// Redirect to clear POST payload and refresh view
					wp_safe_redirect( add_query_arg( 'qp_msg', 'login_success' ) );
					exit;
				}
			}

			// 2. HANDLE REGISTRATION
			if ( 'register' === $action ) {
				$name             = isset( $_POST['reg_name'] ) ? sanitize_text_field( wp_unslash( $_POST['reg_name'] ) ) : '';
				$email            = isset( $_POST['reg_email'] ) ? sanitize_email( wp_unslash( $_POST['reg_email'] ) ) : '';
				$password         = isset( $_POST['reg_password'] ) ? $_POST['reg_password'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw password.
				$confirm_password = isset( $_POST['reg_confirm_password'] ) ? $_POST['reg_confirm_password'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw password.

				if ( empty( $name ) || empty( $email ) || empty( $password ) ) {
					$qp_dashboard_error = esc_html__( 'All fields are required.', 'quote-pilot' );
					return;
				}

				if ( ! is_email( $email ) ) {
					$qp_dashboard_error = esc_html__( 'Please enter a valid email address.', 'quote-pilot' );
					return;
				}

				if ( $password !== $confirm_password ) {
					$qp_dashboard_error = esc_html__( 'Passwords do not match.', 'quote-pilot' );
					return;
				}

				if ( email_exists( $email ) ) {
					$qp_dashboard_error = esc_html__( 'An account with this email already exists. Please log in.', 'quote-pilot' );
					return;
				}

				// Create the user
				$username = $email; // Use email as the username
				$user_id  = wp_create_user( $username, $password, $email );

				if ( is_wp_error( $user_id ) ) {
					$qp_dashboard_error = $user_id->get_error_message();
				} else {
					// Set the Customer Role
					$user = new WP_User( $user_id );
					$user->set_role( 'qp_customer' );

					// Save customer name as display name / first name
					wp_update_user(
						array(
							'ID'           => $user_id,
							'display_name' => $name,
							'first_name'   => $name,
						)
					);

					// Linked bookings logic is automatically handled via user_register hook in QP_Email_Link!

					// Log the user in atomically
					$creds = array(
						'user_login'    => $email,
						'user_password' => $password,
						'remember'      => true,
					);
					wp_signon( $creds, is_ssl() );

					wp_safe_redirect( add_query_arg( 'qp_msg', 'register_success' ) );
					exit;
				}
			}
		}

		/**
		 * Render the customer dashboard markup.
		 *
		 * @return string HTML shortcode output.
		 */
		public static function render() {
			// Enqueue dashicons for dashboard icons
			wp_enqueue_style( 'dashicons' );

			// Enqueue quote calculator CSS if not already enqueued (shares look-and-feel variables)
			wp_enqueue_style(
				'qp-quote-calculator',
				QP_PLUGIN_URL . 'public/css/quote-calculator.css',
				array(),
				QP_VERSION
			);

			// Load the view in a buffered scope
			ob_start();
			include QP_PLUGIN_DIR . 'modules/accounts/views/dashboard.php';
			return ob_get_clean();
		}
	}

endif;
