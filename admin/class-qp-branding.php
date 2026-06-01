<?php
/**
 * Branding controller.
 *
 * Coordinates custom customer styling including logo uploading (via core Media Library),
 * primary and secondary color selection, and typography choices. Outputs custom CSS variables
 * dynamically to both the frontend quote forms and dashboard environments.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Branding' ) ) :

	/**
	 * Class QP_Branding
	 */
	class QP_Branding {

		/**
		 * Register hooks.
		 *
		 * @return void
		 */
		public static function register() {
			add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_media_assets' ) );
			add_action( 'wp_head', array( __CLASS__, 'inject_branding_css' ), 100 );
		}

		/**
		 * Enqueue WP core Media Uploader dependencies on branding page.
		 *
		 * @param string $hook Admin page hook suffix.
		 * @return void
		 */
		public static function enqueue_media_assets( $hook ) {
			if ( strpos( $hook, 'qp-branding' ) !== false ) {
				wp_enqueue_media();
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
			}
		}

		/**
		 * Save branding overrides from POST request.
		 *
		 * @return void
		 */
		public static function handle_save() {
			if ( ! isset( $_POST['qp_save_branding'] ) ) {
				return;
			}

			// Security validation
			if ( ! isset( $_POST['qp_branding_nonce'] ) || ! wp_verify_nonce( $_POST['qp_branding_nonce'], 'qp_save_branding_action' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'quote-pilot' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage branding.', 'quote-pilot' ) );
			}

			$primary   = isset( $_POST['primary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['primary_color'] ) ) : '#6366f1';
			$secondary = isset( $_POST['secondary_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['secondary_color'] ) ) : '#0ea5e9';
			$font      = isset( $_POST['font_family'] ) ? sanitize_text_field( wp_unslash( $_POST['font_family'] ) ) : 'system-ui';
			$logo      = isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '';

			$branding_payload = array(
				'primary_color'   => $primary,
				'secondary_color' => $secondary,
				'font_family'     => $font,
				'logo_url'        => $logo,
			);

			update_option( 'qp_branding', $branding_payload );

			wp_safe_redirect( add_query_arg( array( 'page' => 'qp-branding', 'qp_updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		/**
		 * Inject custom branding variables scoped dynamically.
		 *
		 * @return void
		 */
		public static function inject_branding_css() {
			$branding  = get_option( 'qp_branding', array() );
			$primary   = isset( $branding['primary_color'] ) ? sanitize_hex_color( $branding['primary_color'] ) : '#6366f1';
			$secondary = isset( $branding['secondary_color'] ) ? sanitize_hex_color( $branding['secondary_color'] ) : '#0ea5e9';
			$font      = isset( $branding['font_family'] ) ? sanitize_text_field( $branding['font_family'] ) : 'system-ui';

			// Format Google Font import if custom typography selected
			$google_font_import = '';
			$font_stack         = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';

			if ( 'Inter' === $font ) {
				$google_font_import = "@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');";
				$font_stack         = '"Inter", ' . $font_stack;
			} elseif ( 'Outfit' === $font ) {
				$google_font_import = "@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap');";
				$font_stack         = '"Outfit", ' . $font_stack;
			} elseif ( 'Roboto' === $font ) {
				$google_font_import = "@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap');";
				$font_stack         = '"Roboto", ' . $font_stack;
			}
			?>
			<style type="text/css">
				<?php echo $google_font_import; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stylesheet import URL is completely internal and clean. ?>
				.qp-quote-form, .qp-dashboard {
					--qp-primary: <?php echo esc_html( $primary ); ?>;
					--qp-secondary: <?php echo esc_html( $secondary ); ?>;
					--qp-font: <?php echo $font_stack; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted standard stack. ?>;
				}
			</style>
			<?php
		}

		/**
		 * Render Branding override screen HTML.
		 *
		 * @return void
		 */
		public static function render_page() {
			$branding  = get_option( 'qp_branding', array() );
			$primary   = isset( $branding['primary_color'] ) ? $branding['primary_color'] : '#6366f1';
			$secondary = isset( $branding['secondary_color'] ) ? $branding['secondary_color'] : '#0ea5e9';
			$font      = isset( $branding['font_family'] ) ? $branding['font_family'] : 'system-ui';
			$logo      = isset( $branding['logo_url'] ) ? $branding['logo_url'] : '';
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Branding Colors & Fonts', 'quote-pilot' ); ?></h1>
				<p class="description"><?php esc_html_e( 'Customize the look and feel of your quote forms and customer dashboard.', 'quote-pilot' ); ?></p>

				<?php if ( isset( $_GET['qp_updated'] ) ) : ?>
					<div class="notice notice-success is-dismissible">
						<p><?php esc_html_e( 'Branding preferences updated.', 'quote-pilot' ); ?></p>
					</div>
				<?php endif; ?>

				<form method="post" action="">
					<?php wp_nonce_field( 'qp_save_branding_action', 'qp_branding_nonce' ); ?>

					<div class="card" style="max-width: 800px; margin-top: 20px;">
						<h2><?php esc_html_e( 'Style Customizer', 'quote-pilot' ); ?></h2>
						<table class="form-table" role="presentation">
							<!-- 1. Colors -->
							<tr>
								<th scope="row"><label for="primary_color"><?php esc_html_e( 'Primary Brand Color', 'quote-pilot' ); ?></label></th>
								<td>
									<input type="text" id="primary_color" name="primary_color" class="qp-color-field" value="<?php echo esc_attr( $primary ); ?>" required />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="secondary_color"><?php esc_html_e( 'Secondary Accent Color', 'quote-pilot' ); ?></label></th>
								<td>
									<input type="text" id="secondary_color" name="secondary_color" class="qp-color-field" value="<?php echo esc_attr( $secondary ); ?>" required />
								</td>
							</tr>

							<!-- 2. Typography -->
							<tr>
								<th scope="row"><label for="font_family"><?php esc_html_e( 'Typography Font Stack', 'quote-pilot' ); ?></label></th>
								<td>
									<select id="font_family" name="font_family" style="width: 250px;">
										<option value="system-ui" <?php selected( 'system-ui', $font ); ?>><?php esc_html_e( 'Default System Fonts (Fastest)', 'quote-pilot' ); ?></option>
										<option value="Inter" <?php selected( 'Inter', $font ); ?>>Inter</option>
										<option value="Outfit" <?php selected( 'Outfit', $font ); ?>>Outfit</option>
										<option value="Roboto" <?php selected( 'Roboto', $font ); ?>>Roboto</option>
									</select>
								</td>
							</tr>

							<!-- 3. Logo Upload -->
							<tr>
								<th scope="row"><?php esc_html_e( 'Business Brand Logo', 'quote-pilot' ); ?></th>
								<td>
									<input type="text" id="logo_url" name="logo_url" class="regular-text" value="<?php echo esc_url( $logo ); ?>" />
									<button type="button" class="button" id="qp-logo-upload-btn"><?php esc_html_e( 'Choose/Upload Logo', 'quote-pilot' ); ?></button>
									<p class="description"><?php esc_html_e( 'Select your brand logo using the WordPress media library.', 'quote-pilot' ); ?></p>
									<div id="qp-logo-preview-container" style="margin-top: 15px;">
										<?php if ( ! empty( $logo ) ) : ?>
											<img src="<?php echo esc_url( $logo ); ?>" style="max-height: 80px; width: auto; border: 1px solid #ccd0d4; padding: 5px; background: #fff;" id="qp-logo-preview" alt="Preview logo" />
										<?php endif; ?>
									</div>
								</td>
							</tr>
						</table>
					</div>

					<p class="submit">
						<input type="submit" name="qp_save_branding" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Branding overrides', 'quote-pilot' ); ?>" />
					</p>
				</form>
			</div>

			<script type="text/javascript">
				jQuery(document).ready(function($){
					// Enable color pickers
					$('.qp-color-field').wpColorPicker();

					// Enable Media Upload button
					$('#qp-logo-upload-btn').click(function(e) {
						e.preventDefault();
						var image_frame;
						if (image_frame) {
							image_frame.open();
							return;
						}
						image_frame = wp.media({
							title: '<?php esc_html_e( 'Select Brand Logo Logo', 'quote-pilot' ); ?>',
							multiple: false,
							library: { type : 'image' }
						});
						image_frame.on('close', function() {
							var selection = image_frame.state().get('selection');
							selection.each(function(attachment) {
								var url = attachment.attributes.url;
								$('#logo_url').val(url);
								
								// Render Preview
								var img = $('#qp-logo-preview');
								if (img.length) {
									img.attr('src', url);
								} else {
									$('#qp-logo-preview-container').html('<img src="' + url + '" style="max-height: 80px; width: auto; border: 1px solid #ccd0d4; padding: 5px; background: #fff;" id="qp-logo-preview" />');
								}
							});
						});
						image_frame.open();
					});
				});
			</script>
			<?php
		}
	}

endif;
