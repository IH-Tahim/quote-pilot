<?php
/**
 * Canonical quote-form field reference.
 *
 * This is the SINGLE source of truth for every field name on the quote
 * form. The form markup (B4), the sanitizer (QP_Helpers), the price
 * engine (B2), the conditional logic (B3), and the qp_service pricing
 * meta box all read their keys from here. If a field name only lives in
 * one place, it silently evaluates to zero and produces a wrong price —
 * so nothing hard-codes a field name; everything loops over this list.
 *
 * Each field declares:
 *   key               the canonical name= attribute (the array key)
 *   label             human-readable label
 *   input_type        number|tel|email|select|radio|checkbox|date|text|textarea|password
 *   sanitize          abs_int|non_negative_float|email|tel|text|textarea|bool|raw_password|date|slug
 *   pricing_role      base|multiplier|addon_flat|addon_percent|surcharge|discount|contact|scheduling|consent|account|none
 *   service_meta_key  matching _qp_* meta key on qp_service, or null for universal fields
 *   multiple          (optional) true when the field submits an array of values
 *   options           (optional) allowed values for select/radio fields
 *   transform         (optional) post-sanitize transform, e.g. 'upper'
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Fields' ) ) :

	/**
	 * Class QP_Fields
	 *
	 * Returns the canonical quote-form field contract.
	 */
	class QP_Fields {

		/**
		 * The full, ordered list of quote-form fields.
		 *
		 * @return array<string,array> Field key => field definition.
		 */
		public static function all() {
			return array(

				/*------------------------------------------------------
				 * Service selection (picks which qp_service applies; its
				 * base_price meta is the booking base).
				 *----------------------------------------------------*/
				'service_id'        => array(
					'label'            => __( 'Service', 'quote-pilot' ),
					'input_type'       => 'radio',
					'sanitize'         => 'abs_int',
					'pricing_role'     => 'base',
					'service_meta_key' => null,
				),

				/*------------------------------------------------------
				 * Property size & room counts (per-unit multipliers).
				 *----------------------------------------------------*/
				'sqft'              => array(
					'label'            => __( 'Square Footage', 'quote-pilot' ),
					'input_type'       => 'number',
					'sanitize'         => 'non_negative_float',
					'pricing_role'     => 'multiplier',
					'service_meta_key' => 'price_per_sqft',
				),
				'bedrooms'          => array(
					'label'            => __( 'Bedrooms', 'quote-pilot' ),
					'input_type'       => 'number',
					'sanitize'         => 'abs_int',
					'pricing_role'     => 'multiplier',
					'service_meta_key' => 'price_per_bedroom',
				),
				'bathrooms'         => array(
					'label'            => __( 'Bathrooms', 'quote-pilot' ),
					'input_type'       => 'number',
					'sanitize'         => 'abs_int',
					'pricing_role'     => 'multiplier',
					'service_meta_key' => 'price_per_bathroom',
				),
				'extra_bathrooms'   => array(
					'label'            => __( 'Extra Bathrooms', 'quote-pilot' ),
					'input_type'       => 'number',
					'sanitize'         => 'abs_int',
					'pricing_role'     => 'multiplier',
					'service_meta_key' => 'price_per_extra_bathroom',
				),
				'living_rooms'      => array(
					'label'            => __( 'Living Rooms', 'quote-pilot' ),
					'input_type'       => 'number',
					'sanitize'         => 'abs_int',
					'pricing_role'     => 'multiplier',
					// NOTE: meta box does not yet save this key. See B0 gaps.
					'service_meta_key' => 'price_per_living_room',
				),
				'stories'           => array(
					'label'            => __( 'Building Stories', 'quote-pilot' ),
					'input_type'       => 'radio',
					'sanitize'         => 'abs_int',
					'pricing_role'     => 'multiplier',
					'options'          => array( '1', '2', '3' ),
					// NOTE: meta box does not yet save this key. See B0 gaps.
					'service_meta_key' => 'price_per_story',
				),

				/*------------------------------------------------------
				 * Oven cleaning (count is priced; size is a modifier).
				 *----------------------------------------------------*/
				'ovens'             => array(
					'label'            => __( 'Number of Ovens', 'quote-pilot' ),
					'input_type'       => 'number',
					'sanitize'         => 'abs_int',
					'pricing_role'     => 'multiplier',
					'service_meta_key' => 'price_per_oven',
				),
				'oven_size'         => array(
					'label'            => __( 'Oven Size', 'quote-pilot' ),
					'input_type'       => 'radio',
					'sanitize'         => 'text',
					'pricing_role'     => 'multiplier',
					'options'          => array( 'standard', 'large' ),
					// NOTE: no size-modifier meta yet. See B0 gaps.
					'service_meta_key' => null,
				),

				/*------------------------------------------------------
				 * Add-ons (selected slugs; price/type from service meta).
				 *----------------------------------------------------*/
				'addons_flat'       => array(
					'label'            => __( 'Add-ons (flat price)', 'quote-pilot' ),
					'input_type'       => 'checkbox',
					'sanitize'         => 'slug',
					'pricing_role'     => 'addon_flat',
					'multiple'         => true,
					// NOTE: needs a per-service add-on definitions meta. See B0 gaps.
					'service_meta_key' => null,
				),
				'addons_percent'    => array(
					'label'            => __( 'Add-ons (percentage)', 'quote-pilot' ),
					'input_type'       => 'checkbox',
					'sanitize'         => 'slug',
					'pricing_role'     => 'addon_percent',
					'multiple'         => true,
					// NOTE: needs a per-service add-on definitions meta. See B0 gaps.
					'service_meta_key' => null,
				),

				/*------------------------------------------------------
				 * Surcharge.
				 *----------------------------------------------------*/
				'emergency'         => array(
					'label'            => __( 'Emergency / Same-day Service', 'quote-pilot' ),
					'input_type'       => 'checkbox',
					'sanitize'         => 'bool',
					'pricing_role'     => 'surcharge',
					'service_meta_key' => null,
				),

				/*------------------------------------------------------
				 * Scheduling (universal — not priced from service meta).
				 *----------------------------------------------------*/
				'preferred_date'    => array(
					'label'            => __( 'Preferred Date', 'quote-pilot' ),
					'input_type'       => 'date',
					'sanitize'         => 'date',
					'pricing_role'     => 'scheduling',
					'service_meta_key' => null,
				),
				'preferred_time'    => array(
					'label'            => __( 'Preferred Time', 'quote-pilot' ),
					'input_type'       => 'select',
					'sanitize'         => 'text',
					'pricing_role'     => 'scheduling',
					'service_meta_key' => null,
				),

				/*------------------------------------------------------
				 * Discount.
				 *----------------------------------------------------*/
				'coupon_code'       => array(
					'label'            => __( 'Coupon Code', 'quote-pilot' ),
					'input_type'       => 'text',
					'sanitize'         => 'text',
					'pricing_role'     => 'discount',
					'transform'        => 'upper',
					'service_meta_key' => null,
				),

				/*------------------------------------------------------
				 * Contact details (universal).
				 *----------------------------------------------------*/
				'customer_name'     => array(
					'label'            => __( 'Full Name', 'quote-pilot' ),
					'input_type'       => 'text',
					'sanitize'         => 'text',
					'pricing_role'     => 'contact',
					'service_meta_key' => null,
				),
				'customer_email'    => array(
					'label'            => __( 'Email Address', 'quote-pilot' ),
					'input_type'       => 'email',
					'sanitize'         => 'email',
					'pricing_role'     => 'contact',
					'service_meta_key' => null,
				),
				'customer_phone'    => array(
					'label'            => __( 'Phone Number', 'quote-pilot' ),
					'input_type'       => 'tel',
					'sanitize'         => 'tel',
					'pricing_role'     => 'contact',
					'service_meta_key' => null,
				),
				'customer_address'  => array(
					'label'            => __( 'Service Address', 'quote-pilot' ),
					'input_type'       => 'textarea',
					'sanitize'         => 'textarea',
					'pricing_role'     => 'contact',
					'service_meta_key' => null,
				),

				/*------------------------------------------------------
				 * Consent (split boxes + a merged single box; admin
				 * config in B4 decides which mode renders).
				 *----------------------------------------------------*/
				'consent_privacy'   => array(
					'label'            => __( 'I agree to the Privacy Policy', 'quote-pilot' ),
					'input_type'       => 'checkbox',
					'sanitize'         => 'bool',
					'pricing_role'     => 'consent',
					'service_meta_key' => null,
				),
				'consent_terms'     => array(
					'label'            => __( 'I accept the Terms & Conditions', 'quote-pilot' ),
					'input_type'       => 'checkbox',
					'sanitize'         => 'bool',
					'pricing_role'     => 'consent',
					'service_meta_key' => null,
				),
				'consent_marketing' => array(
					'label'            => __( 'I would like to receive offers and updates', 'quote-pilot' ),
					'input_type'       => 'checkbox',
					'sanitize'         => 'bool',
					'pricing_role'     => 'consent',
					'service_meta_key' => null,
				),
				'consent_all'       => array(
					'label'            => __( 'I agree to the Privacy Policy and Terms & Conditions', 'quote-pilot' ),
					'input_type'       => 'checkbox',
					'sanitize'         => 'bool',
					'pricing_role'     => 'consent',
					'service_meta_key' => null,
				),

				/*------------------------------------------------------
				 * Account opt-in.
				 *----------------------------------------------------*/
				'create_account'    => array(
					'label'            => __( 'Create an account for faster booking', 'quote-pilot' ),
					'input_type'       => 'checkbox',
					'sanitize'         => 'bool',
					'pricing_role'     => 'account',
					'service_meta_key' => null,
				),
				'password'          => array(
					'label'            => __( 'Password', 'quote-pilot' ),
					'input_type'       => 'password',
					'sanitize'         => 'raw_password',
					'pricing_role'     => 'account',
					'service_meta_key' => null,
				),
			);
		}

		/**
		 * Fetch a single field definition by key.
		 *
		 * @param string $key Canonical field key.
		 * @return array|null The field definition, or null if unknown.
		 */
		public static function get( $key ) {
			$all = self::all();
			return isset( $all[ $key ] ) ? $all[ $key ] : null;
		}

		/**
		 * Fetch every field that has the given pricing role.
		 *
		 * Used by the price engine (B2) to walk only the fields that
		 * affect money, in a defined order.
		 *
		 * @param string $role One of the pricing_role values.
		 * @return array<string,array> Subset of all() with that role.
		 */
		public static function by_pricing_role( $role ) {
			$matches = array();

			foreach ( self::all() as $key => $field ) {
				if ( isset( $field['pricing_role'] ) && $role === $field['pricing_role'] ) {
					$matches[ $key ] = $field;
				}
			}

			return $matches;
		}
	}

endif;
