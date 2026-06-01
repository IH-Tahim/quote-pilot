<?php
/**
 * Conditional show/hide evaluator for QuotePilot.
 *
 * Implements admin-definable rules to show/hide fields on the quote
 * form based on user selections. These rules are mirrored in the frontend
 * JavaScript and evaluated server-side to guarantee security.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Conditional' ) ) :

	/**
	 * Class QP_Conditional
	 */
	class QP_Conditional {

		/**
		 * Evaluate conditional rules against current inputs and return visible field keys.
		 *
		 * @param array $rules List of conditional rules.
		 * @param array $input Sanitized field inputs.
		 * @return array List of currently visible field keys.
		 */
		public static function evaluate( array $rules, array $input ) {
			$visible_keys = array();
			$all_fields   = array_keys( QP_Fields::all() );

			foreach ( $all_fields as $field_key ) {
				if ( self::is_visible( $field_key, $rules, $input ) ) {
					$visible_keys[] = $field_key;
				}
			}

			return $visible_keys;
		}

		/**
		 * Check if a single field key is visible.
		 *
		 * Rules structure:
		 * [
		 *   [
		 *     'target_field' => 'oven_size',
		 *     'action'       => 'show', // show|hide
		 *     'logic'        => 'all',  // all|any
		 *     'conditions'   => [ ['field' => 'service_id', 'op' => 'is', 'value' => '12'], ... ]
		 *   ]
		 * ]
		 *
		 * @param string $field_key Canonical field key.
		 * @param array  $rules     List of conditional rules.
		 * @param array  $input     Sanitized inputs.
		 * @return bool True if visible, false if hidden.
		 */
		public static function is_visible( string $field_key, array $rules, array $input ) {
			// Find all rules targeting this specific field.
			$field_rules = array();
			foreach ( $rules as $rule ) {
				if ( isset( $rule['target_field'] ) && $rule['target_field'] === $field_key ) {
					$field_rules[] = $rule;
				}
			}

			// A field with no conditional rule is visible by default.
			if ( empty( $field_rules ) ) {
				return true;
			}

			foreach ( $field_rules as $rule ) {
				$action     = isset( $rule['action'] ) ? $rule['action'] : 'show';
				$logic      = isset( $rule['logic'] ) ? $rule['logic'] : 'all';
				$conditions = isset( $rule['conditions'] ) ? $rule['conditions'] : array();

				if ( empty( $conditions ) ) {
					continue;
				}

				$satisfied_count = 0;
				foreach ( $conditions as $cond ) {
					if ( self::evaluate_condition( $cond, $input ) ) {
						$satisfied_count++;
					}
				}

				$is_satisfied = false;
				if ( 'all' === $logic ) {
					$is_satisfied = ( $satisfied_count === count( $conditions ) );
				} else { // 'any'
					$is_satisfied = ( $satisfied_count > 0 );
				}

				// If action is show, the field must satisfy the conditions to be shown.
				if ( 'show' === $action ) {
					if ( ! $is_satisfied ) {
						return false;
					}
				} else { // hide
					if ( $is_satisfied ) {
						return false;
					}
				}
			}

			return true;
		}

		/**
		 * Evaluate a single condition against current inputs.
		 *
		 * Operators supported:
		 * - is: strict string comparison.
		 * - is_not: strict string inequality.
		 * - greater_than: numeric comparison.
		 * - less_than: numeric comparison.
		 * - contains: matches value in array or substring in text.
		 * - checked: checks if checkbox is truthy.
		 *
		 * @param array $cond  Condition specifications: [field, op, value].
		 * @param array $input Sanitized inputs.
		 * @return bool True if satisfied, false otherwise.
		 */
		private static function evaluate_condition( array $cond, array $input ) {
			$field = isset( $cond['field'] ) ? $cond['field'] : '';
			$op    = isset( $cond['op'] ) ? $cond['op'] : 'is';
			$val   = isset( $cond['value'] ) ? $cond['value'] : null;

			if ( empty( $field ) ) {
				return false;
			}

			$input_val = isset( $input[ $field ] ) ? $input[ $field ] : null;

			switch ( $op ) {
				case 'is':
					return ( strval( $input_val ) === strval( $val ) );

				case 'is_not':
					return ( strval( $input_val ) !== strval( $val ) );

				case 'greater_than':
					return ( floatval( $input_val ) > floatval( $val ) );

				case 'less_than':
					return ( floatval( $input_val ) < floatval( $val ) );

				case 'contains':
					if ( is_array( $input_val ) ) {
						return in_array( $val, $input_val, true );
					}
					if ( is_string( $input_val ) ) {
						return ( false !== strpos( $input_val, strval( $val ) ) );
					}
					return false;

				case 'checked':
					return ! empty( $input_val );

				default:
					return false;
			}
		}
	}

endif;
