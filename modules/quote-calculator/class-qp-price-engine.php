<?php
/**
 * Authoritative server-side price engine for QuotePilot.
 *
 * This is the ONLY component that decides the real price. It reads the
 * canonical field contract (QP_Fields), per-service pricing configuration
 * (QP_Services_Meta::get_pricing), date rules and coupons (QP_Database),
 * and global settings (QP_Helpers::get_setting).
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Price_Engine' ) ) :

	/**
	 * Class QP_Price_Engine
	 */
	class QP_Price_Engine {

		/**
		 * Calculate the authoritative quote price and build itemised breakdown.
		 *
		 * Pure function: no DB writes, no echo, no globals (except helpers/reads).
		 * Money is rounded to 2 decimals at each subtotal step.
		 *
		 * @param array      $input        Sanitized field inputs from the client.
		 * @param int        $service_id   Selected service post ID.
		 * @param array|null $visible_keys Array of currently visible field keys, or null if all visible.
		 * @return array Price breakdown and totals.
		 */
		public static function calculate( array $input, int $service_id, array $visible_keys = null ) {
			// Retrieve service pricing metadata.
			$pricing = QP_Services_Meta::get_pricing( $service_id );
			$items   = array();

			/*----------------------------------------------------------
			 * 1. Base Price & Per-Unit Multipliers
			 *--------------------------------------------------------*/
			$base_price = round( $pricing['base_price'], 2 );
			$items[]    = array(
				'item_label'  => __( 'Base Price', 'quote-pilot' ),
				'item_type'   => 'base',
				'quantity'    => 1.0,
				'unit_amount' => $base_price,
				'line_total'  => $base_price,
			);
			$base = $base_price;

			// room_size multiplier
			$room_size = self::get_field_val( 'room_size', $input, $visible_keys, 'medium' );
			if ( ! in_array( $room_size, array( 'small', 'medium', 'large' ), true ) ) {
				$room_size = 'medium';
			}
			$room_mult = isset( $pricing['room_size_multipliers'][ $room_size ] ) ? floatval( $pricing['room_size_multipliers'][ $room_size ] ) : 1.0;

			// Square Footage Surcharge
			if ( ! empty( $pricing['enable_sqft'] ) ) {
				$sqft = self::get_field_val( 'sqft', $input, $visible_keys, 0.0 );
				if ( $sqft > 0 ) {
					$unit_amount = round( $pricing['price_per_sqft'], 4 ); // Higher precision for sqft rate
					$line        = round( $sqft * $pricing['price_per_sqft'], 2 );
					$items[]     = array(
						'item_label'  => __( 'Square Footage Surcharge', 'quote-pilot' ),
						'item_type'   => 'multiplier',
						'quantity'    => floatval( $sqft ),
						'unit_amount' => round( $unit_amount, 2 ),
						'line_total'  => $line,
					);
					$base += $line;
				}
			}

			// Bedrooms
			if ( ! empty( $pricing['enable_bedrooms'] ) ) {
				$bedrooms = self::get_field_val( 'bedrooms', $input, $visible_keys, 0 );
				if ( $bedrooms > 0 ) {
					$unit_price = round( $pricing['price_per_bedroom'] * $room_mult, 2 );
					$line       = round( $bedrooms * $unit_price, 2 );
					$label      = sprintf( __( 'Bedrooms (%s)', 'quote-pilot' ), ucfirst( $room_size ) );
					$items[]    = array(
						'item_label'  => $label,
						'item_type'   => 'multiplier',
						'quantity'    => floatval( $bedrooms ),
						'unit_amount' => $unit_price,
						'line_total'  => $line,
					);
					$base += $line;
				}
			}

			// Bathrooms & Extra Bathrooms
			if ( ! empty( $pricing['enable_bathrooms'] ) ) {
				$bathrooms       = self::get_field_val( 'bathrooms', $input, $visible_keys, 0 );
				$extra_bathrooms = self::get_field_val( 'extra_bathrooms', $input, $visible_keys, 0 );

				if ( $bathrooms > 0 ) {
					// First bathroom
					$unit_price = round( $pricing['price_per_bathroom'] * $room_mult, 2 );
					$line       = round( 1 * $unit_price, 2 );
					$label      = sprintf( __( 'First Bathroom (%s)', 'quote-pilot' ), ucfirst( $room_size ) );
					$items[]    = array(
						'item_label'  => $label,
						'item_type'   => 'multiplier',
						'quantity'    => 1.0,
						'unit_amount' => $unit_price,
						'line_total'  => $line,
					);
					$base += $line;

					// Additional bathrooms
					$rem_bathrooms = max( 0, $bathrooms - 1 ) + $extra_bathrooms;
					if ( $rem_bathrooms > 0 ) {
						$extra_unit_price = round( $pricing['price_per_extra_bathroom'] * $room_mult, 2 );
						$extra_line       = round( $rem_bathrooms * $extra_unit_price, 2 );
						$extra_label      = sprintf( __( 'Additional Bathrooms (%s)', 'quote-pilot' ), ucfirst( $room_size ) );
						$items[]          = array(
							'item_label'  => $extra_label,
							'item_type'   => 'multiplier',
							'quantity'    => floatval( $rem_bathrooms ),
							'unit_amount' => $extra_unit_price,
							'line_total'  => $extra_line,
						);
						$base += $extra_line;
					}
				} elseif ( $extra_bathrooms > 0 ) {
					// Fallback when only extra_bathrooms input is present
					$extra_unit_price = round( $pricing['price_per_extra_bathroom'] * $room_mult, 2 );
					$extra_line       = round( $extra_bathrooms * $extra_unit_price, 2 );
					$extra_label      = sprintf( __( 'Additional Bathrooms (%s)', 'quote-pilot' ), ucfirst( $room_size ) );
					$items[]          = array(
						'item_label'  => $extra_label,
						'item_type'   => 'multiplier',
						'quantity'    => floatval( $extra_bathrooms ),
						'unit_amount' => $extra_unit_price,
						'line_total'  => $extra_line,
					);
					$base += $extra_line;
				}
			}

			// Living Rooms
			if ( ! empty( $pricing['enable_living_room'] ) ) {
				$living_rooms  = self::get_field_val( 'living_rooms', $input, $visible_keys, 0 );
				$lr_mult       = 1.0;
				$lr_size_label = '';

				if ( ! empty( $pricing['living_room_size_multipliers']['enabled'] ) ) {
					$lr_size = self::get_field_val( 'living_room_size', $input, $visible_keys, 'medium' );
					if ( ! in_array( $lr_size, array( 'small', 'medium', 'large' ), true ) ) {
						$lr_size = 'medium';
					}
					$lr_mult       = isset( $pricing['living_room_size_multipliers'][ $lr_size ] ) ? floatval( $pricing['living_room_size_multipliers'][ $lr_size ] ) : 1.0;
					$lr_size_label = ' (' . ucfirst( $lr_size ) . ')';
				}

				if ( $living_rooms > 0 ) {
					$unit_price = round( $pricing['price_per_living_room'] * $lr_mult, 2 );
					$line       = round( $living_rooms * $unit_price, 2 );
					$items[]    = array(
						'item_label'  => __( 'Living Rooms', 'quote-pilot' ) . $lr_size_label,
						'item_type'   => 'multiplier',
						'quantity'    => floatval( $living_rooms ),
						'unit_amount' => $unit_price,
						'line_total'  => $line,
					);
					$base += $line;
				}
			}

			// Building Stories
			if ( ! empty( $pricing['enable_stories'] ) ) {
				$stories     = self::get_field_val( 'stories', $input, $visible_keys, 1 );
				$add_stories = max( 0, $stories - 1 );
				if ( $add_stories > 0 ) {
					$unit_price = round( $pricing['price_per_story'], 2 );
					$line       = round( $add_stories * $unit_price, 2 );
					$items[]    = array(
						'item_label'  => __( 'Building Stories Surcharge', 'quote-pilot' ),
						'item_type'   => 'multiplier',
						'quantity'    => floatval( $add_stories ),
						'unit_amount' => $unit_price,
						'line_total'  => $line,
					);
					$base += $line;
				}
			}

			// Ovens
			if ( ! empty( $pricing['enable_oven'] ) ) {
				$ovens     = self::get_field_val( 'ovens', $input, $visible_keys, 0 );
				$oven_size = self::get_field_val( 'oven_size', $input, $visible_keys, 'standard' );
				$oven_mult = 1.0;

				if ( 'large' === $oven_size ) {
					$oven_mult = isset( $pricing['oven_size_large_multiplier'] ) ? floatval( $pricing['oven_size_large_multiplier'] ) : 1.5;
				}

				if ( $ovens > 0 ) {
					$unit_price = round( $pricing['price_per_oven'] * $oven_mult, 2 );
					$line       = round( $ovens * $unit_price, 2 );
					$label      = sprintf( __( 'Oven Cleaning (%s)', 'quote-pilot' ), ucfirst( $oven_size ) );
					$items[]    = array(
						'item_label'  => $label,
						'item_type'   => 'multiplier',
						'quantity'    => floatval( $ovens ),
						'unit_amount' => $unit_price,
						'line_total'  => $line,
					);
					$base += $line;
				}
			}

			$base = round( $base, 2 );

			/*----------------------------------------------------------
			 * 2. Flat Add-ons
			 *--------------------------------------------------------*/
			$addon_flat_total = 0.0;
			if ( ! empty( $pricing['enable_addons'] ) && is_array( $pricing['addons'] ) ) {
				$selected_flat = self::get_field_val( 'addons_flat', $input, $visible_keys, array() );
				if ( ! is_array( $selected_flat ) ) {
					$selected_flat = array();
				}

				foreach ( $pricing['addons'] as $addon ) {
					if ( 'flat' === $addon['type'] && in_array( $addon['slug'], $selected_flat, true ) ) {
						$val              = round( $addon['value'], 2 );
						$items[]          = array(
							'item_label'  => sprintf( __( 'Add-on: %s', 'quote-pilot' ), $addon['label'] ),
							'item_type'   => 'addon_flat',
							'quantity'    => 1.0,
							'unit_amount' => $val,
							'line_total'  => $val,
						);
						$addon_flat_total += $val;
					}
				}
			}
			$subtotal_1 = round( $base + $addon_flat_total, 2 );

			/*----------------------------------------------------------
			 * 3. Percent Add-ons (Calculated on subtotal_1)
			 *--------------------------------------------------------*/
			$addon_percent_total = 0.0;
			if ( ! empty( $pricing['enable_addons'] ) && is_array( $pricing['addons'] ) ) {
				$selected_percent = self::get_field_val( 'addons_percent', $input, $visible_keys, array() );
				if ( ! is_array( $selected_percent ) ) {
					$selected_percent = array();
				}

				foreach ( $pricing['addons'] as $addon ) {
					if ( 'percent' === $addon['type'] && in_array( $addon['slug'], $selected_percent, true ) ) {
						$pct                 = floatval( $addon['value'] );
						$val                 = round( $subtotal_1 * ( $pct / 100 ), 2 );
						$items[]             = array(
							'item_label'  => sprintf( __( 'Add-on: %s (%s%%)', 'quote-pilot' ), $addon['label'], $pct ),
							'item_type'   => 'addon_percent',
							'quantity'    => 1.0,
							'unit_amount' => $val,
							'line_total'  => $val,
						);
						$addon_percent_total += $val;
					}
				}
			}
			$subtotal_2 = round( $subtotal_1 + $addon_percent_total, 2 );

			/*----------------------------------------------------------
			 * 4. Flat Surcharges
			 *--------------------------------------------------------*/
			$emergency_flat = 0.0;
			$is_emergency   = self::get_field_val( 'emergency', $input, $visible_keys, 0 );
			if ( $is_emergency && QP_Helpers::get_setting( 'emergency_surcharge_enabled' ) ) {
				$type = QP_Helpers::get_setting( 'emergency_surcharge_type' );
				if ( 'flat' === $type ) {
					$val            = round( floatval( QP_Helpers::get_setting( 'emergency_surcharge_value' ) ), 2 );
					$items[]        = array(
						'item_label'  => __( 'Emergency Surcharge', 'quote-pilot' ),
						'item_type'   => 'surcharge',
						'quantity'    => 1.0,
						'unit_amount' => $val,
						'line_total'  => $val,
					);
					$emergency_flat = $val;
				}
			}

			// Date rule high rate
			$date_flat      = 0.0;
			$preferred_date = self::get_field_val( 'preferred_date', $input, $visible_keys, '' );
			if ( ! empty( $preferred_date ) ) {
				$date_rule = QP_Database::get_date_rule( $preferred_date );
				if ( $date_rule && 'high_rate' === $date_rule->rule_type ) {
					if ( 'flat' === $date_rule->surcharge_type ) {
						$val       = round( floatval( $date_rule->surcharge_value ), 2 );
						$items[]   = array(
							'item_label'  => sprintf( __( 'Peak Date Surcharge (%s)', 'quote-pilot' ), $preferred_date ),
							'item_type'   => 'surcharge',
							'quantity'    => 1.0,
							'unit_amount' => $val,
							'line_total'  => $val,
						);
						$date_flat = $val;
					}
				}
			}
			$subtotal_3 = round( $subtotal_2 + $emergency_flat + $date_flat, 2 );

			/*----------------------------------------------------------
			 * 5. Percent Surcharges (Calculated on subtotal_3)
			 *--------------------------------------------------------*/
			$emergency_pct_val = 0.0;
			if ( $is_emergency && QP_Helpers::get_setting( 'emergency_surcharge_enabled' ) ) {
				$type = QP_Helpers::get_setting( 'emergency_surcharge_type' );
				if ( 'percent' === $type ) {
					$pct               = floatval( QP_Helpers::get_setting( 'emergency_surcharge_value' ) );
					$val               = round( $subtotal_3 * ( $pct / 100 ), 2 );
					$items[]           = array(
						'item_label'  => sprintf( __( 'Emergency Surcharge (%s%%)', 'quote-pilot' ), $pct ),
						'item_type'   => 'surcharge',
						'quantity'    => 1.0,
						'unit_amount' => $val,
						'line_total'  => $val,
					);
					$emergency_pct_val = $val;
				}
			}

			$date_pct_val = 0.0;
			if ( ! empty( $preferred_date ) && isset( $date_rule ) && $date_rule && 'high_rate' === $date_rule->rule_type ) {
				if ( 'percent' === $date_rule->surcharge_type ) {
					$pct          = floatval( $date_rule->surcharge_value );
					$val          = round( $subtotal_3 * ( $pct / 100 ), 2 );
					$items[]      = array(
						'item_label'  => sprintf( __( 'Peak Date Surcharge (%s%%)', 'quote-pilot' ), $pct ),
						'item_type'   => 'surcharge',
						'quantity'    => 1.0,
						'unit_amount' => $val,
						'line_total'  => $val,
					);
					$date_pct_val = $val;
				}
			}
			$subtotal_4 = round( $subtotal_3 + $emergency_pct_val + $date_pct_val, 2 );

			/*----------------------------------------------------------
			 * 6. Discount (Coupon applied to subtotal_4)
			 *--------------------------------------------------------*/
			$discount_total = 0.0;
			$coupon_invalid = false;
			$coupon_error   = '';

			$coupon_code = self::get_field_val( 'coupon_code', $input, $visible_keys, '' );
			if ( ! empty( $coupon_code ) ) {
				$coupon = QP_Database::get_coupon( $coupon_code );

				if ( ! $coupon ) {
					$coupon_invalid = true;
					$coupon_error   = 'not_found';
				} elseif ( 1 !== intval( $coupon->active ) ) {
					$coupon_invalid = true;
					$coupon_error   = 'inactive';
				} else {
					// Check expiry
					$expired = false;
					if ( ! empty( $coupon->expires_at ) && '0000-00-00 00:00:00' !== $coupon->expires_at ) {
						$now = current_time( 'mysql' );
						if ( $coupon->expires_at < $now ) {
							$expired = true;
						}
					}

					if ( $expired ) {
						$coupon_invalid = true;
						$coupon_error   = 'expired';
					} elseif ( intval( $coupon->usage_limit ) > 0 && intval( $coupon->usage_count ) >= intval( $coupon->usage_limit ) ) {
						$coupon_invalid = true;
						$coupon_error   = 'limit_exceeded';
					}
				}

				if ( ! $coupon_invalid && isset( $coupon ) ) {
					if ( 'flat' === $coupon->discount_type ) {
						$val            = round( floatval( $coupon->discount_value ), 2 );
						$val            = min( $val, $subtotal_4 );
						$items[]        = array(
							'item_label'  => sprintf( __( 'Discount: Coupon %s', 'quote-pilot' ), $coupon->code ),
							'item_type'   => 'discount',
							'quantity'    => 1.0,
							'unit_amount' => -$val,
							'line_total'  => -$val,
						);
						$discount_total = $val;
					} elseif ( 'percent' === $coupon->discount_type ) {
						$pct            = floatval( $coupon->discount_value );
						$val            = round( $subtotal_4 * ( $pct / 100 ), 2 );
						$val            = min( $val, $subtotal_4 );
						$items[]        = array(
							'item_label'  => sprintf( __( 'Discount: Coupon %s (%s%%)', 'quote-pilot' ), $coupon->code, $pct ),
							'item_type'   => 'discount',
							'quantity'    => 1.0,
							'unit_amount' => -$val,
							'line_total'  => -$val,
						);
						$discount_total = $val;
					}
				}
			}
			$subtotal_5 = round( $subtotal_4 - $discount_total, 2 );

			/*----------------------------------------------------------
			 * 7. GST / Tax (Applied to subtotal_5)
			 *--------------------------------------------------------*/
			$tax_enabled = QP_Helpers::get_setting( 'gst_enabled', false );
			if ( ! $tax_enabled ) {
				$tax_enabled = QP_Helpers::get_setting( 'tax_enabled', false );
			}

			$tax_rate = floatval( QP_Helpers::get_setting( 'gst_rate', 0 ) );
			if ( $tax_rate <= 0 ) {
				$tax_rate = floatval( QP_Helpers::get_setting( 'tax_rate', 0 ) );
			}

			$tax_total = 0.0;
			if ( $tax_enabled && $tax_rate > 0 ) {
				$tax_total = round( $subtotal_5 * ( $tax_rate / 100 ), 2 );
				$items[]   = array(
					'item_label'  => sprintf( __( 'GST / Tax (%s%%)', 'quote-pilot' ), $tax_rate ),
					'item_type'   => 'tax',
					'quantity'    => 1.0,
					'unit_amount' => $tax_total,
					'line_total'  => $tax_total,
				);
			}
			$total = round( $subtotal_5 + $tax_total, 2 );

			// Build and return final totals and items list.
			$return = array(
				'base'            => $base,
				'surcharge_total' => round( $emergency_flat + $date_flat + $emergency_pct_val + $date_pct_val, 2 ),
				'discount_total'  => $discount_total,
				'tax_rate'        => $tax_rate,
				'tax_total'       => $tax_total,
				'total'           => $total,
				'items'           => $items,
			);

			if ( $coupon_invalid ) {
				$return['invalid_coupon'] = true;
				$return['coupon_error']   = $coupon_error;
			}

			return $return;
		}

		/**
		 * Retrieve a field value from the input if visible, otherwise return default.
		 *
		 * @param string     $key          The canonical field key.
		 * @param array      $input        The input values.
		 * @param array|null $visible_keys Whitelist of visible keys.
		 * @param mixed      $default      Fallback if not set or not visible.
		 * @return mixed
		 */
		private static function get_field_val( $key, array $input, $visible_keys, $default = 0 ) {
			if ( null !== $visible_keys && ! in_array( $key, $visible_keys, true ) ) {
				return $default;
			}
			return isset( $input[ $key ] ) ? $input[ $key ] : $default;
		}
	}

endif;
