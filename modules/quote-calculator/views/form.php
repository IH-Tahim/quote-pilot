<?php
/**
 * QuotePilot multi-step quote wizard form markup.
 *
 * Scoped under .qp-quote-form. Rendered dynamically via [quotepilot_form]
 * shortcode. Follows a mobile-first wizard structure with big touch targets
 * and progress tracking.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Fetch active services with pricing configuration.
$services = array();
$posts    = get_posts(
	array(
		'post_type'   => 'qp_service',
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => 'menu_order title',
		'order'       => 'ASC',
	)
);

foreach ( $posts as $post ) {
	$pricing = class_exists( 'QP_Services_Meta' ) ? QP_Services_Meta::get_pricing( $post->ID ) : array();
	if ( ! empty( $pricing['service_active'] ) ) {
		$pricing['id']    = $post->ID;
		$pricing['title'] = get_the_title( $post );
		$pricing['slug']  = $post->post_name;
		$services[]       = $pricing;
	}
}

// Get global settings.
$settings       = get_option( 'qp_settings', array() );
$consent_config = isset( $settings['consent_config'] ) ? $settings['consent_config'] : array();
$consent_mode   = isset( $consent_config['mode'] ) ? $consent_config['mode'] : 'split';
$privacy_url    = isset( $settings['privacy_url'] ) ? esc_url( $settings['privacy_url'] ) : '#';
$terms_url      = isset( $settings['terms_url'] ) ? esc_url( $settings['terms_url'] ) : '#';

// Default options if settings unset.
$currency_symbol = isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';

// Fields whitelisted contract.
$fields = QP_Fields::all();
?>
<div class="qp-quote-form" id="qp-quote-form-container">
	<form id="qp-calculator-form" novalidate>
		<?php wp_nonce_field( 'qp_quote_form', 'nonce' ); ?>
		<input type="hidden" name="action" value="qp_submit" />

		<!-- 1. Progress Bar & Wizard Header -->
		<div class="qp-wizard-header">
			<div class="qp-progress-wrapper">
				<div class="qp-progress-bar" id="qp-progress-bar" role="progressbar" aria-valuemin="1" aria-valuemax="5" aria-valuenow="1"></div>
			</div>
			<div class="qp-steps-indicator">
				<span class="qp-step-dot active" data-step="1">1</span>
				<span class="qp-step-line"></span>
				<span class="qp-step-dot" data-step="2">2</span>
				<span class="qp-step-line"></span>
				<span class="qp-step-dot" data-step="3">3</span>
				<span class="qp-step-line"></span>
				<span class="qp-step-dot" data-step="4">4</span>
				<span class="qp-step-line"></span>
				<span class="qp-step-dot" data-step="5">5</span>
			</div>
			<h2 class="qp-step-title" id="qp-step-title"><?php esc_html_e( 'Select Service', 'quote-pilot' ); ?></h2>
		</div>

		<!-- 2. Steps Containers -->
		<div class="qp-steps-container">

			<!-- STEP 1: Select Service -->
			<div class="qp-step active" data-step="1" role="tabpanel" aria-label="<?php esc_attr_e( 'Service Selection', 'quote-pilot' ); ?>">
				<p class="qp-step-intro"><?php esc_html_e( 'Choose the cleaning service that best fits your needs.', 'quote-pilot' ); ?></p>
				<div class="qp-cards-grid qp-services-cards">
					<?php
					if ( ! empty( $services ) ) :
						foreach ( $services as $svc ) :
							$is_preselected = ( $preselected_slug === $svc['slug'] );
							?>
							<label class="qp-option-card qp-service-card <?php echo $is_preselected ? 'selected' : ''; ?>" data-service-id="<?php echo esc_attr( $svc['id'] ); ?>">
								<input
									type="radio"
									name="service_id"
									value="<?php echo esc_attr( $svc['id'] ); ?>"
									class="qp-radio-input"
									<?php checked( $is_preselected || count( $services ) === 1 ); ?>
									required
								/>
								<div class="qp-card-content">
									<div class="qp-card-icon">
										<span class="dashicons dashicons-admin-home"></span>
									</div>
									<h3 class="qp-card-title"><?php echo esc_html( $svc['title'] ); ?></h3>
									<div class="qp-card-price">
										<span class="qp-price-label"><?php esc_html_e( 'Starts from', 'quote-pilot' ); ?></span>
										<span class="qp-price-amount"><?php echo esc_html( $currency_symbol . number_format( $svc['base_price'], 2 ) ); ?></span>
									</div>
								</div>
							</label>
							<?php
						endforeach;
					else :
						?>
						<p class="qp-no-services"><?php esc_html_e( 'No active services configured yet. Please configure services in the admin panel.', 'quote-pilot' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- STEP 2: Property Details -->
			<div class="qp-step" data-step="2" role="tabpanel" aria-label="<?php esc_attr_e( 'Property Details', 'quote-pilot' ); ?>">
				<p class="qp-step-intro"><?php esc_html_e( 'Tell us a bit about your property for an accurate calculation.', 'quote-pilot' ); ?></p>
				
				<!-- Square Footage -->
				<div class="qp-form-section qp-conditional-field" data-field="sqft">
					<label class="qp-field-label" for="sqft"><?php echo esc_html( $fields['sqft']['label'] ); ?></label>
					<div class="qp-input-wrapper">
						<input
							type="number"
							inputmode="numeric"
							min="0"
							id="sqft"
							name="sqft"
							placeholder="<?php esc_attr_e( 'e.g. 1500', 'quote-pilot' ); ?>"
							class="qp-number-input"
						/>
						<span class="qp-input-suffix"><?php esc_html_e( 'sqft', 'quote-pilot' ); ?></span>
					</div>
				</div>

				<!-- Room Size Tier -->
				<div class="qp-form-section qp-conditional-field" data-field="room_size">
					<span class="qp-field-label"><?php echo esc_html( $fields['room_size']['label'] ); ?></span>
					<div class="qp-cards-row">
						<?php foreach ( $fields['room_size']['options'] as $opt ) : ?>
							<label class="qp-option-card-small <?php echo 'medium' === $opt ? 'selected' : ''; ?>">
								<input
									type="radio"
									name="room_size"
									value="<?php echo esc_attr( $opt ); ?>"
									class="qp-radio-input"
									<?php checked( 'medium' === $opt ); ?>
								/>
								<span class="qp-card-small-label"><?php echo esc_html( ucfirst( $opt ) ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Room Counts -->
				<div class="qp-form-grid">
					<!-- Bedrooms -->
					<div class="qp-form-section qp-conditional-field" data-field="bedrooms">
						<label class="qp-field-label" for="bedrooms"><?php echo esc_html( $fields['bedrooms']['label'] ); ?></label>
						<input
							type="number"
							inputmode="numeric"
							min="0"
							id="bedrooms"
							name="bedrooms"
							value="0"
							class="qp-counter-input"
						/>
					</div>

					<!-- Bathrooms -->
					<div class="qp-form-section qp-conditional-field" data-field="bathrooms">
						<label class="qp-field-label" for="bathrooms"><?php echo esc_html( $fields['bathrooms']['label'] ); ?></label>
						<input
							type="number"
							inputmode="numeric"
							min="0"
							id="bathrooms"
							name="bathrooms"
							value="0"
							class="qp-counter-input"
						/>
					</div>

					<!-- Extra Bathrooms -->
					<div class="qp-form-section qp-conditional-field" data-field="extra_bathrooms">
						<label class="qp-field-label" for="extra_bathrooms"><?php echo esc_html( $fields['extra_bathrooms']['label'] ); ?></label>
						<input
							type="number"
							inputmode="numeric"
							min="0"
							id="extra_bathrooms"
							name="extra_bathrooms"
							value="0"
							class="qp-counter-input"
						/>
					</div>
				</div>

				<!-- Living Room Size Tier -->
				<div class="qp-form-section qp-conditional-field" data-field="living_room_size">
					<span class="qp-field-label"><?php echo esc_html( $fields['living_room_size']['label'] ); ?></span>
					<div class="qp-cards-row">
						<?php foreach ( $fields['living_room_size']['options'] as $opt ) : ?>
							<label class="qp-option-card-small <?php echo 'medium' === $opt ? 'selected' : ''; ?>">
								<input
									type="radio"
									name="living_room_size"
									value="<?php echo esc_attr( $opt ); ?>"
									class="qp-radio-input"
									<?php checked( 'medium' === $opt ); ?>
								/>
								<span class="qp-card-small-label"><?php echo esc_html( ucfirst( $opt ) ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Living Rooms count & Stories -->
				<div class="qp-form-grid">
					<!-- Living Rooms -->
					<div class="qp-form-section qp-conditional-field" data-field="living_rooms">
						<label class="qp-field-label" for="living_rooms"><?php echo esc_html( $fields['living_rooms']['label'] ); ?></label>
						<input
							type="number"
							inputmode="numeric"
							min="0"
							id="living_rooms"
							name="living_rooms"
							value="0"
							class="qp-counter-input"
						/>
					</div>

					<!-- Stories -->
					<div class="qp-form-section qp-conditional-field" data-field="stories">
						<span class="qp-field-label"><?php echo esc_html( $fields['stories']['label'] ); ?></span>
						<div class="qp-cards-row">
							<?php foreach ( $fields['stories']['options'] as $opt ) : ?>
								<label class="qp-option-card-small <?php echo '1' === $opt ? 'selected' : ''; ?>">
									<input
										type="radio"
										name="stories"
										value="<?php echo esc_attr( $opt ); ?>"
										class="qp-radio-input"
										<?php checked( '1' === $opt ); ?>
									/>
									<span class="qp-card-small-label"><?php echo esc_html( $opt ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- STEP 3: Select Add-ons & Extra items -->
			<div class="qp-step" data-step="3" role="tabpanel" aria-label="<?php esc_attr_e( 'Extras & Add-ons', 'quote-pilot' ); ?>">
				<p class="qp-step-intro"><?php esc_html_e( 'Choose additional items or custom options to add to your service.', 'quote-pilot' ); ?></p>
				
				<!-- Oven Cleaning counts -->
				<div class="qp-form-grid qp-conditional-field" data-field="ovens" style="margin-bottom: 20px;">
					<div class="qp-form-section">
						<label class="qp-field-label" for="ovens"><?php echo esc_html( $fields['ovens']['label'] ); ?></label>
						<input
							type="number"
							inputmode="numeric"
							min="0"
							id="ovens"
							name="ovens"
							value="0"
							class="qp-counter-input"
						/>
					</div>

					<!-- Oven Size Tier -->
					<div class="qp-form-section qp-conditional-field" data-field="oven_size">
						<span class="qp-field-label"><?php echo esc_html( $fields['oven_size']['label'] ); ?></span>
						<div class="qp-cards-row">
							<?php foreach ( $fields['oven_size']['options'] as $opt ) : ?>
								<label class="qp-option-card-small <?php echo 'standard' === $opt ? 'selected' : ''; ?>">
									<input
										type="radio"
										name="oven_size"
										value="<?php echo esc_attr( $opt ); ?>"
										class="qp-radio-input"
										<?php checked( 'standard' === $opt ); ?>
									/>
									<span class="qp-card-small-label"><?php echo esc_html( ucfirst( $opt ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<!-- Repeatable Addons lists -->
				<div class="qp-form-section qp-conditional-field" data-field="addons_flat" id="qp-addons-container">
					<span class="qp-field-label"><?php esc_html_e( 'Choose Extra Services', 'quote-pilot' ); ?></span>
					<div class="qp-addons-cards-grid" id="qp-addons-cards-grid">
						<!-- Populated dynamically by B6 JS based on selected Service metadata -->
						<p class="qp-addons-placeholder description"><?php esc_html_e( 'Add-ons will be listed here based on your selected service.', 'quote-pilot' ); ?></p>
					</div>
				</div>
			</div>

			<!-- STEP 4: Scheduling & Surcharges -->
			<div class="qp-step" data-step="4" role="tabpanel" aria-label="<?php esc_attr_e( 'Scheduling & Promotions', 'quote-pilot' ); ?>">
				<p class="qp-step-intro"><?php esc_html_e( 'Select your preferred date/time slot and apply promotional coupons.', 'quote-pilot' ); ?></p>
				
				<div class="qp-form-grid">
					<!-- Date selection -->
					<div class="qp-form-section qp-conditional-field" data-field="preferred_date">
						<label class="qp-field-label" for="preferred_date"><?php echo esc_html( $fields['preferred_date']['label'] ); ?></label>
						<input
							type="date"
							id="preferred_date"
							name="preferred_date"
							class="qp-date-input"
							required
						/>
					</div>

					<!-- Time slot -->
					<div class="qp-form-section qp-conditional-field" data-field="preferred_time">
						<label class="qp-field-label" for="preferred_time"><?php echo esc_html( $fields['preferred_time']['label'] ); ?></label>
						<select id="preferred_time" name="preferred_time" class="qp-select-input" required>
							<option value=""><?php esc_html_e( 'Select a slot...', 'quote-pilot' ); ?></option>
							<option value="8:00 AM">8:00 AM</option>
							<option value="10:00 AM">10:00 AM</option>
							<option value="12:00 PM">12:00 PM</option>
							<option value="2:00 PM">2:00 PM</option>
							<option value="4:00 PM">4:00 PM</option>
						</select>
					</div>
				</div>

				<div class="qp-form-section qp-conditional-field" data-field="emergency" style="margin-top: 15px;">
					<label class="qp-checkbox-label">
						<input
							type="checkbox"
							id="emergency"
							name="emergency"
							value="1"
							class="qp-checkbox-input"
						/>
						<span class="qp-checkbox-styled"></span>
						<span class="qp-checkbox-text">
							<strong><?php echo esc_html( $fields['emergency']['label'] ); ?></strong><br/>
							<small class="description"><?php esc_html_e( 'Request same-day priority service dispatch (surcharges apply).', 'quote-pilot' ); ?></small>
						</span>
					</label>
				</div>

				<!-- Coupon promotion -->
				<div class="qp-form-section qp-conditional-field" data-field="coupon_code" style="margin-top: 24px; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
					<label class="qp-field-label" for="coupon_code"><?php echo esc_html( $fields['coupon_code']['label'] ); ?></label>
					<div class="qp-coupon-row">
						<input
							type="text"
							id="coupon_code"
							name="coupon_code"
							placeholder="<?php esc_attr_e( 'ENTER CODE', 'quote-pilot' ); ?>"
							class="qp-coupon-input"
						/>
						<button type="button" class="qp-coupon-btn" id="qp-apply-coupon-btn"><?php esc_html_e( 'Apply', 'quote-pilot' ); ?></button>
					</div>
					<div class="qp-coupon-message" id="qp-coupon-message"></div>
				</div>
			</div>

			<!-- STEP 5: Your Details & Account -->
			<div class="qp-step" data-step="5" role="tabpanel" aria-label="<?php esc_attr_e( 'Booking & Account Info', 'quote-pilot' ); ?>">
				<p class="qp-step-intro"><?php esc_html_e( 'Complete your booking details and manage your account settings.', 'quote-pilot' ); ?></p>
				
				<!-- Contact Fields -->
				<div class="qp-form-section">
					<label class="qp-field-label" for="customer_name"><?php echo esc_html( $fields['customer_name']['label'] ); ?></label>
					<input
						type="text"
						id="customer_name"
						name="customer_name"
						placeholder="<?php esc_attr_e( 'Your name', 'quote-pilot' ); ?>"
						class="qp-text-input"
						required
					/>
				</div>

				<div class="qp-form-grid">
					<div class="qp-form-section">
						<label class="qp-field-label" for="customer_email"><?php echo esc_html( $fields['customer_email']['label'] ); ?></label>
						<input
							type="email"
							id="customer_email"
							name="customer_email"
							placeholder="<?php esc_attr_e( 'name@domain.com', 'quote-pilot' ); ?>"
							class="qp-text-input"
							required
						/>
					</div>
					<div class="qp-form-section">
						<label class="qp-field-label" for="customer_phone"><?php echo esc_html( $fields['customer_phone']['label'] ); ?></label>
						<input
							type="tel"
							id="customer_phone"
							name="customer_phone"
							placeholder="<?php esc_attr_e( 'Phone number', 'quote-pilot' ); ?>"
							class="qp-text-input"
							required
						/>
					</div>
				</div>

				<div class="qp-form-section">
					<label class="qp-field-label" for="customer_address"><?php echo esc_html( $fields['customer_address']['label'] ); ?></label>
					<textarea
						id="customer_address"
						name="customer_address"
						rows="3"
						placeholder="<?php esc_attr_e( 'Full address with unit number...', 'quote-pilot' ); ?>"
						class="qp-textarea-input"
						required
					></textarea>
				</div>

				<!-- Account Creation Toggle -->
				<div class="qp-form-section qp-account-toggle-section" style="margin-top: 20px;">
					<label class="qp-checkbox-label">
						<input
							type="checkbox"
							id="create_account"
							name="create_account"
							value="1"
							class="qp-checkbox-input"
						/>
						<span class="qp-checkbox-styled"></span>
						<span class="qp-checkbox-text">
							<strong><?php echo esc_html( $fields['create_account']['label'] ); ?></strong>
						</span>
					</label>
				</div>

				<!-- Dynamically revealed password -->
				<div class="qp-form-section qp-password-container" id="qp-password-container" style="display: none; margin-top: 10px;">
					<label class="qp-field-label" for="password"><?php echo esc_html( $fields['password']['label'] ); ?></label>
					<input
						type="password"
						id="password"
						name="password"
						placeholder="<?php esc_attr_e( 'Choose a secure password...', 'quote-pilot' ); ?>"
						class="qp-text-input"
					/>
				</div>

				<!-- Consent Box Area -->
				<div class="qp-consent-section" style="margin-top: 24px; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
					<?php if ( 'merged' === $consent_mode ) : ?>
						<!-- Merged Consent Box -->
						<div class="qp-form-section">
							<label class="qp-checkbox-label">
								<input
									type="checkbox"
									id="consent_all"
									name="consent_all"
									value="1"
									class="qp-checkbox-input"
									required
								/>
								<span class="qp-checkbox-styled"></span>
								<span class="qp-checkbox-text">
									<?php
									printf(
										/* translators: 1: Terms URL, 2: Privacy URL */
										wp_kses_post( __( 'I agree to the <a href="%1$s" target="_blank">Terms & Conditions</a> and <a href="%2$s" target="_blank">Privacy Policy</a>.', 'quote-pilot' ) ),
										$terms_url,
										$privacy_url
									);
									?> <span class="qp-required-mark">*</span>
								</span>
							</label>
						</div>
					<?php else : ?>
						<!-- Split Consent Boxes -->
						<div class="qp-form-section">
							<label class="qp-checkbox-label">
								<input
									type="checkbox"
									id="consent_terms"
									name="consent_terms"
									value="1"
									class="qp-checkbox-input"
									required
								/>
								<span class="qp-checkbox-styled"></span>
								<span class="qp-checkbox-text">
									<?php
									printf(
										/* translators: 1: Terms URL */
										wp_kses_post( __( 'I accept the <a href="%1$s" target="_blank">Terms & Conditions</a>.', 'quote-pilot' ) ),
										$terms_url
									);
									?> <span class="qp-required-mark">*</span>
								</span>
							</label>
						</div>

						<div class="qp-form-section">
							<label class="qp-checkbox-label">
								<input
									type="checkbox"
									id="consent_privacy"
									name="consent_privacy"
									value="1"
									class="qp-checkbox-input"
									required
								/>
								<span class="qp-checkbox-styled"></span>
								<span class="qp-checkbox-text">
									<?php
									printf(
										/* translators: 1: Privacy URL */
										wp_kses_post( __( 'I agree to the <a href="%1$s" target="_blank">Privacy Policy</a>.', 'quote-pilot' ) ),
										$privacy_url
									);
									?> <span class="qp-required-mark">*</span>
								</span>
							</label>
						</div>

						<div class="qp-form-section">
							<label class="qp-checkbox-label">
								<input
									type="checkbox"
									id="consent_marketing"
									name="consent_marketing"
									value="1"
									class="qp-checkbox-input"
								/>
								<span class="qp-checkbox-styled"></span>
								<span class="qp-checkbox-text">
									<?php esc_html_e( 'I would like to receive special offers, recovery deals, and clean reminders.', 'quote-pilot' ); ?>
								</span>
							</label>
						</div>
					<?php endif; ?>
				</div>

			</div>
		</div>

		<!-- 3. Form Submission Errors Alert -->
		<div class="qp-form-errors-alert" id="qp-form-errors-alert" style="display: none;"></div>

		<!-- 4. Sticky Summary Footer (Thumb Zone) -->
		<div class="qp-summary-footer" id="qp-summary-footer">
			<div class="qp-summary-info">
				<div class="qp-summary-service" id="qp-summary-service"><?php esc_html_e( 'No Service Selected', 'quote-pilot' ); ?></div>
				<div class="qp-summary-price">
					<span class="qp-summary-amount" id="qp-summary-amount"><?php echo esc_html( $currency_symbol . '0.00' ); ?></span>
					<span class="qp-summary-tax-label" id="qp-summary-tax-label"><?php esc_html_e( 'est. total', 'quote-pilot' ); ?></span>
				</div>
			</div>
			<div class="qp-summary-actions">
				<button type="button" class="qp-nav-btn qp-btn-back" id="qp-btn-back" style="display: none;">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
				</button>
				<button type="button" class="qp-nav-btn qp-btn-next" id="qp-btn-next">
					<span class="qp-btn-text"><?php esc_html_e( 'Next', 'quote-pilot' ); ?></span>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>

		<!-- Hidden display price input (ignored by server recalc) -->
		<input type="hidden" id="qp_display_total" name="qp_display_total" value="0.00" />
	</form>
</div>
