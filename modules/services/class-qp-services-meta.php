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
		 * All pricing-related meta keys (without the _qp_ prefix).
		 *
		 * @var array
		 */
		private static $price_fields = array(
			'base_price',
			'price_per_sqft',
			'price_per_bedroom',
			'price_per_bathroom',
			'price_per_extra_bathroom',
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
				.qp-meta-table { width: 100%; border-collapse: collapse; }
				.qp-meta-table th { text-align: left; padding: 8px 10px; width: 220px; vertical-align: middle; }
				.qp-meta-table td { padding: 8px 10px; }
				.qp-meta-table input[type="number"] { width: 140px; }
				.qp-meta-section { margin: 16px 0 8px; font-size: 14px; font-weight: 600; border-bottom: 1px solid #ddd; padding-bottom: 6px; }
				.qp-toggle-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px 16px; }
				.qp-toggle-grid label { display: flex; align-items: center; gap: 6px; font-size: 13px; }
			</style>

			<!-- Pricing Fields -->
			<p class="qp-meta-section"><?php esc_html_e( 'Price Settings', 'quote-pilot' ); ?></p>
			<table class="qp-meta-table">
				<?php foreach ( self::$price_fields as $field ) : ?>
					<tr>
						<th>
							<label for="qp_<?php echo esc_attr( $field ); ?>">
								<?php echo esc_html( self::field_label( $field ) ); ?>
							</label>
						</th>
						<td>
							<input
								type="number"
								step="0.01"
								min="0"
								id="qp_<?php echo esc_attr( $field ); ?>"
								name="qp_<?php echo esc_attr( $field ); ?>"
								value="<?php echo esc_attr( $pricing[ $field ] ); ?>"
							/>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<!-- Field Toggles -->
			<p class="qp-meta-section"><?php esc_html_e( 'Enabled Quote Fields', 'quote-pilot' ); ?></p>
			<p class="description" style="margin-bottom:10px;">
				<?php esc_html_e( 'Choose which input fields are shown on the quote form for this service.', 'quote-pilot' ); ?>
			</p>
			<div class="qp-toggle-grid">
				<?php foreach ( self::$toggle_fields as $field ) : ?>
					<label>
						<input
							type="checkbox"
							name="qp_<?php echo esc_attr( $field ); ?>"
							value="1"
							<?php checked( $pricing[ $field ], 1 ); ?>
						/>
						<?php echo esc_html( self::field_label( $field ) ); ?>
					</label>
				<?php endforeach; ?>
			</div>

			<!-- Service Active -->
			<p class="qp-meta-section"><?php esc_html_e( 'Availability', 'quote-pilot' ); ?></p>
			<label>
				<input
					type="checkbox"
					name="qp_service_active"
					value="1"
					<?php checked( $pricing['service_active'], 1 ); ?>
				/>
				<?php esc_html_e( 'Service is selectable in quotes', 'quote-pilot' ); ?>
			</label>
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
		}

		/**
		 * Retrieve the full pricing configuration for a service.
		 *
		 * Returns an associative array with every pricing and toggle
		 * field. Used by Part 3's calculator to build quotes.
		 *
		 * @param int $service_id The service (post) ID.
		 * @return array Associative array of all pricing config.
		 */
		public static function get_pricing( $service_id ) {
			$data = array();

			foreach ( self::$price_fields as $field ) {
				$raw = get_post_meta( $service_id, '_qp_' . $field, true );
				$data[ $field ] = ( '' !== $raw ) ? floatval( $raw ) : 0.00;
			}

			foreach ( self::$toggle_fields as $field ) {
				$raw = get_post_meta( $service_id, '_qp_' . $field, true );
				$data[ $field ] = ( '' !== $raw ) ? absint( $raw ) : 0;
			}

			$raw_active = get_post_meta( $service_id, '_qp_service_active', true );
			$data['service_active'] = ( '' !== $raw_active ) ? absint( $raw_active ) : 1;

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
				'base_price'              => __( 'Base Price ($)', 'quote-pilot' ),
				'price_per_sqft'          => __( 'Price per Sq Ft ($)', 'quote-pilot' ),
				'price_per_bedroom'       => __( 'Price per Bedroom ($)', 'quote-pilot' ),
				'price_per_bathroom'      => __( 'Price per Bathroom ($)', 'quote-pilot' ),
				'price_per_extra_bathroom' => __( 'Price per Extra Bathroom ($)', 'quote-pilot' ),
				'price_per_oven'          => __( 'Price per Oven ($)', 'quote-pilot' ),
				'enable_sqft'             => __( 'Square Footage', 'quote-pilot' ),
				'enable_bedrooms'         => __( 'Bedrooms', 'quote-pilot' ),
				'enable_bathrooms'        => __( 'Bathrooms', 'quote-pilot' ),
				'enable_living_room'      => __( 'Living Room', 'quote-pilot' ),
				'enable_stories'          => __( 'Stories / Floors', 'quote-pilot' ),
				'enable_oven'             => __( 'Oven Cleaning', 'quote-pilot' ),
				'enable_addons'           => __( 'Add-ons', 'quote-pilot' ),
				'service_active'          => __( 'Active', 'quote-pilot' ),
			);

			return isset( $labels[ $field ] ) ? $labels[ $field ] : ucwords( str_replace( '_', ' ', $field ) );
		}
	}

endif;
