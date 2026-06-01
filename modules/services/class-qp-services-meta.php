<?php
/**
 * Services pricing meta box.
 *
 * Adds a "Pricing Configuration" meta box to the qp_service edit
 * screen, renders the pricing fields, and saves them securely.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Services_Meta' ) ) :

	/**
	 * Class QP_Services_Meta
	 *
	 * Handles the pricing meta box UI and persistence for services.
	 */
	class QP_Services_Meta {

		/**
		 * Nonce action identifier.
		 *
		 * @var string
		 */
		const NONCE_ACTION = 'qp_service_pricing_save';

		/**
		 * Nonce field name.
		 *
		 * @var string
		 */
		const NONCE_NAME = '_qp_pricing_nonce';

		/**
		 * All pricing-related meta keys (without the _qp_ prefix) that are basic floats.
		 *
		 * @var array
		 */
		private static $price_fields = array(
			'base_price',
			'price_per_sqft',
			'price_per_bedroom',
			'price_per_bathroom',
			'price_per_extra_bathroom',
			'price_per_living_room',
			'price_per_story',
			'price_per_oven',
		);

		/**
		 * Toggle meta keys — which input fields apply to this service.
		 *
		 * @var array
		 */
		private static $toggle_fields = array(
			'enable_sqft',
			'enable_bedrooms',
			'enable_bathrooms',
			'enable_living_room',
			'enable_stories',
			'enable_oven',
			'enable_addons',
		);

		/**
		 * Constructor — register hooks.
		 */
		public function __construct() {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
			add_action( 'save_post_qp_service', array( $this, 'save_meta' ), 10, 2 );
		}

		/**
		 * Register the Pricing Configuration meta box.
		 *
		 * @return void
		 */
		public function add_meta_box() {
			add_meta_box(
				'qp_service_pricing',
				__( 'Pricing Configuration', 'quote-pilot' ),
				array( $this, 'render_meta_box' ),
				'qp_service',
				'normal',
				'high'
			);
		}

		/**
		 * Render the meta box form fields.
		 *
		 * @param \WP_Post $post The current post object.
		 * @return void
		 */
		public function render_meta_box( $post ) {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

			$pricing = self::get_pricing( $post->ID );
			?>
			<style>
				.qp-meta-container {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
					color: #2c3338;
					max-width: 100%;
				}
				.qp-meta-section {
					margin: 24px 0 12px;
					font-size: 14px;
					font-weight: 600;
					border-bottom: 2px solid #e2e8f0;
					padding-bottom: 8px;
					color: #1e293b;
					display: flex;
					align-items: center;
					justify-content: space-between;
				}
				.qp-meta-card {
					background: #ffffff;
					border: 1px solid #e2e8f0;
					border-radius: 8px;
					padding: 16px;
					margin-bottom: 16px;
					box-shadow: 0 1px 3px rgba(0,0,0,0.02);
					transition: border-color 0.2s ease, box-shadow 0.2s ease;
				}
				.qp-meta-card:hover {
					border-color: #cbd5e1;
					box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
				}
				.qp-toggle-row {
					display: flex;
					align-items: center;
					justify-content: space-between;
				}
				.qp-toggle-row label {
					display: flex;
					align-items: center;
					gap: 8px;
					font-weight: 600;
					font-size: 13px;
					color: #334155;
					cursor: pointer;
				}
				.qp-toggle-row input[type="checkbox"] {
					width: 18px;
					height: 18px;
					border-radius: 4px;
					border: 1.5px solid #cbd5e1;
					margin: 0;
					cursor: pointer;
				}
				.qp-conditional-inputs {
					margin-top: 14px;
					padding-top: 14px;
					border-top: 1px dashed #e2e8f0;
					display: none; /* Controlled dynamically by JS */
				}
				.qp-input-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
					gap: 16px;
				}
				.qp-field-group {
					display: flex;
					flex-direction: column;
					gap: 6px;
				}
				.qp-field-group label {
					font-size: 12px;
					font-weight: 500;
					color: #64748b;
				}
				.qp-field-group input, .qp-field-group select {
					padding: 8px 12px;
					border-radius: 6px;
					border: 1px solid #cbd5e1;
					font-size: 13px;
					color: #1e293b;
					background: #f8fafc;
					transition: all 0.15s ease;
				}
				.qp-field-group input:focus, .qp-field-group select:focus {
					outline: none;
					border-color: #6366f1;
					box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
					background: #ffffff;
				}
				.qp-multiplier-box {
					background: #f8fafc;
					border-radius: 6px;
					border: 1px solid #e2e8f0;
					padding: 12px;
					margin-top: 12px;
				}
				.qp-multiplier-box-title {
					font-size: 11px;
					font-weight: 700;
					text-transform: uppercase;
					letter-spacing: 0.05em;
					color: #64748b;
					margin-bottom: 10px;
					display: flex;
					align-items: center;
					gap: 6px;
				}
				/* Addons Repeatable UI */
				.qp-addons-table {
					width: 100%;
					border-collapse: collapse;
					margin-top: 10px;
				}
				.qp-addons-table th {
					text-align: left;
					font-size: 11px;
					font-weight: 600;
					text-transform: uppercase;
					color: #64748b;
					padding: 8px;
					border-bottom: 2px solid #e2e8f0;
				}
				.qp-addons-table td {
					padding: 8px 4px;
					border-bottom: 1px solid #f1f5f9;
				}
				.qp-btn {
					display: inline-flex;
					align-items: center;
					justify-content: center;
					padding: 6px 12px;
					font-size: 12px;
					font-weight: 500;
					border-radius: 6px;
					border: 1px solid transparent;
					cursor: pointer;
					transition: all 0.15s ease;
				}
				.qp-btn-danger {
					background: #fee2e2;
					color: #ef4444;
					border-color: #fca5a5;
				}
				.qp-btn-danger:hover {
					background: #fecaca;
					color: #dc2626;
				}
				.qp-btn-primary {
					background: #eef2ff;
					color: #4f46e5;
					border-color: #c7d2fe;
					margin-top: 10px;
				}
				.qp-btn-primary:hover {
					background: #e0e7ff;
					color: #4338ca;
				}
				.qp-badge {
					display: inline-block;
					padding: 2px 6px;
					font-size: 10px;
					font-weight: 600;
					border-radius: 4px;
					background: #f1f5f9;
					color: #475569;
				}
			</style>

			<div class="qp-meta-container">
				<!-- Base Price: Always visible -->
				<div class="qp-meta-card" style="border-left: 4px solid #6366f1;">
					<div class="qp-input-grid">
						<div class="qp-field-group">
							<label for="qp_base_price" style="font-weight: 600; color: #4f46e5;">
								<?php esc_html_e( 'Base Price ($)', 'quote-pilot' ); ?>
							</label>
							<input
								type="number"
								step="0.01"
								min="0"
								id="qp_base_price"
								name="qp_base_price"
								value="<?php echo esc_attr( $pricing['base_price'] ); ?>"
								style="font-size: 15px; font-weight: 600; border-color: #c7d2fe;"
							/>
						</div>
						<div class="qp-field-group" style="justify-content: center; padding-left: 20px;">
							<label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer; margin: 0;">
								<input
									type="checkbox"
									name="qp_service_active"
									value="1"
									<?php checked( $pricing['service_active'], 1 ); ?>
									style="width: 18px; height: 18px; border-radius: 4px;"
								/>
								<?php esc_html_e( 'Service is selectable in quotes', 'quote-pilot' ); ?>
							</label>
						</div>
					</div>
				</div>

				<p class="qp-meta-section"><?php esc_html_e( 'Calculated Property Rules & Multipliers', 'quote-pilot' ); ?></p>

				<!-- 1. Square Footage -->
				<div class="qp-meta-card">
					<div class="qp-toggle-row">
						<label for="qp_enable_sqft">
							<input
								type="checkbox"
								name="qp_enable_sqft"
								id="qp_enable_sqft"
								class="qp-toggle"
								value="1"
								<?php checked( $pricing['enable_sqft'], 1 ); ?>
							/>
							<?php esc_html_e( 'Enable Square Footage Pricing', 'quote-pilot' ); ?>
						</label>
						<span class="qp-badge">sqft</span>
					</div>
					<div class="qp-conditional-inputs" data-trigger="qp_enable_sqft">
						<div class="qp-input-grid">
							<div class="qp-field-group">
								<label for="qp_price_per_sqft"><?php esc_html_e( 'Price per Sq Ft ($)', 'quote-pilot' ); ?></label>
								<input
									type="number"
									step="0.001"
									min="0"
									id="qp_price_per_sqft"
									name="qp_price_per_sqft"
									value="<?php echo esc_attr( $pricing['price_per_sqft'] ); ?>"
								/>
							</div>
						</div>
					</div>
				</div>

				<!-- 2. Bedrooms -->
				<div class="qp-meta-card">
					<div class="qp-toggle-row">
						<label for="qp_enable_bedrooms">
							<input
								type="checkbox"
								name="qp_enable_bedrooms"
								id="qp_enable_bedrooms"
								class="qp-toggle"
								value="1"
								<?php checked( $pricing['enable_bedrooms'], 1 ); ?>
							/>
							<?php esc_html_e( 'Enable Bedroom Pricing', 'quote-pilot' ); ?>
						</label>
						<span class="qp-badge">bedrooms</span>
					</div>
					<div class="qp-conditional-inputs" data-trigger="qp_enable_bedrooms">
						<div class="qp-input-grid">
							<div class="qp-field-group">
								<label for="qp_price_per_bedroom"><?php esc_html_e( 'Price per Bedroom ($)', 'quote-pilot' ); ?></label>
								<input
									type="number"
									step="0.01"
									min="0"
									id="qp_price_per_bedroom"
									name="qp_price_per_bedroom"
									value="<?php echo esc_attr( $pricing['price_per_bedroom'] ); ?>"
								/>
							</div>
						</div>
					</div>
				</div>

				<!-- 3. Bathrooms -->
				<div class="qp-meta-card">
					<div class="qp-toggle-row">
						<label for="qp_enable_bathrooms">
							<input
								type="checkbox"
								name="qp_enable_bathrooms"
								id="qp_enable_bathrooms"
								class="qp-toggle"
								value="1"
								<?php checked( $pricing['enable_bathrooms'], 1 ); ?>
							/>
							<?php esc_html_e( 'Enable Bathroom Pricing', 'quote-pilot' ); ?>
						</label>
						<span class="qp-badge">bathrooms</span>
					</div>
					<div class="qp-conditional-inputs" data-trigger="qp_enable_bathrooms">
						<div class="qp-input-grid">
							<div class="qp-field-group">
								<label for="qp_price_per_bathroom"><?php esc_html_e( 'Price per First Bathroom ($)', 'quote-pilot' ); ?></label>
								<input
									type="number"
									step="0.01"
									min="0"
									id="qp_price_per_bathroom"
									name="qp_price_per_bathroom"
									value="<?php echo esc_attr( $pricing['price_per_bathroom'] ); ?>"
								/>
							</div>
							<div class="qp-field-group">
								<label for="qp_price_per_extra_bathroom"><?php esc_html_e( 'Price per Extra Bathroom ($)', 'quote-pilot' ); ?></label>
								<input
									type="number"
									step="0.01"
									min="0"
									id="qp_price_per_extra_bathroom"
									name="qp_price_per_extra_bathroom"
									value="<?php echo esc_attr( $pricing['price_per_extra_bathroom'] ); ?>"
								/>
							</div>
						</div>
					</div>
				</div>

				<!-- Shared Room Size Multipliers (shown when Bedrooms or Bathrooms are enabled) -->
				<div class="qp-meta-card qp-conditional-inputs" data-trigger-either="qp_enable_bedrooms,qp_enable_bathrooms">
					<div class="qp-multiplier-box">
						<div class="qp-multiplier-box-title">
							<span class="dashicons dashicons-text-flow" style="font-size:16px; width:16px; height:16px;"></span>
							<?php esc_html_e( 'Standard Room Size Multipliers', 'quote-pilot' ); ?>
						</div>
						<p class="description" style="margin-top:0; margin-bottom:10px;">
							<?php esc_html_e( 'Multipliers applied to bedroom & bathroom charges based on client room size selections.', 'quote-pilot' ); ?>
						</p>
						<div class="qp-input-grid">
							<div class="qp-field-group">
								<label><?php esc_html_e( 'Small Size Multiplier', 'quote-pilot' ); ?></label>
								<input
									type="number"
									step="0.05"
									min="0"
									name="qp_room_size_small"
									value="<?php echo esc_attr( $pricing['room_size_multipliers']['small'] ); ?>"
								/>
							</div>
							<div class="qp-field-group">
								<label><?php esc_html_e( 'Medium Size Multiplier', 'quote-pilot' ); ?></label>
								<input
									type="number"
									step="0.05"
									min="0"
									name="qp_room_size_medium"
									value="<?php echo esc_attr( $pricing['room_size_multipliers']['medium'] ); ?>"
								/>
							</div>
							<div class="qp-field-group">
								<label><?php esc_html_e( 'Large Size Multiplier', 'quote-pilot' ); ?></label>
								<input
									type="number"
									step="0.05"
									min="0"
									name="qp_room_size_large"
									value="<?php echo esc_attr( $pricing['room_size_multipliers']['large'] ); ?>"
								/>
							</div>
						</div>
					</div>
				</div>

				<!-- 4. Living Room -->
				<div class="qp-meta-card">
					<div class="qp-toggle-row">
						<label for="qp_enable_living_room">
							<input
								type="checkbox"
								name="qp_enable_living_room"
								id="qp_enable_living_room"
								class="qp-toggle"
								value="1"
								<?php checked( $pricing['enable_living_room'], 1 ); ?>
							/>
							<?php esc_html_e( 'Enable Living Room Pricing', 'quote-pilot' ); ?>
						</label>
						<span class="qp-badge">living_rooms</span>
					</div>
					<div class="qp-conditional-inputs" data-trigger="qp_enable_living_room">
						<div class="qp-input-grid">
							<div class="qp-field-group">
								<label for="qp_price_per_living_room"><?php esc_html_e( 'Price per Living Room ($)', 'quote-pilot' ); ?></label>
								<input
									type="number"
									step="0.01"
									min="0"
									id="qp_price_per_living_room"
									name="qp_price_per_living_room"
									value="<?php echo esc_attr( $pricing['price_per_living_room'] ); ?>"
								/>
							</div>
						</div>

						<!-- Living Room Size Multipliers -->
						<div class="qp-multiplier-box" style="margin-top: 14px;">
							<div class="qp-multiplier-box-title" style="margin-bottom:6px;">
								<label style="display:inline-flex; align-items:center; gap:6px; text-transform:none; letter-spacing:normal; color:#475569; font-weight:600;">
									<input
										type="checkbox"
										name="qp_living_room_size_enabled"
										id="qp_living_room_size_enabled"
										value="1"
										<?php checked( $pricing['living_room_size_multipliers']['enabled'], 1 ); ?>
									/>
									<?php esc_html_e( 'Apply custom size multipliers for living rooms', 'quote-pilot' ); ?>
								</label>
							</div>
							<div class="qp-conditional-inputs" data-trigger="qp_living_room_size_enabled" style="margin-top:0; padding-top:10px; border:none;">
								<div class="qp-input-grid">
									<div class="qp-field-group">
										<label><?php esc_html_e( 'Small Size Multiplier', 'quote-pilot' ); ?></label>
										<input
											type="number"
											step="0.05"
											min="0"
											name="qp_living_room_size_small"
											value="<?php echo esc_attr( $pricing['living_room_size_multipliers']['small'] ); ?>"
										/>
									</div>
									<div class="qp-field-group">
										<label><?php esc_html_e( 'Medium Size Multiplier', 'quote-pilot' ); ?></label>
										<input
											type="number"
											step="0.05"
											min="0"
											name="qp_living_room_size_medium"
											value="<?php echo esc_attr( $pricing['living_room_size_multipliers']['medium'] ); ?>"
										/>
									</div>
									<div class="qp-field-group">
										<label><?php esc_html_e( 'Large Size Multiplier', 'quote-pilot' ); ?></label>
										<input
											type="number"
											step="0.05"
											min="0"
											name="qp_living_room_size_large"
											value="<?php echo esc_attr( $pricing['living_room_size_multipliers']['large'] ); ?>"
										/>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- 5. Stories -->
				<div class="qp-meta-card">
					<div class="qp-toggle-row">
						<label for="qp_enable_stories">
							<input
								type="checkbox"
								name="qp_enable_stories"
								id="qp_enable_stories"
								class="qp-toggle"
								value="1"
								<?php checked( $pricing['enable_stories'], 1 ); ?>
							/>
							<?php esc_html_e( 'Enable Story / Floors Surcharge', 'quote-pilot' ); ?>
						</label>
						<span class="qp-badge">stories</span>
					</div>
					<div class="qp-conditional-inputs" data-trigger="qp_enable_stories">
						<div class="qp-input-grid">
							<div class="qp-field-group">
								<label for="qp_price_per_story"><?php esc_html_e( 'Surcharge per additional floor ($)', 'quote-pilot' ); ?></label>
								<input
									type="number"
									step="0.01"
									min="0"
									id="qp_price_per_story"
									name="qp_price_per_story"
									value="<?php echo esc_attr( $pricing['price_per_story'] ); ?>"
								/>
							</div>
						</div>
					</div>
				</div>

				<!-- 6. Oven -->
				<div class="qp-meta-card">
					<div class="qp-toggle-row">
						<label for="qp_enable_oven">
							<input
								type="checkbox"
								name="qp_enable_oven"
								id="qp_enable_oven"
								class="qp-toggle"
								value="1"
								<?php checked( $pricing['enable_oven'], 1 ); ?>
							/>
							<?php esc_html_e( 'Enable Oven Cleaning', 'quote-pilot' ); ?>
						</label>
						<span class="qp-badge">ovens</span>
					</div>
					<div class="qp-conditional-inputs" data-trigger="qp_enable_oven">
						<div class="qp-input-grid">
							<div class="qp-field-group">
								<label for="qp_price_per_oven"><?php esc_html_e( 'Price per Oven ($)', 'quote-pilot' ); ?></label>
								<input
									type="number"
									step="0.01"
									min="0"
									id="qp_price_per_oven"
									name="qp_price_per_oven"
									value="<?php echo esc_attr( $pricing['price_per_oven'] ); ?>"
								/>
							</div>
							<div class="qp-field-group">
								<label for="qp_oven_size_large_multiplier"><?php esc_html_e( 'Large Oven Size Multiplier', 'quote-pilot' ); ?></label>
								<input
									type="number"
									step="0.05"
									min="0"
									id="qp_oven_size_large_multiplier"
									name="qp_oven_size_large_multiplier"
									value="<?php echo esc_attr( $pricing['oven_size_large_multiplier'] ); ?>"
								/>
							</div>
						</div>
					</div>
				</div>

				<!-- 7. Add-ons Repeatable UI -->
				<div class="qp-meta-card">
					<div class="qp-toggle-row">
						<label for="qp_enable_addons">
							<input
								type="checkbox"
								name="qp_enable_addons"
								id="qp_enable_addons"
								class="qp-toggle"
								value="1"
								<?php checked( $pricing['enable_addons'], 1 ); ?>
							/>
							<?php esc_html_e( 'Enable Service Add-ons', 'quote-pilot' ); ?>
						</label>
						<span class="qp-badge">addons</span>
					</div>
					<div class="qp-conditional-inputs" data-trigger="qp_enable_addons">
						<p class="description" style="margin-top:0; margin-bottom:10px;">
							<?php esc_html_e( 'Define additional options or upsells custom to this service.', 'quote-pilot' ); ?>
						</p>

						<table class="qp-addons-table" id="qp-addons-table">
							<thead>
								<tr>
									<th style="width:25%;"><?php esc_html_e( 'Slug / Identifier', 'quote-pilot' ); ?></th>
									<th style="width:35%;"><?php esc_html_e( 'Display Label', 'quote-pilot' ); ?></th>
									<th style="width:20%;"><?php esc_html_e( 'Charge Type', 'quote-pilot' ); ?></th>
									<th style="width:15%;"><?php esc_html_e( 'Rate / Value', 'quote-pilot' ); ?></th>
									<th style="width:5%;"></th>
								</tr>
							</thead>
							<tbody id="qp-addons-tbody">
								<?php
								$addon_index = 0;
								if ( ! empty( $pricing['addons'] ) ) :
									foreach ( $pricing['addons'] as $addon ) :
										?>
										<tr data-index="<?php echo esc_attr( $addon_index ); ?>">
											<td>
												<input
													type="text"
													name="qp_addons[<?php echo esc_attr( $addon_index ); ?>][slug]"
													value="<?php echo esc_attr( $addon['slug'] ); ?>"
													placeholder="extra-window"
													style="width:95%;"
													required
												/>
											</td>
											<td>
												<input
													type="text"
													name="qp_addons[<?php echo esc_attr( $addon_index ); ?>][label]"
													value="<?php echo esc_attr( $addon['label'] ); ?>"
													placeholder="Window Cleaning"
													style="width:95%;"
													required
												/>
											</td>
											<td>
												<select name="qp_addons[<?php echo esc_attr( $addon_index ); ?>][type]" style="width:95%;">
													<option value="flat" <?php selected( $addon['type'], 'flat' ); ?>><?php esc_html_e( 'Flat Price ($)', 'quote-pilot' ); ?></option>
													<option value="percent" <?php selected( $addon['type'], 'percent' ); ?>><?php esc_html_e( 'Percentage (%)', 'quote-pilot' ); ?></option>
												</select>
											</td>
											<td>
												<input
													type="number"
													step="0.01"
													min="0"
													name="qp_addons[<?php echo esc_attr( $addon_index ); ?>][value]"
													value="<?php echo esc_attr( $addon['value'] ); ?>"
													placeholder="15.00"
													style="width:90%;"
													required
												/>
											</td>
											<td>
												<button type="button" class="qp-btn qp-btn-danger qp-remove-addon-row">
													<span class="dashicons dashicons-no-alt"></span>
												</button>
											</td>
										</tr>
										<?php
										$addon_index++;
									endforeach;
								endif;
								?>
							</tbody>
						</table>

						<button type="button" class="qp-btn qp-btn-primary" id="qp-add-addon-row">
							<span class="dashicons dashicons-plus-alt" style="margin-right:4px;"></span>
							<?php esc_html_e( 'Add Add-on Row', 'quote-pilot' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Dynamic Interface JS (Sliding Panels + Repeatable UI Manager) -->
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					// 1. Toggle visibility function
					function updateToggles() {
						var checkboxes = document.querySelectorAll('.qp-toggle, #qp_living_room_size_enabled');
						var enabledToggles = [];

						checkboxes.forEach(function(cb) {
							var container = document.querySelector('.qp-conditional-inputs[data-trigger="' + cb.id + '"]');
							if (container) {
								if (cb.checked) {
									container.style.display = 'block';
								} else {
									container.style.display = 'none';
								}
							}
							if (cb.checked) {
								enabledToggles.push(cb.id);
							}
						});

						// Handle combined triggers (e.g. either Bedrooms or Bathrooms)
						var multiTriggers = document.querySelectorAll('.qp-conditional-inputs[data-trigger-either]');
						multiTriggers.forEach(function(container) {
							var triggers = container.getAttribute('data-trigger-either').split(',');
							var shouldShow = triggers.some(function(t) {
								return enabledToggles.indexOf(t) !== -1;
							});
							container.style.display = shouldShow ? 'block' : 'none';
						});
					}

					// Bind event listeners to checkbox triggers
					document.querySelectorAll('.qp-toggle, #qp_living_room_size_enabled').forEach(function(cb) {
						cb.addEventListener('change', updateToggles);
					});

					// Initial run on render
					updateToggles();

					// 2. Repeatable Add-ons Manager
					var tbody = document.getElementById('qp-addons-tbody');
					var addBtn = document.getElementById('qp-add-addon-row');

					if (tbody && addBtn) {
						addBtn.addEventListener('click', function() {
							var index = tbody.querySelectorAll('tr').length;
							var row = document.createElement('tr');
							row.setAttribute('data-index', index);

							row.innerHTML = '<td>' +
								'<input type="text" name="qp_addons[' + index + '][slug]" placeholder="extra-window" style="width:95%;" required />' +
								'</td><td>' +
								'<input type="text" name="qp_addons[' + index + '][label]" placeholder="Window Cleaning" style="width:95%;" required />' +
								'</td><td>' +
								'<select name="qp_addons[' + index + '][type]" style="width:95%;">' +
								'<option value="flat"><?php esc_html_e( "Flat Price ($)", "quote-pilot" ); ?></option>' +
								'<option value="percent"><?php esc_html_e( "Percentage (%)", "quote-pilot" ); ?></option>' +
								'</select>' +
								'</td><td>' +
								'<input type="number" step="0.01" min="0" name="qp_addons[' + index + '][value]" placeholder="15.00" style="width:90%;" required />' +
								'</td><td>' +
								'<button type="button" class="qp-btn qp-btn-danger qp-remove-addon-row">' +
								'<span class="dashicons dashicons-no-alt"></span>' +
								'</button>' +
								'</td>';

							tbody.appendChild(row);
							bindRowRemove(row.querySelector('.qp-remove-addon-row'));
						});

						// Bind initial remove buttons
						tbody.querySelectorAll('.qp-remove-addon-row').forEach(function(btn) {
							bindRowRemove(btn);
						});
					}

					function bindRowRemove(btn) {
						btn.addEventListener('click', function() {
							var row = btn.closest('tr');
							if (row) {
								row.remove();
								reindexAddonRows();
							}
						});
					}

					function reindexAddonRows() {
						var rows = tbody.querySelectorAll('tr');
						rows.forEach(function(row, newIndex) {
							row.setAttribute('data-index', newIndex);
							row.querySelectorAll('input, select').forEach(function(input) {
								var name = input.getAttribute('name');
								if (name) {
									var updatedName = name.replace(/qp_addons\[\d+\]/, 'qp_addons[' + newIndex + ']');
									input.setAttribute('name', updatedName);
								}
							});
						});
					}
				});
			</script>
			<?php
		}

		/**
		 * Save the pricing meta fields.
		 *
		 * @param int      $post_id The post ID.
		 * @param \WP_Post $post    The post object.
		 * @return void
		 */
		public function save_meta( $post_id, $post ) {
			/* --- Security checks ----------------------------------- */
			if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
				return;
			}

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
				return;
			}

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			// Bypass revisions to avoid saving orphaned metadata on revision posts
			if ( wp_is_post_revision( $post_id ) ) {
				return;
			}

			/* --- Save price fields (decimal) ----------------------- */
			foreach ( self::$price_fields as $field ) {
				$key   = '_qp_' . $field;
				$value = isset( $_POST[ 'qp_' . $field ] ) ? floatval( $_POST[ 'qp_' . $field ] ) : 0.00;
				update_post_meta( $post_id, $key, $value );
			}

			/* --- Save toggle fields (0 or 1) ----------------------- */
			foreach ( self::$toggle_fields as $field ) {
				$key   = '_qp_' . $field;
				$value = ! empty( $_POST[ 'qp_' . $field ] ) ? 1 : 0;
				update_post_meta( $post_id, $key, $value );
			}

			/* --- Save service_active (0 or 1) ---------------------- */
			$active = ! empty( $_POST['qp_service_active'] ) ? 1 : 0;
			update_post_meta( $post_id, '_qp_service_active', $active );

			/* --- Save large oven size multiplier (default 1.5) ----- */
			$oven_mult = isset( $_POST['qp_oven_size_large_multiplier'] ) ? floatval( $_POST['qp_oven_size_large_multiplier'] ) : 1.5;
			update_post_meta( $post_id, '_qp_oven_size_large_multiplier', $oven_mult );

			/* --- Save room size multipliers ------------------------- */
			$room_multipliers = array(
				'small'  => isset( $_POST['qp_room_size_small'] ) ? max( 0, floatval( $_POST['qp_room_size_small'] ) ) : 1.0,
				'medium' => isset( $_POST['qp_room_size_medium'] ) ? max( 0, floatval( $_POST['qp_room_size_medium'] ) ) : 1.0,
				'large'  => isset( $_POST['qp_room_size_large'] ) ? max( 0, floatval( $_POST['qp_room_size_large'] ) ) : 1.0,
			);
			update_post_meta( $post_id, '_qp_room_size_multipliers', $room_multipliers );

			/* --- Save living room size multipliers ------------------ */
			$living_room_multipliers = array(
				'enabled' => ! empty( $_POST['qp_living_room_size_enabled'] ) ? 1 : 0,
				'small'   => isset( $_POST['qp_living_room_size_small'] ) ? max( 0, floatval( $_POST['qp_living_room_size_small'] ) ) : 1.0,
				'medium'  => isset( $_POST['qp_living_room_size_medium'] ) ? max( 0, floatval( $_POST['qp_living_room_size_medium'] ) ) : 1.0,
				'large'   => isset( $_POST['qp_living_room_size_large'] ) ? max( 0, floatval( $_POST['qp_living_room_size_large'] ) ) : 1.0,
			);
			update_post_meta( $post_id, '_qp_living_room_size_multipliers', $living_room_multipliers );

			/* --- Save repeatable add-ons ---------------------------- */
			$addons = array();
			if ( isset( $_POST['qp_addons'] ) && is_array( $_POST['qp_addons'] ) ) {
				foreach ( $_POST['qp_addons'] as $addon ) {
					if ( empty( $addon['slug'] ) || empty( $addon['label'] ) ) {
						continue;
					}
					$addons[] = array(
						'slug'  => sanitize_key( $addon['slug'] ),
						'label' => sanitize_text_field( $addon['label'] ),
						'type'  => in_array( $addon['type'], array( 'flat', 'percent' ), true ) ? $addon['type'] : 'flat',
						'value' => max( 0, floatval( $addon['value'] ) ),
					);
				}
			}
			update_post_meta( $post_id, '_qp_addons', $addons );
		}

		/**
		 * Retrieve the full pricing configuration for a service.
		 *
		 * Returns an associative array with every pricing, multiplier, toggle,
		 * and repeatable addons configuration. Used by Part 2's price engine to build quotes.
		 *
		 * @param int $service_id The service (post) ID.
		 * @return array Associative array of all pricing config.
		 */
		public static function get_pricing( $service_id ) {
			$data = array();

			// Basic float price fields
			foreach ( self::$price_fields as $field ) {
				$raw = get_post_meta( $service_id, '_qp_' . $field, true );
				$data[ $field ] = ( '' !== $raw ) ? floatval( $raw ) : 0.00;
			}

			// Toggles
			foreach ( self::$toggle_fields as $field ) {
				$raw = get_post_meta( $service_id, '_qp_' . $field, true );
				$data[ $field ] = ( '' !== $raw ) ? absint( $raw ) : 0;
			}

			// Availability
			$raw_active = get_post_meta( $service_id, '_qp_service_active', true );
			$data['service_active'] = ( '' !== $raw_active ) ? absint( $raw_active ) : 1;

			// Large oven size multiplier (default 1.5)
			$raw_oven_mult = get_post_meta( $service_id, '_qp_oven_size_large_multiplier', true );
			$data['oven_size_large_multiplier'] = ( '' !== $raw_oven_mult ) ? floatval( $raw_oven_mult ) : 1.5;

			// Room size multipliers (default 1/1/1)
			$raw_room_mult = get_post_meta( $service_id, '_qp_room_size_multipliers', true );
			$data['room_size_multipliers'] = is_array( $raw_room_mult ) ? $raw_room_mult : array(
				'small'  => 1.0,
				'medium' => 1.0,
				'large'  => 1.0,
			);
			$data['room_size_multipliers'] = array_merge( array(
				'small'  => 1.0,
				'medium' => 1.0,
				'large'  => 1.0,
			), $data['room_size_multipliers'] );

			// Living room size multipliers (default off, 1/1/1)
			$raw_lr_mult = get_post_meta( $service_id, '_qp_living_room_size_multipliers', true );
			$data['living_room_size_multipliers'] = is_array( $raw_lr_mult ) ? $raw_lr_mult : array(
				'enabled' => 0,
				'small'   => 1.0,
				'medium'  => 1.0,
				'large'   => 1.0,
			);
			$data['living_room_size_multipliers'] = array_merge( array(
				'enabled' => 0,
				'small'   => 1.0,
				'medium'  => 1.0,
				'large'   => 1.0,
			), $data['living_room_size_multipliers'] );

			// Add-ons (default empty array)
			$raw_addons = get_post_meta( $service_id, '_qp_addons', true );
			$data['addons'] = is_array( $raw_addons ) ? $raw_addons : array();

			return $data;
		}

		/**
		 * Convert a meta key slug to a human-readable label.
		 *
		 * @param string $field The field slug.
		 * @return string Translated human-readable label.
		 */
		private static function field_label( $field ) {
			$labels = array(
				'base_price'               => __( 'Base Price ($)', 'quote-pilot' ),
				'price_per_sqft'           => __( 'Price per Sq Ft ($)', 'quote-pilot' ),
				'price_per_bedroom'        => __( 'Price per Bedroom ($)', 'quote-pilot' ),
				'price_per_bathroom'       => __( 'Price per Bathroom ($)', 'quote-pilot' ),
				'price_per_extra_bathroom' => __( 'Price per Extra Bathroom ($)', 'quote-pilot' ),
				'price_per_living_room'    => __( 'Price per Living Room ($)', 'quote-pilot' ),
				'price_per_story'          => __( 'Price per Floor / Story Surcharge ($)', 'quote-pilot' ),
				'price_per_oven'           => __( 'Price per Oven ($)', 'quote-pilot' ),
				'enable_sqft'              => __( 'Square Footage', 'quote-pilot' ),
				'enable_bedrooms'          => __( 'Bedrooms', 'quote-pilot' ),
				'enable_bathrooms'         => __( 'Bathrooms', 'quote-pilot' ),
				'enable_living_room'       => __( 'Living Room', 'quote-pilot' ),
				'enable_stories'           => __( 'Stories / Floors', 'quote-pilot' ),
				'enable_oven'              => __( 'Oven Cleaning', 'quote-pilot' ),
				'enable_addons'            => __( 'Add-ons', 'quote-pilot' ),
				'service_active'           => __( 'Active', 'quote-pilot' ),
			);

			return isset( $labels[ $field ] ) ? $labels[ $field ] : ucwords( str_replace( '_', ' ', $field ) );
		}
	}

endif;
