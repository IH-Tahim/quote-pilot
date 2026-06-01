<?php
/**
 * Settings Page Handler.
 *
 * Manages plugin-wide options including currency, tax rules, payments gateway
 * keys (Stripe/PayPal), user consent policies, emergency surcharges, and
 * enabled sub-modules toggling. Securely sanitizes inputs and masks secret API keys.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Settings' ) ) :

	/**
	 * Class QP_Settings
	 */
	class QP_Settings {

		/**
		 * Register settings Hooks.
		 *
		 * @return void
		 */
		public static function register() {
			add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
		}

		/**
		 * Securely mask credentials for front-end rendering.
		 *
		 * @param string $key Original key.
		 * @return string Masked key.
		 */
		public static function mask_key( $key ) {
			if ( empty( $key ) ) {
				return '';
			}
			$len = strlen( $key );
			if ( $len <= 8 ) {
				return '••••••••';
			}
			return substr( $key, 0, 4 ) . str_repeat( '•', 8 ) . substr( $key, -4 );
		}

		/**
		 * Handle options POST submission.
		 *
		 * @return void
		 */
		public static function handle_save() {
			if ( ! isset( $_POST['qp_save_settings'] ) ) {
				return;
			}

			// Verify Nonce & Capability
			if ( ! isset( $_POST['qp_settings_nonce'] ) || ! wp_verify_nonce( $_POST['qp_settings_nonce'], 'qp_save_settings_action' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'quote-pilot' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage options.', 'quote-pilot' ) );
			}

			// Fetch existing options to handle masked keys and merge defaults
			$existing = get_option( 'qp_settings', array() );

			// 1. MODULES TOGGLES
			$modules = isset( $_POST['enabled_modules'] ) ? (array) $_POST['enabled_modules'] : array();
			$sanitized_modules = array(
				'services'            => ! empty( $modules['services'] ),
				'quote_calculator'    => ! empty( $modules['quote_calculator'] ),
				'customer_dashboard'  => ! empty( $modules['customer_dashboard'] ),
				'lead_capture'        => ! empty( $modules['lead_capture'] ),
				'coupons'             => ! empty( $modules['coupons'] ),
				'date_rules'          => ! empty( $modules['date_rules'] ),
				'payments'            => ! empty( $modules['payments'] ),
				'email_notifications' => ! empty( $modules['email_notifications'] ),
			);

			// 2. CURRENCY & TAX
			$currency_symbol = isset( $_POST['currency_symbol'] ) ? sanitize_text_field( wp_unslash( $_POST['currency_symbol'] ) ) : '$';
			$tax_enabled     = ! empty( $_POST['tax_enabled'] );
			$tax_rate        = isset( $_POST['tax_rate'] ) ? (float) $_POST['tax_rate'] : 0.0;

			// 3. SURCHARGES
			$emergency_surcharge_enabled = ! empty( $_POST['emergency_surcharge_enabled'] );
			$emergency_surcharge_type    = isset( $_POST['emergency_surcharge_type'] ) ? sanitize_text_field( wp_unslash( $_POST['emergency_surcharge_type'] ) ) : 'flat';
			$emergency_surcharge_value   = isset( $_POST['emergency_surcharge_value'] ) ? (float) $_POST['emergency_surcharge_value'] : 0.0;

			// 4. CONSENT & POLICIES
			$consent_mode    = isset( $_POST['consent_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_mode'] ) ) : 'split';
			$terms_url       = isset( $_POST['terms_url'] ) ? esc_url_raw( wp_unslash( $_POST['terms_url'] ) ) : '';
			$privacy_url     = isset( $_POST['privacy_url'] ) ? esc_url_raw( wp_unslash( $_POST['privacy_url'] ) ) : '';

			// 5. PAYMENTS & MASKED GATEWAY KEYS
			$payment_mode = isset( $_POST['payment_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_mode'] ) ) : 'pay-after';

			$stripe_secret   = isset( $_POST['stripe_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_secret_key'] ) ) : '';
			$stripe_publish  = isset( $_POST['stripe_publishable_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_publishable_key'] ) ) : '';
			$paypal_client   = isset( $_POST['paypal_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['paypal_client_id'] ) ) : '';
			$paypal_secret   = isset( $_POST['paypal_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['paypal_secret'] ) ) : '';

			// Check if keys are masked; if so, restore from existing options database
			if ( strpos( $stripe_secret, '•••' ) !== false ) {
				$stripe_secret = isset( $existing['stripe_secret_key'] ) ? $existing['stripe_secret_key'] : '';
			}
			if ( strpos( $stripe_publish, '•••' ) !== false ) {
				$stripe_publish = isset( $existing['stripe_publishable_key'] ) ? $existing['stripe_publishable_key'] : '';
			}
			if ( strpos( $paypal_client, '•••' ) !== false ) {
				$paypal_client = isset( $existing['paypal_client_id'] ) ? $existing['paypal_client_id'] : '';
			}
			if ( strpos( $paypal_secret, '•••' ) !== false ) {
				$paypal_secret = isset( $existing['paypal_secret'] ) ? $existing['paypal_secret'] : '';
			}

			// Clean Settings Object
			$settings_payload = array(
				'enabled_modules'             => $sanitized_modules,
				'currency_symbol'             => $currency_symbol,
				'tax_enabled'                 => $tax_enabled,
				'tax_rate'                    => $tax_rate,
				'emergency_surcharge_enabled' => $emergency_surcharge_enabled,
				'emergency_surcharge_type'    => $emergency_surcharge_type,
				'emergency_surcharge_value'   => $emergency_surcharge_value,
				'consent_config'              => array( 'mode' => $consent_mode ),
				'terms_url'                   => $terms_url,
				'privacy_url'                 => $privacy_url,
				'payment_mode'                => $payment_mode,
				'stripe_secret_key'           => $stripe_secret,
				'stripe_publishable_key'      => $stripe_publish,
				'paypal_client_id'            => $paypal_client,
				'paypal_secret'               => $paypal_secret,
				'qp_delete_data_on_uninstall'  => ! empty( $_POST['qp_delete_data_on_uninstall'] ),
			);

			update_option( 'qp_settings', $settings_payload );

			// Redirect with success flag
			wp_safe_redirect( add_query_arg( array( 'page' => 'qp-settings', 'qp_updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		/**
		 * Render Settings screen HTML.
		 *
		 * @return void
		 */
		public static function render_page() {
			$settings = get_option( 'qp_settings', array() );
			$modules  = isset( $settings['enabled_modules'] ) ? (array) $settings['enabled_modules'] : array();

			// Pre-populate values
			$currency_symbol             = isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';
			$tax_enabled                 = ! empty( $settings['tax_enabled'] );
			$tax_rate                    = isset( $settings['tax_rate'] ) ? $settings['tax_rate'] : 10.0;
			$emergency_surcharge_enabled = ! empty( $settings['emergency_surcharge_enabled'] );
			$emergency_surcharge_type    = isset( $settings['emergency_surcharge_type'] ) ? $settings['emergency_surcharge_type'] : 'flat';
			$emergency_surcharge_value   = isset( $settings['emergency_surcharge_value'] ) ? $settings['emergency_surcharge_value'] : 0.0;
			$consent_mode                = isset( $settings['consent_config']['mode'] ) ? $settings['consent_config']['mode'] : 'split';
			$terms_url                   = isset( $settings['terms_url'] ) ? $settings['terms_url'] : '';
			$privacy_url                 = isset( $settings['privacy_url'] ) ? $settings['privacy_url'] : '';
			$payment_mode                = isset( $settings['payment_mode'] ) ? $settings['payment_mode'] : 'pay-after';
			$stripe_secret               = isset( $settings['stripe_secret_key'] ) ? $settings['stripe_secret_key'] : '';
			$stripe_publish              = isset( $settings['stripe_publishable_key'] ) ? $settings['stripe_publishable_key'] : '';
			$paypal_client               = isset( $settings['paypal_client_id'] ) ? $settings['paypal_client_id'] : '';
			$paypal_secret               = isset( $settings['paypal_secret'] ) ? $settings['paypal_secret'] : '';
			$delete_on_uninstall         = ! empty( $settings['qp_delete_data_on_uninstall'] );
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'QuotePilot Settings', 'quote-pilot' ); ?></h1>

				<?php if ( isset( $_GET['qp_updated'] ) ) : ?>
					<div class="notice notice-success is-dismissible">
						<p><?php esc_html_e( 'Settings saved successfully.', 'quote-pilot' ); ?></p>
					</div>
				<?php endif; ?>

				<form method="post" action="">
					<?php wp_nonce_field( 'qp_save_settings_action', 'qp_settings_nonce' ); ?>

					<!-- 1. MODULE TOGGLES -->
					<div class="card">
						<h2><?php esc_html_e( 'Enabled Features & Modules', 'quote-pilot' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Toggle QuotePilot modules. Features that are disabled will not load resources on your site.', 'quote-pilot' ); ?></p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Core Clean Services CPT', 'quote-pilot' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enabled_modules[services]" value="1" <?php checked( ! empty( $modules['services'] ) ); ?> />
										<?php esc_html_e( 'Clean Services Catalog', 'quote-pilot' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Instant Quote Calculator', 'quote-pilot' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enabled_modules[quote_calculator]" value="1" <?php checked( ! empty( $modules['quote_calculator'] ) ); ?> />
										<?php esc_html_e( 'Interactive Quote Form Shortcode [quotepilot_form]', 'quote-pilot' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Customer Accounts Portal', 'quote-pilot' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enabled_modules[customer_dashboard]" value="1" <?php checked( ! empty( $modules['customer_dashboard'] ) ); ?> />
										<?php esc_html_e( 'Shortcode Dashboard [quotepilot_dashboard]', 'quote-pilot' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Consent-Gated Lead Capture', 'quote-pilot' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enabled_modules[lead_capture]" value="1" <?php checked( ! empty( $modules['lead_capture'] ) ); ?> />
										<?php esc_html_e( 'Capture partial abandoned quotes silently on consent ticked', 'quote-pilot' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Coupons & Discounts', 'quote-pilot' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enabled_modules[coupons]" value="1" <?php checked( ! empty( $modules['coupons'] ) ); ?> />
										<?php esc_html_e( 'Support promotional discount code inputs', 'quote-pilot' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Date-Specific Surcharges', 'quote-pilot' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enabled_modules[date_rules]" value="1" <?php checked( ! empty( $modules['date_rules'] ) ); ?> />
										<?php esc_html_e( 'Configure high-rate or closed date rules', 'quote-pilot' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Payments Integrations', 'quote-pilot' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enabled_modules[payments]" value="1" <?php checked( ! empty( $modules['payments'] ) ); ?> />
										<?php esc_html_e( 'Integrate Stripe and PayPal payments', 'quote-pilot' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Integrations & Webhooks', 'quote-pilot' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enabled_modules[email_notifications]" value="1" <?php checked( ! empty( $modules['email_notifications'] ) ); ?> />
										<?php esc_html_e( 'Outgoing webhooks and email/WhatsApp alert integrations', 'quote-pilot' ); ?>
									</label>
								</td>
							</tr>
						</table>
					</div>

					<!-- 2. PRICING & TAX RULES -->
					<div class="card" style="margin-top: 20px;">
						<h2><?php esc_html_e( 'Pricing, Tax & Currency Settings', 'quote-pilot' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="currency_symbol"><?php esc_html_e( 'Currency Symbol', 'quote-pilot' ); ?></label></th>
								<td>
									<input type="text" id="currency_symbol" name="currency_symbol" class="small-text" value="<?php echo esc_attr( $currency_symbol ); ?>" required />
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'GST / Tax Enable', 'quote-pilot' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="tax_enabled" value="1" <?php checked( $tax_enabled ); ?> />
										<?php esc_html_e( 'Add tax to clean quotes automatically', 'quote-pilot' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="tax_rate"><?php esc_html_e( 'Tax Rate (%)', 'quote-pilot' ); ?></label></th>
								<td>
									<input type="number" step="0.01" min="0" id="tax_rate" name="tax_rate" class="regular-text" value="<?php echo esc_attr( $tax_rate ); ?>" />
								</td>
							</tr>
						</table>
					</div>

					<!-- 3. EMERGENCY SURCHARGES -->
					<div class="card" style="margin-top: 20px;">
						<h2><?php esc_html_e( 'Priority Dispatch / Emergency Surcharges', 'quote-pilot' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Emergency Surcharges', 'quote-pilot' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="emergency_surcharge_enabled" value="1" <?php checked( $emergency_surcharge_enabled ); ?> />
										<?php esc_html_e( 'Add option for urgent prioritized cleaning booking', 'quote-pilot' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="emergency_surcharge_type"><?php esc_html_e( 'Surcharge Type', 'quote-pilot' ); ?></label></th>
								<td>
									<select id="emergency_surcharge_type" name="emergency_surcharge_type">
										<option value="flat" <?php selected( 'flat', $emergency_surcharge_type ); ?>><?php esc_html_e( 'Flat Amount', 'quote-pilot' ); ?></option>
										<option value="percent" <?php selected( 'percent', $emergency_surcharge_type ); ?>><?php esc_html_e( 'Percentage of Base', 'quote-pilot' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="emergency_surcharge_value"><?php esc_html_e( 'Surcharge Value', 'quote-pilot' ); ?></label></th>
								<td>
									<input type="number" step="0.01" min="0" id="emergency_surcharge_value" name="emergency_surcharge_value" class="regular-text" value="<?php echo esc_attr( $emergency_surcharge_value ); ?>" />
								</td>
							</tr>
						</table>
					</div>

					<!-- 4. CONSENT & POLICIES -->
					<div class="card" style="margin-top: 20px;">
						<h2><?php esc_html_e( 'Customer Consent Policy Configuration', 'quote-pilot' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="consent_mode"><?php esc_html_e( 'Consent Area Layout Mode', 'quote-pilot' ); ?></label></th>
								<td>
									<select id="consent_mode" name="consent_mode">
										<option value="merged" <?php selected( 'merged', $consent_mode ); ?>><?php esc_html_e( 'Merged Mode (Single terms + privacy checkbox)', 'quote-pilot' ); ?></option>
										<option value="split" <?php selected( 'split', $consent_mode ); ?>><?php esc_html_e( 'Split Mode (Individually checked terms, privacy, & marketing)', 'quote-pilot' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="terms_url"><?php esc_html_e( 'Terms & Conditions URL', 'quote-pilot' ); ?></label></th>
								<td>
									<input type="url" id="terms_url" name="terms_url" class="regular-text" value="<?php echo esc_url( $terms_url ); ?>" placeholder="https://domain.com/terms/" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="privacy_url"><?php esc_html_e( 'Privacy Policy URL', 'quote-pilot' ); ?></label></th>
								<td>
									<input type="url" id="privacy_url" name="privacy_url" class="regular-text" value="<?php echo esc_url( $privacy_url ); ?>" placeholder="https://domain.com/privacy/" />
								</td>
							</tr>
						</table>
					</div>

					<!-- 5. PAYMENT GATEWAYS KEYS -->
					<div class="card" style="margin-top: 20px;">
						<h2><?php esc_html_e( 'Payment Gateway & Deposit Configuration', 'quote-pilot' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="payment_mode"><?php esc_html_e( 'Payment Allocation', 'quote-pilot' ); ?></label></th>
								<td>
									<select id="payment_mode" name="payment_mode">
										<option value="pay-after" <?php selected( 'pay-after', $payment_mode ); ?>><?php esc_html_e( 'Pay after clean (Cash/Invoice)', 'quote-pilot' ); ?></option>
										<option value="deposit-half" <?php selected( 'deposit-half', $payment_mode ); ?>><?php esc_html_e( 'Require 50% Deposit upfront', 'quote-pilot' ); ?></option>
										<option value="deposit-full" <?php selected( 'deposit-full', $payment_mode ); ?>><?php esc_html_e( 'Require 100% Full Payment upfront', 'quote-pilot' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="stripe_publishable_key"><?php esc_html_e( 'Stripe Publishable Key', 'quote-pilot' ); ?></label></th>
								<td>
									<input type="text" id="stripe_publishable_key" name="stripe_publishable_key" class="regular-text" value="<?php echo esc_attr( self::mask_key( $stripe_publish ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="stripe_secret_key"><?php esc_html_e( 'Stripe Secret Key', 'quote-pilot' ); ?></label></th>
								<td>
									<input type="text" id="stripe_secret_key" name="stripe_secret_key" class="regular-text" value="<?php echo esc_attr( self::mask_key( $stripe_secret ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="paypal_client_id"><?php esc_html_e( 'PayPal Client ID', 'quote-pilot' ); ?></label></th>
								<td>
									<input type="text" id="paypal_client_id" name="paypal_client_id" class="regular-text" value="<?php echo esc_attr( self::mask_key( $paypal_client ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="paypal_secret"><?php esc_html_e( 'PayPal Client Secret', 'quote-pilot' ); ?></label></th>
								<td>
									<input type="text" id="paypal_secret" name="paypal_secret" class="regular-text" value="<?php echo esc_attr( self::mask_key( $paypal_secret ) ); ?>" />
								</td>
							</tr>
						</table>
					</div>

					<!-- 6. UNINSTALL PREFERENCE -->
					<div class="card" style="margin-top: 20px;">
						<h2><?php esc_html_e( 'Uninstall Preferences', 'quote-pilot' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Delete Data on Uninstall', 'quote-pilot' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="qp_delete_data_on_uninstall" value="1" <?php checked( $delete_on_uninstall ); ?> />
										<span style="color: #d97706; font-weight: 600;"><?php esc_html_e( 'Warning: checking this will drop all 5 custom QuotePilot tables and options permanently on plugin delete.', 'quote-pilot' ); ?></span>
									</label>
								</td>
							</tr>
						</table>
					</div>

					<p class="submit">
						<input type="submit" name="qp_save_settings" id="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Settings', 'quote-pilot' ); ?>" />
					</p>
				</form>
			</div>
			<?php
		}
	}

endif;
