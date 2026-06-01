<?php
/**
 * Date Rules Admin Interface.
 *
 * Manages closed days and high-rate surcharge calendar days inside QuotePilot.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Date_Rules_Admin' ) ) :

	/**
	 * Class QP_Date_Rules_Admin
	 */
	class QP_Date_Rules_Admin {

		/**
		 * Register hooks.
		 *
		 * @return void
		 */
		public static function register() {
			add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		}

		/**
		 * Handles CRUD operations via POST/GET queries.
		 *
		 * @return void
		 */
		public static function handle_actions() {
			if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// 1. HANDLE ADD RULE
			if ( isset( $_POST['qp_add_date_rule'] ) ) {
				if ( ! isset( $_POST['qp_date_rules_nonce'] ) || ! wp_verify_nonce( $_POST['qp_date_rules_nonce'], 'qp_date_rules_action' ) ) {
					wp_die( esc_html__( 'Security check failed.', 'quote-pilot' ) );
				}

				$date  = isset( $_POST['rule_date'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_date'] ) ) : '';
				$type  = isset( $_POST['rule_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_type'] ) ) : 'closed';
				$s_type = isset( $_POST['surcharge_type'] ) ? sanitize_text_field( wp_unslash( $_POST['surcharge_type'] ) ) : 'flat';
				$s_val  = isset( $_POST['surcharge_value'] ) ? (float) $_POST['surcharge_value'] : 0.0;
				$note  = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';

				if ( empty( $date ) ) {
					wp_safe_redirect( add_query_arg( array( 'page' => 'qp-date-rules', 'qp_error' => 'date_required' ), admin_url( 'admin.php' ) ) );
					exit;
				}

				if ( class_exists( 'QP_Database' ) ) {
					QP_Database::insert_date_rule(
						array(
							'rule_date'       => $date,
							'rule_type'       => $type,
							'surcharge_type'  => $s_type,
							'surcharge_value' => $s_val,
							'note'            => $note,
						)
					);
				}

				wp_safe_redirect( add_query_arg( array( 'page' => 'qp-date-rules', 'qp_updated' => 'added' ), admin_url( 'admin.php' ) ) );
				exit;
			}

			// 2. HANDLE DELETE RULE
			if ( isset( $_GET['qp_action'] ) && 'delete_date_rule' === $_GET['qp_action'] && ! empty( $_GET['id'] ) ) {
				if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'qp_delete_date_rule_' . $_GET['id'] ) ) {
					wp_die( esc_html__( 'Security check failed.', 'quote-pilot' ) );
				}

				$id = (int) $_GET['id'];
				if ( class_exists( 'QP_Database' ) ) {
					QP_Database::delete_date_rule( $id );
				}

				wp_safe_redirect( add_query_arg( array( 'page' => 'qp-date-rules', 'qp_updated' => 'deleted' ), admin_url( 'admin.php' ) ) );
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

			$rules           = QP_Database::get_all_date_rules();
			$currency_symbol = '$';
			if ( class_exists( 'QP_Helpers' ) ) {
				$currency_symbol = QP_Helpers::get_setting( 'currency_symbol', '$' );
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Date Rules Calendar & Closed Days', 'quote-pilot' ); ?></h1>
				<p class="description"><?php esc_html_e( 'Block off dates from booking or configure high-rate seasonal surcharges for specific calendar days.', 'quote-pilot' ); ?></p>

				<?php if ( isset( $_GET['qp_updated'] ) ) : ?>
					<div class="notice notice-success is-dismissible">
						<p>
							<?php
							if ( 'added' === $_GET['qp_updated'] ) {
								esc_html_e( 'Date rule added successfully.', 'quote-pilot' );
							} else {
								esc_html_e( 'Date rule deleted.', 'quote-pilot' );
							}
							?>
						</p>
					</div>
				<?php elseif ( isset( $_GET['qp_error'] ) ) : ?>
					<div class="notice notice-error is-dismissible">
						<p><?php esc_html_e( 'Please select a valid date.', 'quote-pilot' ); ?></p>
					</div>
				<?php endif; ?>

				<div class="metabox-holder" style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
					<!-- Left: Add New Rule Card -->
					<div style="flex: 1; min-width: 300px;">
						<div class="postbox">
							<h2 class="hndle"><span><?php esc_html_e( 'Add Custom Calendar Rule', 'quote-pilot' ); ?></span></h2>
							<div class="inside">
								<form method="post" action="">
									<?php wp_nonce_field( 'qp_date_rules_action', 'qp_date_rules_nonce' ); ?>

									<div style="margin-bottom: 15px;">
										<label for="rule_date" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Select Calendar Date', 'quote-pilot' ); ?></label>
										<input type="date" id="rule_date" name="rule_date" style="width: 100%;" required />
									</div>

									<div style="margin-bottom: 15px;">
										<label for="rule_type" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Rule Type', 'quote-pilot' ); ?></label>
										<select id="rule_type" name="rule_type" style="width: 100%;" onchange="qpToggleSurchargeFields(this.value)">
											<option value="closed"><?php esc_html_e( 'Closed (Block booking entirely)', 'quote-pilot' ); ?></option>
											<option value="high_rate"><?php esc_html_e( 'High Rate (Surcharge adjustment)', 'quote-pilot' ); ?></option>
										</select>
									</div>

									<div id="qp-surcharge-options" style="display: none; border-left: 3px solid var(--qp-primary); padding-left: 10px; margin-bottom: 15px;">
										<div style="margin-bottom: 10px;">
											<label for="surcharge_type" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Surcharge Mode', 'quote-pilot' ); ?></label>
											<select id="surcharge_type" name="surcharge_type" style="width: 100%;">
												<option value="flat"><?php esc_html_e( 'Flat Value', 'quote-pilot' ); ?></option>
												<option value="percent"><?php esc_html_e( 'Percentage of Quote Base', 'quote-pilot' ); ?></option>
											</select>
										</div>
										<div>
											<label for="surcharge_value" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Surcharge Value', 'quote-pilot' ); ?></label>
											<input type="number" step="0.01" min="0" id="surcharge_value" name="surcharge_value" style="width: 100%;" value="0.00" />
										</div>
									</div>

									<div style="margin-bottom: 15px;">
										<label for="note" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Note / Reason', 'quote-pilot' ); ?></label>
										<input type="text" id="note" name="note" class="regular-text" style="width: 100%;" placeholder="e.g. Christmas Day / Public Holiday Surcharge" />
									</div>

									<input type="submit" name="qp_add_date_rule" class="button button-primary button-large" style="width: 100%;" value="<?php esc_attr_e( 'Add Calendar Rule', 'quote-pilot' ); ?>" />
								</form>
							</div>
						</div>
					</div>

					<!-- Right: List Existing Rules Card -->
					<div style="flex: 2; min-width: 400px;">
						<div class="postbox">
							<h2 class="hndle"><span><?php esc_html_e( 'Configured Calendar Rules', 'quote-pilot' ); ?></span></h2>
							<div class="inside" style="padding: 0;">
								<table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
									<thead>
										<tr>
											<th style="padding-left: 15px; font-weight: 600;"><?php esc_html_e( 'Date', 'quote-pilot' ); ?></th>
											<th style="width: 120px; font-weight: 600;"><?php esc_html_e( 'Rule Type', 'quote-pilot' ); ?></th>
											<th style="width: 140px; font-weight: 600;"><?php esc_html_e( 'Adjustment', 'quote-pilot' ); ?></th>
											<th style="font-weight: 600;"><?php esc_html_e( 'Note / Reason', 'quote-pilot' ); ?></th>
											<th style="width: 80px; text-align: center; font-weight: 600;"><?php esc_html_e( 'Actions', 'quote-pilot' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php if ( ! empty( $rules ) ) : ?>
											<?php foreach ( $rules as $rule ) :
												$delete_url = wp_nonce_url(
													add_query_arg( array( 'page' => 'qp-date-rules', 'qp_action' => 'delete_date_rule', 'id' => $rule->id ), admin_url( 'admin.php' ) ),
													'qp_delete_date_rule_' . $rule->id
												);
												?>
												<tr>
													<td style="padding-left: 15px;"><strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $rule->rule_date ) ) ); ?></strong></td>
													<td>
														<?php if ( 'closed' === $rule->rule_type ) : ?>
															<span class="status-badge" style="background: #fde8e8; color: #9b1c1c; font-size: 10px;"><?php esc_html_e( 'CLOSED', 'quote-pilot' ); ?></span>
														<?php else : ?>
															<span class="status-badge" style="background: #dbeafe; color: #1e40af; font-size: 10px;"><?php esc_html_e( 'HIGH RATE', 'quote-pilot' ); ?></span>
														<?php endif; ?>
													</td>
													<td>
														<?php
														if ( 'high_rate' === $rule->rule_type ) {
															if ( 'percent' === $rule->surcharge_type ) {
																echo esc_html( '+' . (float) $rule->surcharge_value . '%' );
															} else {
																echo esc_html( '+' . $currency_symbol . number_format( $rule->surcharge_value, 2 ) );
															}
														} else {
															echo '—';
														}
														?>
													</td>
													<td><?php echo esc_html( $rule->note ); ?></td>
													<td style="text-align: center;">
														<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-link" style="color: #ef4444;" onclick="return confirm('<?php esc_html_e( 'Are you sure you want to delete this rule?', 'quote-pilot' ); ?>')"><?php esc_html_e( 'Delete', 'quote-pilot' ); ?></a>
													</td>
												</tr>
											<?php endforeach; ?>
										<?php else : ?>
											<tr>
												<td colspan="5" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No custom calendar rules configured.', 'quote-pilot' ); ?></td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>

			<script type="text/javascript">
				function qpToggleSurchargeFields(val) {
					var el = document.getElementById('qp-surcharge-options');
					if (val === 'high_rate') {
						el.style.display = 'block';
					} else {
						el.style.display = 'none';
					}
				}
			</script>
			<?php
		}
	}

endif;
