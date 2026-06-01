<?php
/**
 * Coupons Admin Interface.
 *
 * Provides administration interface for creating, updating, and deleting
 * discount coupons and usage limits in QuotePilot.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Coupons_Admin' ) ) :

	/**
	 * Class QP_Coupons_Admin
	 */
	class QP_Coupons_Admin {

		/**
		 * Register hooks.
		 *
		 * @return void
		 */
		public static function register() {
			add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		}

		/**
		 * Handle CRUD actions via POST/GET queries.
		 *
		 * @return void
		 */
		public static function handle_actions() {
			if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// 1. HANDLE ADD COUPON
			if ( isset( $_POST['qp_add_coupon'] ) ) {
				if ( ! isset( $_POST['qp_coupons_nonce'] ) || ! wp_verify_nonce( $_POST['qp_coupons_nonce'], 'qp_coupons_action' ) ) {
					wp_die( esc_html__( 'Security check failed.', 'quote-pilot' ) );
				}

				$code   = isset( $_POST['code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) : '';
				$type   = isset( $_POST['discount_type'] ) ? sanitize_text_field( wp_unslash( $_POST['discount_type'] ) ) : 'flat';
				$value  = isset( $_POST['discount_value'] ) ? (float) $_POST['discount_value'] : 0.0;
				$limit  = isset( $_POST['usage_limit'] ) ? (int) $_POST['usage_limit'] : 0;
				$expiry = isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '';
				$active = ! empty( $_POST['active'] );

				if ( empty( $code ) ) {
					wp_safe_redirect( add_query_arg( array( 'page' => 'qp-coupons', 'qp_error' => 'code_required' ), admin_url( 'admin.php' ) ) );
					exit;
				}

				if ( class_exists( 'QP_Database' ) ) {
					// Verify uniqueness of coupon code
					$existing = QP_Database::get_coupon( $code );
					if ( $existing ) {
						wp_safe_redirect( add_query_arg( array( 'page' => 'qp-coupons', 'qp_error' => 'code_exists' ), admin_url( 'admin.php' ) ) );
						exit;
					}

					QP_Database::insert_coupon(
						array(
							'code'           => $code,
							'discount_type'  => $type,
							'discount_value' => $value,
							'usage_limit'    => $limit,
							'expires_at'     => $expiry,
							'active'         => $active ? 1 : 0,
						)
					);
				}

				wp_safe_redirect( add_query_arg( array( 'page' => 'qp-coupons', 'qp_updated' => 'added' ), admin_url( 'admin.php' ) ) );
				exit;
			}

			// 2. HANDLE DELETE COUPON
			if ( isset( $_GET['qp_action'] ) && 'delete_coupon' === $_GET['qp_action'] && ! empty( $_GET['id'] ) ) {
				if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'qp_delete_coupon_' . $_GET['id'] ) ) {
					wp_die( esc_html__( 'Security check failed.', 'quote-pilot' ) );
				}

				$id = (int) $_GET['id'];
				if ( class_exists( 'QP_Database' ) ) {
					QP_Database::delete_coupon( $id );
				}

				wp_safe_redirect( add_query_arg( array( 'page' => 'qp-coupons', 'qp_updated' => 'deleted' ), admin_url( 'admin.php' ) ) );
				exit;
			}

			// 3. HANDLE TOGGLE ACTIVE STATE
			if ( isset( $_GET['qp_action'] ) && 'toggle_coupon' === $_GET['qp_action'] && ! empty( $_GET['id'] ) ) {
				if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'qp_toggle_coupon_' . $_GET['id'] ) ) {
					wp_die( esc_html__( 'Security check failed.', 'quote-pilot' ) );
				}

				$id = (int) $_GET['id'];
				if ( class_exists( 'QP_Database' ) ) {
					global $wpdb;
					$table = QP_Database::coupons();
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- safe table name, id is prepared.
					$coupon = $wpdb->get_row( $wpdb->prepare( "SELECT active FROM {$table} WHERE id = %d", $id ) );
					if ( $coupon ) {
						$new_active = ( 1 === (int) $coupon->active ) ? 0 : 1;
						QP_Database::update_coupon( $id, array( 'active' => $new_active ) );
					}
				}

				wp_safe_redirect( add_query_arg( array( 'page' => 'qp-coupons', 'qp_updated' => 'toggled' ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}

		/**
		 * Render page HTML.
		 *
		 * @return void
		 */
		public static function render_page() {
			if ( ! class_exists( 'QP_Database' ) ) {
				return;
			}

			$coupons         = QP_Database::get_all_coupons();
			$currency_symbol = '$';
			if ( class_exists( 'QP_Helpers' ) ) {
				$currency_symbol = QP_Helpers::get_setting( 'currency_symbol', '$' );
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Coupon Code Management', 'quote-pilot' ); ?></h1>
				<p class="description"><?php esc_html_e( 'Configure discount codes and usage limits to encourage bookings or run campaigns.', 'quote-pilot' ); ?></p>

				<?php if ( isset( $_GET['qp_updated'] ) ) : ?>
					<div class="notice notice-success is-dismissible">
						<p>
							<?php
							if ( 'added' === $_GET['qp_updated'] ) {
								esc_html_e( 'Coupon code created successfully.', 'quote-pilot' );
							} elseif ( 'deleted' === $_GET['qp_updated'] ) {
								esc_html_e( 'Coupon deleted successfully.', 'quote-pilot' );
							} else {
								esc_html_e( 'Coupon active status toggled.', 'quote-pilot' );
							}
							?>
						</p>
					</div>
				<?php elseif ( isset( $_GET['qp_error'] ) ) : ?>
					<div class="notice notice-error is-dismissible">
						<p>
							<?php
							if ( 'code_exists' === $_GET['qp_error'] ) {
								esc_html_e( 'Error: An active coupon with this identical code already exists.', 'quote-pilot' );
							} else {
								esc_html_e( 'Error: Code name is required.', 'quote-pilot' );
							}
							?>
						</p>
					</div>
				<?php endif; ?>

				<div class="metabox-holder" style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
					<!-- Left: Add New Coupon Card -->
					<div style="flex: 1; min-width: 300px;">
						<div class="postbox">
							<h2 class="hndle"><span><?php esc_html_e( 'Create Discount Coupon', 'quote-pilot' ); ?></span></h2>
							<div class="inside">
								<form method="post" action="">
									<?php wp_nonce_field( 'qp_coupons_action', 'qp_coupons_nonce' ); ?>

									<div style="margin-bottom: 15px;">
										<label for="code" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Coupon Code', 'quote-pilot' ); ?></label>
										<input type="text" id="code" name="code" class="regular-text" style="width: 100%; text-transform: uppercase;" placeholder="e.g. SAVE10 / CLEANING20" required />
									</div>

									<div style="margin-bottom: 15px;">
										<label for="discount_type" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Discount Mode', 'quote-pilot' ); ?></label>
										<select id="discount_type" name="discount_type" style="width: 100%;">
											<option value="percent"><?php esc_html_e( 'Percentage (%)', 'quote-pilot' ); ?></option>
											<option value="flat"><?php esc_html_e( 'Flat Amount', 'quote-pilot' ); ?></option>
										</select>
									</div>

									<div style="margin-bottom: 15px;">
										<label for="discount_value" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Discount Value', 'quote-pilot' ); ?></label>
										<input type="number" step="0.01" min="0.01" id="discount_value" name="discount_value" style="width: 100%;" placeholder="e.g. 10 / 15.00" required />
									</div>

									<div style="margin-bottom: 15px;">
										<label for="usage_limit" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Usage Limit', 'quote-pilot' ); ?></label>
										<input type="number" min="0" id="usage_limit" name="usage_limit" style="width: 100%;" value="0" />
										<p class="description" style="margin-top: 2px; font-size: 11px;"><?php esc_html_e( '0 = unlimited uses total.', 'quote-pilot' ); ?></p>
									</div>

									<div style="margin-bottom: 15px;">
										<label for="expires_at" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Expiration Date (Optional)', 'quote-pilot' ); ?></label>
										<input type="date" id="expires_at" name="expires_at" style="width: 100%;" />
									</div>

									<div style="margin-bottom: 15px;">
										<label class="qp-checkbox-label">
											<input type="checkbox" name="active" value="1" class="qp-checkbox-input" checked />
											<span class="qp-checkbox-styled"></span>
											<span class="qp-checkbox-text"><strong><?php esc_html_e( 'Enable Coupon Immediately', 'quote-pilot' ); ?></strong></span>
										</label>
									</div>

									<input type="submit" name="qp_add_coupon" class="button button-primary button-large" style="width: 100%;" value="<?php esc_attr_e( 'Save Coupon Code', 'quote-pilot' ); ?>" />
								</form>
							</div>
						</div>
					</div>

					<!-- Right: List Existing Coupons Card -->
					<div style="flex: 2; min-width: 400px;">
						<div class="postbox">
							<h2 class="hndle"><span><?php esc_html_e( 'Configured Discount Codes', 'quote-pilot' ); ?></span></h2>
							<div class="inside" style="padding: 0;">
								<table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
									<thead>
										<tr>
											<th style="padding-left: 15px; font-weight: 600;"><?php esc_html_e( 'Code', 'quote-pilot' ); ?></th>
											<th style="width: 130px; font-weight: 600;"><?php esc_html_e( 'Discount Type', 'quote-pilot' ); ?></th>
											<th style="width: 120px; font-weight: 600;"><?php esc_html_e( 'Usage Stats', 'quote-pilot' ); ?></th>
											<th style="width: 130px; font-weight: 600;"><?php esc_html_e( 'Expires At', 'quote-pilot' ); ?></th>
											<th style="width: 100px; font-weight: 600;"><?php esc_html_e( 'Status', 'quote-pilot' ); ?></th>
											<th style="width: 120px; text-align: center; font-weight: 600;"><?php esc_html_e( 'Actions', 'quote-pilot' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php if ( ! empty( $coupons ) ) : ?>
											<?php foreach ( $coupons as $coupon ) :
												$delete_url = wp_nonce_url(
													add_query_arg( array( 'page' => 'qp-coupons', 'qp_action' => 'delete_coupon', 'id' => $coupon->id ), admin_url( 'admin.php' ) ),
													'qp_delete_coupon_' . $coupon->id
												);
												$toggle_url = wp_nonce_url(
													add_query_arg( array( 'page' => 'qp-coupons', 'qp_action' => 'toggle_coupon', 'id' => $coupon->id ), admin_url( 'admin.php' ) ),
													'qp_toggle_coupon_' . $coupon->id
												);
												?>
												<tr>
													<td style="padding-left: 15px;"><strong><code><?php echo esc_html( $coupon->code ); ?></code></strong></td>
													<td>
														<?php
														if ( 'percent' === $coupon->discount_type ) {
															echo esc_html( (float) $coupon->discount_value . '%' );
														} else {
															echo esc_html( $currency_symbol . number_format( $coupon->discount_value, 2 ) );
														}
														?>
													</td>
													<td>
														<?php
														$lim_str = ( 0 === (int) $coupon->usage_limit ) ? esc_html__( 'unlimited', 'quote-pilot' ) : esc_html( $coupon->usage_limit );
														printf( esc_html__( '%1$d / %2$s', 'quote-pilot' ), (int) $coupon->usage_count, $lim_str );
														?>
													</td>
													<td>
														<?php
														if ( empty( $coupon->expires_at ) ) {
															echo esc_html__( 'Never', 'quote-pilot' );
														} else {
															echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $coupon->expires_at ) ) );
														}
														?>
													</td>
													<td>
														<?php if ( ! empty( $coupon->active ) ) : ?>
															<span class="status-badge" style="background: #d1fae5; color: #065f46; font-size: 10px;"><?php esc_html_e( 'ACTIVE', 'quote-pilot' ); ?></span>
														<?php else : ?>
															<span class="status-badge" style="background: #e2e8f0; color: #334155; font-size: 10px;"><?php esc_html_e( 'DISABLED', 'quote-pilot' ); ?></span>
														<?php endif; ?>
													</td>
													<td style="text-align: center;">
														<a href="<?php echo esc_url( $toggle_url ); ?>" class="button button-small"><?php esc_html_e( 'Toggle', 'quote-pilot' ); ?></a>
														<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-link" style="color: #ef4444;" onclick="return confirm('<?php esc_html_e( 'Are you sure you want to delete this coupon?', 'quote-pilot' ); ?>')"><?php esc_html_e( 'Delete', 'quote-pilot' ); ?></a>
													</td>
												</tr>
											<?php endforeach; ?>
										<?php else : ?>
											<tr>
												<td colspan="6" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No promotional coupons configured.', 'quote-pilot' ); ?></td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
	}

endif;
