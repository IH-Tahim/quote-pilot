<?php
/**
 * Shared helper utilities.
 *
 * Static methods used across every module for sanitisation,
 * formatting, and option retrieval.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Helpers' ) ) :

	/**
	 * Class QP_Helpers
	 *
	 * Collection of reusable static helper methods.
	 */
	class QP_Helpers {

		/**
		 * Sanitize a plain-text string.
		 *
		 * Wraps sanitize_text_field() for consistency so every module
		 * calls the same helper instead of the WP function directly.
		 *
		 * @param string $value Raw input string.
		 * @return string Sanitized string.
		 */
		public static function sanitize_text( $value ) {
			return sanitize_text_field( $value );
		}

		/**
		 * Format a numeric amount as a money string.
		 *
		 * Uses the currency_symbol stored in qp_settings and
		 * number_format() with two decimal places.
		 *
		 * @param float|string $amount The numeric amount to format.
		 * @return string Formatted money string, e.g. "$120.00".
		 */
		public static function format_money( $amount ) {
			$symbol = self::get_setting( 'currency_symbol', '$' );
			return $symbol . number_format( (float) $amount, 2 );
		}

		/**
		 * Retrieve a single plugin setting.
		 *
		 * Pulls the full qp_settings option once and returns the
		 * requested key, falling back to $default when the key does
		 * not exist.
		 *
		 * @param string $key     The setting key.
		 * @param mixed  $default Fallback value.
		 * @return mixed
		 */
		public static function get_setting( $key, $default = null ) {
			$settings = get_option( 'qp_settings', array() );

			if ( isset( $settings[ $key ] ) ) {
				return $settings[ $key ];
			}

			return $default;
		}

		/**
		 * Centralised nonce (and optional capability) check for AJAX.
		 *
		 * Every AJAX handler calls this first and bails on a false return.
		 * The nonce is read from the 'nonce' field (our localized name),
		 * falling back to WordPress's default '_wpnonce'. When a
		 * capability is supplied the current user must also hold it —
		 * left empty for public (nopriv) endpoints such as quote submit.
		 *
		 * @param string $nonce_action The nonce action string to verify against.
		 * @param string $capability   Optional capability the user must have.
		 * @return bool True when the request is valid, false otherwise.
		 */
		public static function verify_request( $nonce_action, $capability = '' ) {
			$nonce = '';

			if ( isset( $_POST['nonce'] ) ) {
				$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			} elseif ( isset( $_POST['_wpnonce'] ) ) {
				$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
			}

			if ( '' === $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
				return false;
			}

			if ( '' !== $capability && ! current_user_can( $capability ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Whitelisted sanitise of the incoming quote form payload.
		 *
		 * Drives entirely off the canonical field contract in
		 * QP_Fields::all(): it loops every declared field, reads the
		 * matching key from the raw payload, and applies that field's
		 * sanitize rule. Keys not in the contract are dropped, so the
		 * form, the engine, and this sanitizer can never drift apart.
		 *
		 * Input is expected to be the raw (slashed) $_POST array — it is
		 * unslashed here. Numeric inputs clamp to non-negative; the
		 * plaintext password passes through untouched (sanitising it
		 * would corrupt valid passwords); the caller hashes it via wp_*.
		 *
		 * @param array $raw The raw $_POST payload.
		 * @return array Clean, whitelisted quote input keyed by field key.
		 */
		public static function sanitize_quote_input( $raw ) {
			if ( ! is_array( $raw ) ) {
				return array();
			}

			$raw   = wp_unslash( $raw );
			$clean = array();

			foreach ( QP_Fields::all() as $key => $field ) {
				$rule = isset( $field['sanitize'] ) ? $field['sanitize'] : 'text';

				/* --- Multi-value fields (e.g. add-on slug arrays) ----- */
				if ( ! empty( $field['multiple'] ) ) {
					$values        = ( isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ) ? $raw[ $key ] : array();
					$clean[ $key ] = array();

					foreach ( $values as $value ) {
						$clean[ $key ][] = self::apply_sanitize_rule( $rule, $value );
					}

					continue;
				}

				/* --- Single-value fields ------------------------------ */
				$value         = array_key_exists( $key, $raw ) ? $raw[ $key ] : null;
				$clean[ $key ] = self::apply_sanitize_rule( $rule, $value );

				if ( isset( $field['transform'] ) && 'upper' === $field['transform'] && is_string( $clean[ $key ] ) ) {
					$clean[ $key ] = strtoupper( $clean[ $key ] );
				}
			}

			return $clean;
		}

		/**
		 * Apply a single named sanitize rule to one value.
		 *
		 * The rule names come from the 'sanitize' key in QP_Fields::all().
		 * A null value (field absent from the payload) yields the rule's
		 * safe empty equivalent.
		 *
		 * @param string $rule  The sanitize rule name.
		 * @param mixed  $value The raw value (or null if absent).
		 * @return mixed Sanitised value.
		 */
		private static function apply_sanitize_rule( $rule, $value ) {
			switch ( $rule ) {
				case 'abs_int':
					return absint( $value );

				case 'non_negative_float':
					return self::sanitize_non_negative( $value );

				case 'email':
					return ( null === $value ) ? '' : sanitize_email( $value );

				case 'tel':
					return ( null === $value ) ? '' : self::sanitize_phone( $value );

				case 'textarea':
					return ( null === $value ) ? '' : sanitize_textarea_field( $value );

				case 'bool':
					return empty( $value ) ? 0 : 1;

				case 'raw_password':
					// Deliberately untouched; the caller hashes it.
					return ( null === $value ) ? '' : (string) $value;

				case 'date':
					return ( null === $value ) ? '' : self::sanitize_date( $value );

				case 'slug':
					return ( null === $value ) ? '' : sanitize_key( $value );

				case 'text':
				default:
					return ( null === $value ) ? '' : sanitize_text_field( $value );
			}
		}

		/**
		 * Sanitise a phone number, keeping only dialling characters.
		 *
		 * @param string $value Raw phone input.
		 * @return string Cleaned phone string.
		 */
		private static function sanitize_phone( $value ) {
			$value = sanitize_text_field( $value );
			// Allow digits, spaces, and + - ( ) used in phone formatting.
			return preg_replace( '/[^0-9+\-() ]/', '', $value );
		}

		/**
		 * Sanitise a value as a non-negative number.
		 *
		 * Keeps fractional input (e.g. square footage) but never returns
		 * a value below zero.
		 *
		 * @param mixed $value Raw numeric input.
		 * @return float|int Non-negative number.
		 */
		private static function sanitize_non_negative( $value ) {
			$number = is_numeric( $value ) ? (float) $value : 0;
			$number = max( 0, $number );

			// Return an int when the value is whole, for clean storage.
			return ( floor( $number ) === $number ) ? (int) $number : $number;
		}

		/**
		 * Sanitise a Y-m-d date string, returning '' when invalid.
		 *
		 * @param string $value Raw date string.
		 * @return string Valid Y-m-d date or empty string.
		 */
		private static function sanitize_date( $value ) {
			$value = sanitize_text_field( $value );
			$date  = \DateTime::createFromFormat( 'Y-m-d', $value );

			if ( $date && $date->format( 'Y-m-d' ) === $value ) {
				return $value;
			}

			return '';
		}
	}

endif;
