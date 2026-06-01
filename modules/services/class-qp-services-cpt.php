<?php
/**
 * Services custom post type registration.
 *
 * Registers the public qp_service CPT with REST API support,
 * archive, and SEO-friendly rewrite slug.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Services_CPT' ) ) :

	/**
	 * Class QP_Services_CPT
	 *
	 * Registers the qp_service custom post type.
	 */
	class QP_Services_CPT {

		/**
		 * Constructor — hooks the CPT registration to the init action.
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'register_post_type' ) );
		}

		/**
		 * Register the qp_service post type.
		 *
		 * Public, REST-enabled, archive-enabled so it works in the
		 * block editor and with page builders like Elementor.
		 *
		 * Note: After first activation the user may need to visit
		 * Settings > Permalinks to flush rewrite rules, or
		 * deactivate/reactivate the plugin (the activator flushes
		 * automatically on activation).
		 *
		 * @return void
		 */
		public function register_post_type() {

			$labels = array(
				'name'                  => _x( 'Services', 'Post type general name', 'quote-pilot' ),
				'singular_name'         => _x( 'Service', 'Post type singular name', 'quote-pilot' ),
				'menu_name'             => __( 'QuotePilot Services', 'quote-pilot' ),
				'name_admin_bar'        => __( 'Service', 'quote-pilot' ),
				'add_new'               => __( 'Add New', 'quote-pilot' ),
				'add_new_item'          => __( 'Add New Service', 'quote-pilot' ),
				'new_item'              => __( 'New Service', 'quote-pilot' ),
				'edit_item'             => __( 'Edit Service', 'quote-pilot' ),
				'view_item'             => __( 'View Service', 'quote-pilot' ),
				'all_items'             => __( 'All Services', 'quote-pilot' ),
				'search_items'          => __( 'Search Services', 'quote-pilot' ),
				'not_found'             => __( 'No services found.', 'quote-pilot' ),
				'not_found_in_trash'    => __( 'No services found in Trash.', 'quote-pilot' ),
				'archives'              => __( 'Service Archives', 'quote-pilot' ),
				'filter_items_list'     => __( 'Filter services list', 'quote-pilot' ),
				'items_list_navigation' => __( 'Services list navigation', 'quote-pilot' ),
				'items_list'            => __( 'Services list', 'quote-pilot' ),
			);

			$args = array(
				'labels'             => $labels,
				'description'        => __( 'Cleaning services offered for instant quoting.', 'quote-pilot' ),
				'public'             => true,
				'has_archive'        => true,
				'show_in_rest'       => true,
				'rewrite'            => array( 'slug' => 'services' ),
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'menu_icon'          => 'dashicons-sparkles',
				'menu_position'      => 26,
				'capability_type'    => 'post',
				'show_in_menu'       => true,
				'show_ui'            => true,
				'show_in_admin_bar'  => true,
				'show_in_nav_menus'  => true,
				'publicly_queryable' => true,
				'exclude_from_search' => false,
			);

			register_post_type( 'qp_service', $args );
		}
	}

endif;
