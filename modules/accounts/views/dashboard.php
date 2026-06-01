<?php
/**
 * Customer Dashboard View.
 *
 * Renders the register/login screen for guests and the responsive booking
 * portal for logged-in customer accounts. Scoped under .qp-dashboard.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $qp_dashboard_error, $qp_dashboard_success;

// Messages from query arguments
$qp_msg = isset( $_GET['qp_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['qp_msg'] ) ) : '';
if ( 'login_success' === $qp_msg ) {
	$qp_dashboard_success = esc_html__( 'Welcome back! You have successfully logged in.', 'quote-pilot' );
} elseif ( 'register_success' === $qp_msg ) {
	$qp_dashboard_success = esc_html__( 'Thank you for registering! Your account is ready.', 'quote-pilot' );
}

$is_user_logged_in = is_user_logged_in();
$current_user      = wp_get_current_user();
$currency_symbol   = '$';

if ( class_exists( 'QP_Helpers' ) ) {
	$currency_symbol = QP_Helpers::get_setting( 'currency_symbol', '$' );
}
?>
<div class="qp-dashboard qp-quote-form" id="qp-dashboard-container">
	<?php if ( ! $is_user_logged_in ) : ?>
		<!-- 1. GUEST GATE: LOGIN & REGISTER FORMS -->
		<div class="qp-auth-tabs">
			<button type="button" class="qp-auth-tab active" id="qp-tab-login" onclick="qpSwitchTab('login')">
				<?php esc_html_e( 'Sign In', 'quote-pilot' ); ?>
			</button>
			<button type="button" class="qp-auth-tab" id="qp-tab-register" onclick="qpSwitchTab('register')">
				<?php esc_html_e( 'Create Account', 'quote-pilot' ); ?>
			</button>
		</div>

		<!-- Feedback Messages -->
		<?php if ( ! empty( $qp_dashboard_error ) ) : ?>
			<div class="qp-form-errors-alert" style="display: block; margin-bottom: 20px;">
				<?php echo esc_html( $qp_dashboard_error ); ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $qp_dashboard_success ) ) : ?>
			<div class="qp-success-alert" style="display: block; margin-bottom: 20px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; font-size: 13px;">
				<?php echo esc_html( $qp_dashboard_success ); ?>
			</div>
		<?php endif; ?>

		<!-- Login Form -->
		<form id="qp-login-form" method="post" action="">
			<?php wp_nonce_field( 'qp_dashboard_action', 'qp_nonce' ); ?>
			<input type="hidden" name="qp_action" value="login" />

			<p class="qp-step-intro"><?php esc_html_e( 'Sign in to view your booking history, track quotes, and manage your clean schedule.', 'quote-pilot' ); ?></p>

			<div class="qp-form-section">
				<label class="qp-field-label" for="qp_log"><?php esc_html_e( 'Email Address', 'quote-pilot' ); ?></label>
				<input type="text" id="qp_log" name="log" class="qp-text-input" placeholder="<?php esc_attr_e( 'name@domain.com', 'quote-pilot' ); ?>" required />
			</div>

			<div class="qp-form-section">
				<label class="qp-field-label" for="qp_pwd"><?php esc_html_e( 'Password', 'quote-pilot' ); ?></label>
				<input type="password" id="qp_pwd" name="pwd" class="qp-text-input" placeholder="<?php esc_attr_e( 'Enter your password...', 'quote-pilot' ); ?>" required />
			</div>

			<div class="qp-form-section">
				<label class="qp-checkbox-label">
					<input type="checkbox" name="rememberme" value="forever" class="qp-checkbox-input" checked />
					<span class="qp-checkbox-styled"></span>
					<span class="qp-checkbox-text"><?php esc_html_e( 'Remember me', 'quote-pilot' ); ?></span>
				</label>
			</div>

			<button type="submit" class="qp-nav-btn qp-btn-next" style="width: 100%; display: flex; justify-content: center; height: 46px; margin-top: 15px;">
				<?php esc_html_e( 'Sign In', 'quote-pilot' ); ?>
			</button>
		</form>

		<!-- Register Form -->
		<form id="qp-register-form" method="post" action="" style="display: none;">
			<?php wp_nonce_field( 'qp_dashboard_action', 'qp_nonce' ); ?>
			<input type="hidden" name="qp_action" value="register" />

			<p class="qp-step-intro"><?php esc_html_e( 'Create an account to track your orders, link past quotes, and speed up future bookings.', 'quote-pilot' ); ?></p>

			<div class="qp-form-section">
				<label class="qp-field-label" for="qp_reg_name"><?php esc_html_e( 'Full Name', 'quote-pilot' ); ?></label>
				<input type="text" id="qp_reg_name" name="reg_name" class="qp-text-input" placeholder="<?php esc_attr_e( 'First & last name', 'quote-pilot' ); ?>" required />
			</div>

			<div class="qp-form-section">
				<label class="qp-field-label" for="qp_reg_email"><?php esc_html_e( 'Email Address', 'quote-pilot' ); ?></label>
				<input type="email" id="qp_reg_email" name="reg_email" class="qp-text-input" placeholder="<?php esc_attr_e( 'name@domain.com', 'quote-pilot' ); ?>" required />
			</div>

			<div class="qp-form-section">
				<label class="qp-field-label" for="qp_reg_password"><?php esc_html_e( 'Choose Password', 'quote-pilot' ); ?></label>
				<input type="password" id="qp_reg_password" name="reg_password" class="qp-text-input" placeholder="<?php esc_attr_e( 'Choose a secure password...', 'quote-pilot' ); ?>" required />
			</div>

			<div class="qp-form-section">
				<label class="qp-field-label" for="qp_reg_confirm_password"><?php esc_html_e( 'Confirm Password', 'quote-pilot' ); ?></label>
				<input type="password" id="qp_reg_confirm_password" name="reg_confirm_password" class="qp-text-input" placeholder="<?php esc_attr_e( 'Repeat password...', 'quote-pilot' ); ?>" required />
			</div>

			<button type="submit" class="qp-nav-btn qp-btn-next" style="width: 100%; display: flex; justify-content: center; height: 46px; margin-top: 15px;">
				<?php esc_html_e( 'Create Account', 'quote-pilot' ); ?>
			</button>
		</form>

		<script type="text/javascript">
			function qpSwitchTab(tab) {
				var loginForm = document.getElementById('qp-login-form');
				var registerForm = document.getElementById('qp-register-form');
				var loginTab = document.getElementById('qp-tab-login');
				var registerTab = document.getElementById('qp-tab-register');

				if (tab === 'login') {
					loginForm.style.display = 'block';
					registerForm.style.display = 'none';
					loginTab.classList.add('active');
					registerTab.classList.remove('active');
				} else {
					loginForm.style.display = 'none';
					registerForm.style.display = 'block';
					loginTab.classList.remove('active');
					registerTab.classList.add('active');
				}
			}
			<?php if ( ! empty( $_POST['qp_action'] ) && 'register' === $_POST['qp_action'] ) : ?>
				qpSwitchTab('register');
			<?php endif; ?>
		</script>

	<?php else : ?>
		<!-- 2. CUSTOMER PORTAL VIEW -->
		<div class="qp-portal-header">
			<div class="qp-portal-user">
				<div class="qp-portal-avatar">
					<span class="dashicons dashicons-admin-users"></span>
				</div>
				<div class="qp-portal-meta">
					<h3 class="qp-portal-welcome"><?php printf( esc_html__( 'Hello, %s', 'quote-pilot' ), esc_html( $current_user->display_name ) ); ?></h3>
					<span class="qp-portal-email"><?php echo esc_html( $current_user->user_email ); ?></span>
				</div>
			</div>
			<a href="<?php echo esc_url( add_query_arg( 'qp_action', 'logout' ) ); ?>" class="qp-logout-btn">
				<span class="dashicons dashicons-logout"></span>
				<?php esc_html_e( 'Logout', 'quote-pilot' ); ?>
			</a>
		</div>

		<?php if ( ! empty( $qp_dashboard_success ) ) : ?>
			<div class="qp-success-alert" style="display: block; margin-bottom: 20px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; font-size: 13px;">
				<?php echo esc_html( $qp_dashboard_success ); ?>
			</div>
		<?php endif; ?>

		<h4 class="qp-bookings-section-title"><?php esc_html_e( 'Your Bookings & Quotes', 'quote-pilot' ); ?></h4>

		<?php
		$bookings = array();
		if ( class_exists( 'QP_Database' ) ) {
			$bookings = QP_Database::get_bookings_by_user( $current_user->ID );
		}
		?>

		<?php if ( empty( $bookings ) ) : ?>
			<div class="qp-no-bookings">
				<span class="dashicons dashicons-calendar-alt"></span>
				<p><?php esc_html_e( 'You have no quotes or bookings yet.', 'quote-pilot' ); ?></p>
				<a href="<?php echo esc_url( home_url() ); ?>" class="qp-coupon-btn" style="text-decoration: none; padding: 10px 20px; margin-top: 10px; display: inline-block;">
					<?php esc_html_e( 'Get Your First Quote', 'quote-pilot' ); ?>
				</a>
			</div>
		<?php else : ?>
			<div class="qp-bookings-stack">
				<?php foreach ( $bookings as $booking ) :
					$service_title = esc_html__( 'Cleaning Service', 'quote-pilot' );
					$service_post  = get_post( $booking->service_id );
					if ( $service_post ) {
						$service_title = get_the_title( $service_post );
					}

					// Status color logic
					$status_class = 'status-pending';
					if ( 'completed' === $booking->booking_status ) {
						$status_class = 'status-completed';
					} elseif ( 'cancelled' === $booking->booking_status || 'failed' === $booking->booking_status ) {
						$status_class = 'status-cancelled';
					} elseif ( 'confirmed' === $booking->booking_status ) {
						$status_class = 'status-confirmed';
					}
					?>
					<div class="qp-booking-item-card">
						<div class="qp-booking-item-header" onclick="qpToggleBreakdown(<?php echo esc_attr( $booking->id ); ?>)">
							<div class="qp-booking-service-info">
								<span class="qp-booking-id">#<?php echo esc_html( $booking->id ); ?></span>
								<h4 class="qp-booking-service-title"><?php echo esc_html( $service_title ); ?></h4>
								<span class="qp-booking-date">
									<span class="dashicons dashicons-calendar-alt"></span>
									<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking->preferred_date ) ) ); ?>
									<?php if ( ! empty( $booking->preferred_time ) ) : ?>
										@ <?php echo esc_html( $booking->preferred_time ); ?>
									<?php endif; ?>
								</span>
							</div>
							<div class="qp-booking-status-info">
								<span class="qp-booking-total"><?php echo esc_html( $currency_symbol . number_format( $booking->total_price, 2 ) ); ?></span>
								<span class="qp-status-badge <?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( ucfirst( $booking->booking_status ) ); ?>
								</span>
								<span class="dashicons dashicons-arrow-down-alt2 qp-collapse-arrow" id="qp-arrow-<?php echo esc_attr( $booking->id ); ?>"></span>
							</div>
						</div>

						<!-- Booking Details & Breakdown Collapsible panel -->
						<div class="qp-booking-item-breakdown" id="qp-breakdown-<?php echo esc_attr( $booking->id ); ?>" style="display: none;">
							<div class="qp-breakdown-inner">
								<!-- Customer Contact proof -->
								<div class="qp-breakdown-details-grid">
									<div>
										<strong><?php esc_html_e( 'Name:', 'quote-pilot' ); ?></strong> <?php echo esc_html( $booking->customer_name ); ?><br/>
										<strong><?php esc_html_e( 'Phone:', 'quote-pilot' ); ?></strong> <?php echo esc_html( $booking->customer_phone ); ?>
									</div>
									<div>
										<strong><?php esc_html_e( 'Address:', 'quote-pilot' ); ?></strong><br/>
										<span style="font-size: 12px; color: var(--qp-text-light);"><?php echo esc_html( $booking->customer_address ); ?></span>
									</div>
								</div>

								<h5 class="qp-breakdown-subtitle"><?php esc_html_e( 'Pricing Details', 'quote-pilot' ); ?></h5>
								
								<div class="qp-breakdown-price-list">
									<?php
									$items = array();
									if ( class_exists( 'QP_Database' ) ) {
										$items = QP_Database::get_booking_items( $booking->id );
									}
									if ( ! empty( $items ) ) :
										foreach ( $items as $item ) :
											$unit_amt = $item->unit_amount;
											$qty      = $item->quantity;
											?>
											<div class="qp-breakdown-price-row">
												<span class="qp-breakdown-price-label">
													<?php echo esc_html( $item->item_label ); ?>
													<?php if ( $qty > 1 ) : ?>
														<small class="description"><?php echo esc_html( 'x' . $qty ); ?></small>
													<?php endif; ?>
												</span>
												<span class="qp-breakdown-price-value">
													<?php
													if ( 'discount' === $item->item_type ) {
														echo esc_html( '-' . $currency_symbol . number_format( abs( $item->line_total ), 2 ) );
													} else {
														echo esc_html( $currency_symbol . number_format( $item->line_total, 2 ) );
													}
													?>
												</span>
											</div>
											<?php
										endforeach;
									endif;
									?>

									<!-- Final Tax & Surcharges summary -->
									<div class="qp-breakdown-price-row final-total">
										<span class="qp-breakdown-price-label"><strong><?php esc_html_e( 'Total Price', 'quote-pilot' ); ?></strong></span>
										<span class="qp-breakdown-price-value"><strong><?php echo esc_html( $currency_symbol . number_format( $booking->total_price, 2 ) ); ?></strong></span>
									</div>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<script type="text/javascript">
				function qpToggleBreakdown(bookingId) {
					var el = document.getElementById('qp-breakdown-' + bookingId);
					var arrow = document.getElementById('qp-arrow-' + bookingId);
					if (el.style.display === 'none') {
						el.style.display = 'block';
						arrow.style.transform = 'rotate(180deg)';
					} else {
						el.style.display = 'none';
						arrow.style.transform = 'rotate(0deg)';
					}
				}
			</script>
		<?php endif; ?>
	<?php endif; ?>
</div>

<style type="text/css">
.qp-auth-tabs {
	display: flex;
	border-bottom: 2px solid var(--qp-border);
	margin-bottom: 24px;
}
.qp-auth-tab {
	flex: 1;
	background: none;
	border: none;
	padding: 12px;
	font-size: 15px;
	font-weight: 600;
	color: var(--qp-text-light);
	cursor: pointer;
	text-align: center;
	transition: all 0.2s ease;
	border-bottom: 2px solid transparent;
	margin-bottom: -2px;
}
.qp-auth-tab.active {
	color: var(--qp-primary);
	border-bottom-color: var(--qp-primary);
}
.qp-portal-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding-bottom: 16px;
	border-bottom: 1px solid var(--qp-border);
	margin-bottom: 24px;
}
.qp-portal-user {
	display: flex;
	align-items: center;
	gap: 12px;
}
.qp-portal-avatar {
	width: 44px;
	height: 44px;
	border-radius: 50%;
	background: var(--qp-primary-light);
	color: var(--qp-primary);
	display: flex;
	align-items: center;
	justify-content: center;
}
.qp-portal-avatar .dashicons {
	font-size: 22px;
	width: 22px;
	height: 22px;
}
.qp-portal-welcome {
	margin: 0;
	font-size: 16px;
	font-weight: 700;
}
.qp-portal-email {
	font-size: 12px;
	color: var(--qp-text-light);
}
.qp-logout-btn {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 13px;
	font-weight: 600;
	color: #ef4444;
	text-decoration: none;
	padding: 6px 12px;
	border-radius: 6px;
	border: 1px solid transparent;
	transition: all 0.15s ease;
}
.qp-logout-btn:hover {
	background: #fef2f2;
	border-color: #fca5a5;
}
.qp-bookings-section-title {
	font-size: 15px;
	font-weight: 700;
	margin-bottom: 16px;
}
.qp-no-bookings {
	text-align: center;
	padding: 40px 20px;
	background: #ffffff;
	border-radius: var(--qp-radius);
	border: 1px solid var(--qp-border);
}
.qp-no-bookings .dashicons {
	font-size: 40px;
	width: 40px;
	height: 40px;
	color: var(--qp-text-light);
	margin-bottom: 12px;
}
.qp-bookings-stack {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.qp-booking-item-card {
	background: #ffffff;
	border: 1px solid var(--qp-border);
	border-radius: var(--qp-radius);
	overflow: hidden;
	box-shadow: var(--qp-shadow-sm);
	transition: border-color 0.15s ease;
}
.qp-booking-item-card:hover {
	border-color: var(--qp-border-hover);
}
.qp-booking-item-header {
	padding: 16px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	cursor: pointer;
}
.qp-booking-id {
	font-size: 11px;
	font-weight: 700;
	color: var(--qp-primary);
	text-transform: uppercase;
	letter-spacing: 0.05em;
	display: block;
	margin-bottom: 4px;
}
.qp-booking-service-title {
	margin: 0 0 6px 0;
	font-size: 15px;
	font-weight: 700;
}
.qp-booking-date {
	font-size: 12px;
	color: var(--qp-text-light);
	display: flex;
	align-items: center;
	gap: 6px;
}
.qp-booking-date .dashicons {
	font-size: 14px;
	width: 14px;
	height: 14px;
}
.qp-booking-status-info {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 6px;
}
.qp-booking-total {
	font-size: 16px;
	font-weight: 800;
	color: #0f172a;
}
.qp-status-badge {
	font-size: 10px;
	font-weight: 700;
	padding: 4px 8px;
	border-radius: 4px;
	text-transform: uppercase;
}
.status-pending { background: #fef3c7; color: #92400e; }
.status-confirmed { background: #dbeafe; color: #1e40af; }
.status-completed { background: #d1fae5; color: #065f46; }
.status-cancelled { background: #fde8e8; color: #9b1c1c; }

.qp-collapse-arrow {
	font-size: 16px;
	color: var(--qp-text-light);
	transition: transform 0.25s ease;
}
.qp-booking-item-breakdown {
	border-top: 1px dashed var(--qp-border);
	background: var(--qp-bg-form);
}
.qp-breakdown-inner {
	padding: 16px;
}
.qp-breakdown-details-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 12px;
	font-size: 13px;
	line-height: 1.5;
	border-bottom: 1px dashed var(--qp-border);
	padding-bottom: 12px;
	margin-bottom: 12px;
}
.qp-breakdown-subtitle {
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: var(--qp-text-light);
	margin: 0 0 8px 0;
}
.qp-breakdown-price-list {
	display: flex;
	flex-direction: column;
	gap: 6px;
}
.qp-breakdown-price-row {
	display: flex;
	justify-content: space-between;
	font-size: 13px;
}
.qp-breakdown-price-row.final-total {
	border-top: 1px solid var(--qp-border);
	padding-top: 8px;
	margin-top: 4px;
	font-size: 14px;
}
</style>
