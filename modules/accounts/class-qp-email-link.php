<?php
/**
 * Email Linker for customer accounts.
 *
 * Automatically finds bookings made by a guest using the same email address
 * when they subsequently register an account, and links them to the new user ID.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Email_Link' ) ) :

	/**
	 * Class QP_Email_Link
	 */
	class QP_Email_Link {

		/**
		 * Register user registration hooks.
		 *
		 * @return void
		 */
		public static function register() {
			add_action( 'user_register', array( __CLASS__, 'link_guest_bookings' ), 10, 1 );
		}

		/**
		 * Link existing bookings matching the user's email to their new account.
		 *
		 * @param int $user_id The newly registered user's ID.
		 * @return void
		 */
		public static function link_guest_bookings( $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				return;
			}

			$email = $user->user_email;
			if ( empty( $email ) ) {
				return;
			}

			if ( class_exists( 'QP_Database' ) ) {
				// Fetch bookings matching this email.
				$bookings = QP_Database::get_bookings_by_email( $email );

				if ( ! empty( $bookings ) && is_array( $bookings ) ) {
					foreach ( $bookings as $booking ) {
						// Only link bookings that are currently guest bookings (user_id = 0)
						if ( 0 === (int) $booking->user_id ) {
							QP_Database::update_booking(
								$booking->id,
								array(
									'user_id' => (int) $user_id,
								)
							);
						}
					}
				}
			}
		}
	}

endif;
