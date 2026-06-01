<?php
/**
 * Quote Calculator module loader.
 *
 * Boots the quote engine and enqueues its front-end assets — but ONLY
 * on pages that actually contain the [quotepilot_form] shortcode, so the
 * rest of the site stays fast. Localises a single JS config object the
 * browser uses to preview prices.
 *
 * Scope note: this is B1. The price engine, conditional logic, shortcode,
 * form, submission handler, and lead handler are wired in later B-blocks.
 * Their instantiation is intentionally absent here so the module loads
 * cleanly before those classes exist.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Quote' ) ) :

	/**
	 * Class QP_Quote
	 *
	 * Entry point for the Quote Calculator module.
	 */
	class QP_Quote {

		/**
		 * Shortcode tag the module watches for.
		 *
		 * @var string
		 */
		const SHORTCODE = 'quotepilot_form';

		/**
		 * Nonce action shared by every quote-form AJAX endpoint
		 * (submit + lead capture). Verified via QP_Helpers::verify_request().
		 *
		 * @var string
		 */
		const NONCE_ACTION = 'qp_quote_form';

		/**
		 * Initialise the module.
		 *
		 * Called by QP_Modules::boot() when the quote_calculator module
		 * is enabled.
		 *
		 * @return self
		 */
		public static function init() {
			return new self();
		}

		/**
		 * Constructor — register hooks.
		 */
		public function __construct() {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

			/*
			 * Later B-blocks wire their components here, e.g.:
			 *   QP_Shortcode, QP_Price_Engine, QP_Conditional,
			 *   QP_Submission, QP_Leads.
			 * Not loaded in B1.
			 */
		}

		/**
		 * Conditionally enqueue the quote calculator assets.
		 *
		 * Bails immediately unless the current page contains the form,
		 * then loads the CSS/JS and localises the config object.
		 *
		 * @return void
		 */
		public function enqueue_assets() {
			if ( ! $this->page_has_form() ) {
				return;
			}

			wp_enqueue_style(
				'qp-quote-calculator',
				QP_PLUGIN_URL . 'public/css/quote-calculator.css',
				array(),
				QP_VERSION
			);

			wp_enqueue_script(
				'qp-quote-calculator',
				QP_PLUGIN_URL . 'public/js/quote-calculator.js',
				array(),
				QP_VERSION,
				true
			);

			wp_localize_script( 'qp-quote-calculator', 'QPData', $this->get_localized_data() );
		}

		/**
		 * Does the current request render the quote form?
		 *
		 * Uses has_shortcode() (works once the shortcode is registered in
		 * B4) plus a raw string fallback so detection still works during
		 * development before registration. A filter lets page builders
		 * (Elementor, blocks, templates) force the assets on.
		 *
		 * @return bool
		 */
		private function page_has_form() {
			$has = false;

			if ( is_singular() ) {
				$post = get_post();

				if ( $post instanceof WP_Post ) {
					$content = $post->post_content;

					if ( has_shortcode( $content, self::SHORTCODE )
						|| false !== stripos( $content, '[' . self::SHORTCODE ) ) {
						$has = true;
					}
				}
			}

			/**
			 * Filter whether quote-calculator assets load on this request.
			 *
			 * @param bool $has Whether the form was detected in content.
			 */
			return (bool) apply_filters( 'qp_load_quote_assets', $has );
		}

		/**
		 * Build the config object handed to the browser.
		 *
		 * Everything here is for the JS PREVIEW only. The browser never
		 * computes the authoritative price — PHP recalculates on submit.
		 *
		 * @return array
		 */
		private function get_localized_data() {
			return array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( self::NONCE_ACTION ),
				'currency'   => QP_Helpers::get_setting( 'currency_symbol', '$' ),
				'tax'        => array(
					'enabled' => (bool) QP_Helpers::get_setting( 'tax_enabled', false ),
					'rate'    => (float) QP_Helpers::get_setting( 'tax_rate', 0 ),
				),
				'services'   => $this->get_services_pricing(),
				'date_rules' => $this->get_date_rules(),
				'consent'    => $this->get_consent_config(),
				'fields'     => QP_Fields::all(),
			);
		}

		/**
		 * Collect active services and their pricing config for the JS preview.
		 *
		 * @return array<int,array> Keyed by service post ID.
		 */
		private function get_services_pricing() {
			$services = array();

			if ( ! class_exists( 'QP_Services_Meta' ) ) {
				return $services;
			}

			$posts = get_posts(
				array(
					'post_type'        => 'qp_service',
					'post_status'      => 'publish',
					'numberposts'      => -1,
					'orderby'          => 'menu_order title',
					'order'            => 'ASC',
					'suppress_filters' => false,
				)
			);

			foreach ( $posts as $post ) {
				$pricing = QP_Services_Meta::get_pricing( $post->ID );

				if ( empty( $pricing['service_active'] ) ) {
					continue;
				}

				$pricing['id']             = (int) $post->ID;
				$pricing['title']          = get_the_title( $post );
				$pricing['slug']           = $post->post_name;
				$services[ (int) $post->ID ] = $pricing;
			}

			return $services;
		}

		/**
		 * Fetch every date rule for the JS calendar (closed / high-rate days).
		 *
		 * @return array
		 */
		private function get_date_rules() {
			$out = array();

			foreach ( (array) QP_Database::get_all_date_rules() as $rule ) {
				$out[] = array(
					'date'            => $rule->rule_date,
					'type'            => $rule->rule_type,
					'surcharge_type'  => $rule->surcharge_type,
					'surcharge_value' => (float) $rule->surcharge_value,
					'note'            => $rule->note,
				);
			}

			return $out;
		}

		/**
		 * Resolve consent configuration (merged vs split + box definitions).
		 *
		 * Falls back to a sensible split-mode default until the admin
		 * settings screen exposes this.
		 *
		 * @return array
		 */
		private function get_consent_config() {
			$default = array(
				'mode'  => 'split', // 'split' | 'merged'.
				'boxes' => array(
					'consent_privacy'   => array(
						'required' => true,
						'label'    => __( 'I agree to the Privacy Policy', 'quote-pilot' ),
					),
					'consent_terms'     => array(
						'required' => true,
						'label'    => __( 'I accept the Terms & Conditions', 'quote-pilot' ),
					),
					'consent_marketing' => array(
						'required' => false,
						'label'    => __( 'I would like to receive offers and updates', 'quote-pilot' ),
					),
				),
			);

			$config = QP_Helpers::get_setting( 'consent_config', array() );

			if ( ! is_array( $config ) || empty( $config ) ) {
				return $default;
			}

			return wp_parse_args( $config, $default );
		}
	}

endif;
