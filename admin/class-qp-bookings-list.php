<?php
/**
 * Bookings List Screen.
 *
 * Implements the admin dashboard Bookings screen displaying custom quote-form submissions
 * using WP_List_Table with full filtering, sorting, pagination, and a single-booking
 * overview panel allowing status updates and assigned cleaners.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'QP_Bookings_List' ) ) :

	/**
	 * Class QP_Bookings_List
	 */
	class QP_Bookings_List {

		/**
		 * Register save actions.
		 *
		 * @return void
		 */
		public static function register() {
			add_action( 'admin_init', array( __CLASS__, 'handle_booking_update' ) );
		}

		/**
		 * Handle updates to a booking status or cleaner from single-booking screen.
		 *
		 * @return void
		 */
		public static function handle_booking_update() {
			if ( ! isset( $_POST['qp_update_single_booking'] ) ) {
				return;
			}

			// Security validation
			if ( ! isset( $_POST['qp_booking_nonce'] ) || ! wp_verify_nonce( $_POST['qp_booking_nonce'], 'qp_update_booking_action' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'quote-pilot' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage bookings.', 'quote-pilot' ) );
			}

			$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
			if ( empty( $booking_id ) ) {
				return;
			}

			$status  = isset( $_POST['booking_status'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_status'] ) ) : 'pending';
			$cleaner = isset( $_POST['assigned_cleaner'] ) ? sanitize_text_field( wp_unslash( $_POST['assigned_cleaner'] ) ) : '';
			$notes   = isset( $_POST['admin_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '';

			if ( class_exists( 'QP_Database' ) ) {
				QP_Database::update_booking(
					$booking_id,
					array(
						'booking_status'   => $status,
						'assigned_cleaner' => $cleaner,
						'admin_notes'      => $notes,
					)
				);
			}

			wp_safe_redirect( add_query_arg( array( 'page' => 'quote-pilot', 'action' => 'view', 'id' => $booking_id, 'qp_updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		/**
		 * Render page body depending on action.
		 *
		 * @return void
		 */
		public static function render_page() {
			$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

			if ( 'view' === $action && ! empty( $_GET['id'] ) ) {
				self::render_single_booking( (int) $_GET['id'] );
			} else {
				self::render_list_table();
			}
		}

		/**
		 * Render the WP_List_Table of bookings.
		 *
		 * @return void
		 */
		private static function render_list_table() {
			$table = new QP_Bookings_List_Table();
			$table->prepare_items();
			?>
			<div class="wrap">
				<h1 class="wp-heading-inline"><?php esc_html_e( 'QuotePilot Bookings & Quotes', 'quote-pilot' ); ?></h1>
				<hr class="wp-header-end">

				<form id="qp-bookings-filter-form" method="get" action="">
					<input type="hidden" name="page" value="quote-pilot" />
					<?php
					$table->views();
					$table->search_box( esc_html__( 'Search Bookings', 'quote-pilot' ), 'qp-search-input' );
					$table->display();
					?>
				</form>
			</div>
			<style type="text/css">
				.column-customer { font-weight: 600; }
				.column-total { font-weight: 700; }
				.status-badge {
					font-size: 11px;
					font-weight: 600;
					padding: 3px 8px;
					border-radius: 4px;
					display: inline-block;
					text-transform: uppercase;
				}
				.badge-pending { background: #fef3c7; color: #92400e; }
				.badge-confirmed { background: #dbeafe; color: #1e40af; }
				.badge-completed { background: #d1fae5; color: #065f46; }
				.badge-cancelled { background: #fde8e8; color: #9b1c1c; }
			</style>
			<?php
		}

		/**
		 * Render details for a single booking row.
		 *
		 * @param int $id Booking ID.
		 * @return void
		 */
		private static function render_single_booking( $id ) {
			if ( ! class_exists( 'QP_Database' ) ) {
				return;
			}

			$booking = QP_Database::get_booking( $id );
			if ( ! $booking ) {
				wp_die( esc_html__( 'Booking not found.', 'quote-pilot' ) );
			}

			$items           = QP_Database::get_booking_items( $id );
			$currency_symbol = '$';
			if ( class_exists( 'QP_Helpers' ) ) {
				$currency_symbol = QP_Helpers::get_setting( 'currency_symbol', '$' );
			}

			$service_title = esc_html__( 'Cleaning Service', 'quote-pilot' );
			$service_post  = get_post( $booking->service_id );
			if ( $service_post ) {
				$service_title = get_the_title( $service_post );
			}
			?>
			<div class="wrap">
				<h1><?php printf( esc_html__( 'Booking Details — #%s', 'quote-pilot' ), esc_html( $booking->id ) ); ?></h1>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=quote-pilot' ) ); ?>" class="button">&larr; <?php esc_html_e( 'Back to Bookings', 'quote-pilot' ); ?></a></p>

				<?php if ( isset( $_GET['qp_updated'] ) ) : ?>
					<div class="notice notice-success is-dismissible">
						<p><?php esc_html_e( 'Booking updated successfully.', 'quote-pilot' ); ?></p>
					</div>
				<?php endif; ?>

				<div class="metabox-holder" style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
					<!-- Left: Booking Breakdown Details -->
					<div style="flex: 2; min-width: 300px;">
						<div class="postbox">
							<h2 class="hndle"><span><?php esc_html_e( 'Customer & Schedule Information', 'quote-pilot' ); ?></span></h2>
							<div class="inside">
								<table class="form-table" role="presentation" style="margin: 0;">
									<tr>
										<th style="padding: 10px 0; width: 150px;"><strong><?php esc_html_e( 'Customer Name', 'quote-pilot' ); ?></strong></th>
										<td style="padding: 10px 0;"><?php echo esc_html( $booking->customer_name ); ?></td>
									</tr>
									<tr>
										<th style="padding: 10px 0;"><strong><?php esc_html_e( 'Email Address', 'quote-pilot' ); ?></strong></th>
										<td style="padding: 10px 0;"><a href="mailto:<?php echo esc_attr( $booking->customer_email ); ?>"><?php echo esc_html( $booking->customer_email ); ?></a></td>
									</tr>
									<tr>
										<th style="padding: 10px 0;"><strong>Phone Number</strong></th>
										<td style="padding: 10px 0;"><a href="tel:<?php echo esc_attr( $booking->customer_phone ); ?>"><?php echo esc_html( $booking->customer_phone ); ?></a></td>
									</tr>
									<tr>
										<th style="padding: 10px 0;"><strong>Service Selected</strong></th>
										<td style="padding: 10px 0;"><?php echo esc_html( $service_title ); ?></td>
									</tr>
									<tr>
										<th style="padding: 10px 0;"><strong>Address</strong></th>
										<td style="padding: 10px 0;"><?php echo esc_html( $booking->customer_address ); ?></td>
									</tr>
									<tr>
										<th style="padding: 10px 0;"><strong>Date & Time</strong></th>
										<td style="padding: 10px 0;">
											<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking->preferred_date ) ) ); ?>
											<?php if ( ! empty( $booking->preferred_time ) ) : ?>
												@ <?php echo esc_html( $booking->preferred_time ); ?>
											<?php endif; ?>
										</td>
									</tr>
									<tr>
										<th style="padding: 10px 0;"><strong>Payment Status</strong></th>
										<td style="padding: 10px 0;">
											<span class="status-badge" style="background: #e2e8f0; color: #334155; font-size: 10px;"><?php echo esc_html( strtoupper( $booking->payment_status ) ); ?></span>
											<?php if ( $booking->amount_paid > 0 ) : ?>
												(Paid: <?php echo esc_html( $currency_symbol . number_format( $booking->amount_paid, 2 ) ); ?>)
											<?php endif; ?>
										</td>
									</tr>
								</table>
							</div>
						</div>

						<div class="postbox" style="margin-top: 20px;">
							<h2 class="hndle"><span><?php esc_html_e( 'Itemised Price Breakdown', 'quote-pilot' ); ?></span></h2>
							<div class="inside" style="padding: 0;">
								<table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
									<thead>
										<tr>
											<th style="padding-left: 15px; font-weight: 600;"><?php esc_html_e( 'Item Description', 'quote-pilot' ); ?></th>
											<th style="width: 100px; font-weight: 600;"><?php esc_html_e( 'Type', 'quote-pilot' ); ?></th>
											<th style="width: 80px; text-align: center; font-weight: 600;"><?php esc_html_e( 'Qty', 'quote-pilot' ); ?></th>
											<th style="width: 100px; text-align: right; font-weight: 600;"><?php esc_html_e( 'Unit Price', 'quote-pilot' ); ?></th>
											<th style="width: 120px; text-align: right; padding-right: 15px; font-weight: 600;"><?php esc_html_e( 'Line Total', 'quote-pilot' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php if ( ! empty( $items ) ) : ?>
											<?php foreach ( $items as $item ) : ?>
												<tr>
													<td style="padding-left: 15px;"><strong><?php echo esc_html( $item->item_label ); ?></strong></td>
													<td><span style="font-size: 11px; text-transform: uppercase; color: #64748b;"><?php echo esc_html( $item->item_type ); ?></span></td>
													<td style="text-align: center;"><?php echo esc_html( (float) $item->quantity ); ?></td>
													<td style="text-align: right;"><?php echo esc_html( $currency_symbol . number_format( $item->unit_amount, 2 ) ); ?></td>
													<td style="text-align: right; padding-right: 15px; font-weight: 600;">
														<?php
														if ( 'discount' === $item->item_type ) {
															echo esc_html( '-' . $currency_symbol . number_format( abs( $item->line_total ), 2 ) );
														} else {
															echo esc_html( $currency_symbol . number_format( $item->line_total, 2 ) );
														}
														?>
													</td>
												</tr>
											<?php endforeach; ?>
										<?php else : ?>
											<tr>
												<td colspan="5" style="text-align: center; padding: 20px;"><?php esc_html_e( 'No items recorded.', 'quote-pilot' ); ?></td>
											</tr>
										<?php endif; ?>
									</tbody>
									<tfoot>
										<tr style="background: #f8fafc; font-size: 14px;">
											<td colspan="4" style="text-align: right; padding: 15px 10px; font-weight: bold; border-top: 2px solid #e2e8f0;"><?php esc_html_e( 'Authoritative Total Price:', 'quote-pilot' ); ?></td>
											<td style="text-align: right; padding: 15px 15px; font-weight: 800; color: var(--qp-primary); border-top: 2px solid #e2e8f0; font-size: 16px;">
												<?php echo esc_html( $currency_symbol . number_format( $booking->total_price, 2 ) ); ?>
											</td>
										</tr>
									</tfoot>
								</table>
							</div>
						</div>
					</div>

					<!-- Right: Settings Overrides panel -->
					<div style="flex: 1; min-width: 250px;">
						<div class="postbox">
							<h2 class="hndle"><span><?php esc_html_e( 'Status & Assignment', 'quote-pilot' ); ?></span></h2>
							<div class="inside">
								<form method="post" action="">
									<?php wp_nonce_field( 'qp_update_booking_action', 'qp_booking_nonce' ); ?>
									<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->id ); ?>" />

									<div style="margin-bottom: 15px;">
										<label for="booking_status" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Booking Status', 'quote-pilot' ); ?></label>
										<select id="booking_status" name="booking_status" style="width: 100%;">
											<option value="pending" <?php selected( 'pending', $booking->booking_status ); ?>><?php esc_html_e( 'Pending Review', 'quote-pilot' ); ?></option>
											<option value="confirmed" <?php selected( 'confirmed', $booking->booking_status ); ?>><?php esc_html_e( 'Confirmed / Scheduled', 'quote-pilot' ); ?></option>
											<option value="completed" <?php selected( 'completed', $booking->booking_status ); ?>><?php esc_html_e( 'Completed', 'quote-pilot' ); ?></option>
											<option value="cancelled" <?php selected( 'cancelled', $booking->booking_status ); ?>><?php esc_html_e( 'Cancelled', 'quote-pilot' ); ?></option>
										</select>
									</div>

									<div style="margin-bottom: 15px;">
										<label for="assigned_cleaner" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Assigned Cleaner', 'quote-pilot' ); ?></label>
										<input type="text" id="assigned_cleaner" name="assigned_cleaner" class="regular-text" style="width: 100%;" value="<?php echo esc_attr( $booking->assigned_cleaner ); ?>" placeholder="e.g. John Doe / Team Alpha" />
									</div>

									<div style="margin-bottom: 15px;">
										<label for="admin_notes" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e( 'Internal Admin Notes', 'quote-pilot' ); ?></label>
										<textarea id="admin_notes" name="admin_notes" rows="6" style="width: 100%; font-size: 12px;"><?php echo esc_textarea( $booking->admin_notes ); ?></textarea>
									</div>

									<input type="submit" name="qp_update_single_booking" class="button button-primary button-large" style="width: 100%;" value="<?php esc_attr_e( 'Update Booking Settings', 'quote-pilot' ); ?>" />
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Child Table Class for WP_List_Table integration.
	 */
	class QP_Bookings_List_Table extends WP_List_Table {

		/**
		 * Constructor.
		 */
		public function __construct() {
			parent::__construct(
				array(
					'singular' => 'booking',
					'plural'   => 'bookings',
					'ajax'     => false,
				)
			);
		}

		/**
		 * Define List Table Columns.
		 *
		 * @return array columns format.
		 */
		public function get_columns() {
			return array(
				'id'             => esc_html__( 'ID', 'quote-pilot' ),
				'customer'       => esc_html__( 'Customer', 'quote-pilot' ),
				'service'        => esc_html__( 'Service', 'quote-pilot' ),
				'date'           => esc_html__( 'Preferred Date', 'quote-pilot' ),
				'total'          => esc_html__( 'Total Price', 'quote-pilot' ),
				'payment_status' => esc_html__( 'Payment', 'quote-pilot' ),
				'status'         => esc_html__( 'Booking Status', 'quote-pilot' ),
			);
		}

		/**
		 * Define Sortable Columns.
		 *
		 * @return array sortable columns list.
		 */
		public function get_sortable_columns() {
			return array(
				'id'   => array( 'id', true ),
				'date' => array( 'preferred_date', false ),
			);
		}

		/**
		 * Renders the filter view sub-menu links.
		 *
		 * @return array views array.
		 */
		protected function get_views() {
			global $wpdb;
			$table = QP_Database::bookings();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$totals = $wpdb->get_results( "SELECT booking_status, COUNT(*) as count FROM {$table} GROUP BY booking_status" );
			$counts = array(
				'all'       => 0,
				'pending'   => 0,
				'confirmed' => 0,
				'completed' => 0,
				'cancelled' => 0,
			);

			foreach ( $totals as $t ) {
				$status = $t->booking_status;
				if ( array_key_exists( $status, $counts ) ) {
					$counts[ $status ] = (int) $t->count;
				}
				$counts['all'] += (int) $t->count;
			}

			$current = isset( $_GET['booking_status'] ) ? sanitize_text_field( wp_unslash( $_GET['booking_status'] ) ) : 'all';

			$views = array();

			foreach ( $counts as $status => $count ) {
				$label = ucfirst( $status );
				if ( 'all' === $status ) {
					$label = esc_html__( 'All Bookings', 'quote-pilot' );
				}
				
				$class = ( $current === $status ) ? 'class="current"' : '';
				$url   = add_query_arg( 'booking_status', $status, admin_url( 'admin.php?page=quote-pilot' ) );
				if ( 'all' === $status ) {
					$url = remove_query_arg( 'booking_status', admin_url( 'admin.php?page=quote-pilot' ) );
				}

				$views[ $status ] = sprintf(
					'<a href="%1$s" %2$s>%3$s <span class="count">(%4$d)</span></a>',
					esc_url( $url ),
					$class,
					esc_html( $label ),
					$count
				);
			}

			return $views;
		}

		/**
		 * Default renderer for columns.
		 *
		 * @param object $item        Row data object.
		 * @param string $column_name Column key string.
		 * @return string rendered output.
		 */
		public function column_default( $item, $column_name ) {
			$currency_symbol = '$';
			if ( class_exists( 'QP_Helpers' ) ) {
				$currency_symbol = QP_Helpers::get_setting( 'currency_symbol', '$' );
			}

			switch ( $column_name ) {
				case 'customer':
					return esc_html( $item->customer_name ) . '<br/><small class="description">' . esc_html( $item->customer_email ) . '</small>';
				case 'service':
					$post = get_post( $item->service_id );
					return $post ? esc_html( get_the_title( $post ) ) : esc_html__( 'Cleaning Service', 'quote-pilot' );
				case 'date':
					return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->preferred_date ) ) ) . ( ! empty( $item->preferred_time ) ? ' @ ' . esc_html( $item->preferred_time ) : '' );
				case 'total':
					return esc_html( $currency_symbol . number_format( $item->total_price, 2 ) );
				case 'payment_status':
					return '<span class="status-badge" style="background: #e2e8f0; color: #334155; font-size: 10px;">' . esc_html( strtoupper( $item->payment_status ) ) . '</span>';
				case 'status':
					$status_class = 'badge-pending';
					if ( 'completed' === $item->booking_status ) {
						$status_class = 'badge-completed';
					} elseif ( 'cancelled' === $item->booking_status ) {
						$status_class = 'badge-cancelled';
					} elseif ( 'confirmed' === $item->booking_status ) {
						$status_class = 'badge-confirmed';
					}
					return sprintf( '<span class="status-badge %s">%s</span>', esc_attr( $status_class ), esc_html( ucfirst( $item->booking_status ) ) );
				default:
					return esc_html( print_r( $item, true ) );
			}
		}

		/**
		 * Render ID column w/ single Actions links.
		 *
		 * @param object $item Row data object.
		 * @return string rendered ID block.
		 */
		public function column_id( $item ) {
			$view_url   = admin_url( 'admin.php?page=quote-pilot&action=view&id=' . $item->id );
			$delete_url = admin_url( 'admin.php?page=quote-pilot&action=delete&id=' . $item->id ); // Stub delete action

			$actions = array(
				'view' => sprintf( '<a href="%s">%s</a>', esc_url( $view_url ), esc_html__( 'View details', 'quote-pilot' ) ),
			);

			return sprintf(
				'<strong>#%1$s</strong> %2$s',
				esc_html( $item->id ),
				$this->row_actions( $actions )
			);
		}

		/**
		 * Query Bookings Database with Sort and Filters.
		 *
		 * @return void
		 */
		public function prepare_items() {
			global $wpdb;

			if ( ! class_exists( 'QP_Database' ) ) {
				return;
			}

			$table        = QP_Database::bookings();
			$per_page     = 10;
			$current_page = $this->get_pagenum();

			// 1. Filter by booking status view
			$status_filter = isset( $_GET['booking_status'] ) ? sanitize_text_field( wp_unslash( $_GET['booking_status'] ) ) : 'all';
			$where_clause  = '1=1';

			if ( 'all' !== $status_filter && in_array( $status_filter, array( 'pending', 'confirmed', 'completed', 'cancelled' ), true ) ) {
				$where_clause .= $wpdb->prepare( ' AND booking_status = %s', $status_filter );
			}

			// 2. Search filtering
			$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
			if ( ! empty( $search ) ) {
				$where_clause .= $wpdb->prepare(
					' AND (customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s OR id = %d)',
					'%' . $wpdb->esc_like( $search ) . '%',
					'%' . $wpdb->esc_like( $search ) . '%',
					'%' . $wpdb->esc_like( $search ) . '%',
					(int) $search
				);
			}

			// 3. Sorting logic
			$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'id';
			$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

			$valid_order_columns = array(
				'id'             => 'id',
				'preferred_date' => 'preferred_date',
			);

			$sort_col = isset( $valid_order_columns[ $orderby ] ) ? $valid_order_columns[ $orderby ] : 'id';
			$sort_ord = ( 'ASC' === strtoupper( $order ) ) ? 'ASC' : 'DESC';

			// Pagination details
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and order are safe whitelisted, where is prepared.
			$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" );
			$offset      = ( $current_page - 1 ) * $per_page;

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- same safety rules.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$sort_col} {$sort_ord} LIMIT %d OFFSET %d",
					$per_page,
					$offset
				)
			);

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
				)
			);

			$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
			$this->items           = $results;
		}
	}

endif;
