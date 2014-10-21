<?php
/**
 * Plugin Name: WP Job Manager - Predefined Regions
 * Plugin URI:  https://github.com/astoundify/wp-job-manager-regions/
 * Description: Create predefined regions/locations that job submissions can associate themselves with.
 * Author:      Astoundify
 * Author URI:  http://astoundify.com
 * Version:     1.5.1
 * Text Domain: wp-job-manager-locations
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Astoundify_Job_Manager_Regions {

	/**
	 * @var $instance
	 */
	private static $instance;

	/**
	 * Make sure only one instance is only running.
	 */
	public static function instance() {
		if ( ! isset ( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Start things up.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->file         = __FILE__;
		$this->basename     = plugin_basename( $this->file );
		$this->plugin_dir   = plugin_dir_path( $this->file );
		$this->plugin_url   = plugin_dir_url ( $this->file );
		$this->lang_dir     = trailingslashit( $this->plugin_dir . 'languages' );
		$this->domain       = 'wp-job-manager-locations';

		$files = array(
			'includes/class-taxonomy.php',
			'includes/class-template.php',
			'includes/class-widgets.php'
		);

		foreach ( $files as $file ) {
			include_once( $this->plugin_dir . '/' . $file );
		}

		$this->taxonomy = new Astoundify_Job_Manager_Regions_Taxonomy;
		$this->template = new Astoundify_Job_Manager_Regions_Template;

		$this->setup_actions();
	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function setup_actions() {
		add_filter( 'job_manager_settings', array( $this, 'job_manager_settings' ) );

		if ( get_option( 'job_manager_regions_filter' ) ) {
			add_filter( 'job_manager_output_jobs_defaults', array( $this, 'job_manager_output_jobs_defaults' ) );
			add_filter( 'job_manager_get_listings', array( $this, 'job_manager_get_listings' ) );
			add_filter( 'job_manager_get_listings_args', array( $this, 'job_manager_get_listings_args' ) );
		}

		$this->load_textdomain();
	}

	/**
	 * Add settings fields to select the appropriate form for each listing type.
	 *
	 * @since WP Job Manager - Predefiend Regions 1.4.1
	 *
	 * @return void
	 */
	public function job_manager_settings($settings) {
		$settings[ 'job_listings' ][1][] = array(
			'name'     => 'job_manager_regions_filter',
			'std'      => '1',
			'label'    => __( 'Job Regions', 'wp-job-manager-locations' ),
			'cb_label' => __( 'Filter by Region', 'wp-job-manager-locations' ),
			'desc'     => __( 'Use a dropdown instead of a text input.' ),
			'type'     => 'checkbox'
		);

		return $settings;
	}

	public function job_manager_output_jobs_defaults( $defaults ) {
		$defaults[ 'selected_region' ] = '';

		if ( is_tax( 'job_listing_region' ) ) {
			$type = get_queried_object();

			if ( ! $type ) {
				return $defaults;
			}

			$defaults[ 'show_categories' ] = true;
			$defaults[ 'selected_region' ] = $type->term_id;
		}

		return $defaults;
	}

	public function job_manager_get_listings( $args ) {
		$params = array();

		if ( isset( $_POST[ 'form_data' ] ) ) {

			parse_str( $_POST[ 'form_data' ], $params );

			if ( isset( $params[ 'search_region' ] ) && 0 != $params[ 'search_region' ] ) {
				$region = $params[ 'search_region' ];

				if ( is_int( $region ) ) {
					$region = array( $region );
				}

				$args[ 'tax_query' ][] = array(
					'taxonomy' => 'job_listing_region',
					'field'    => 'id',
					'terms'    => $region,
					'operator' => 'IN'
				);

				add_filter( 'job_manager_get_listings_custom_filter', '__return_true' );
				add_filter( 'job_manager_get_listings_custom_filter_text', array( $this, 'custom_filter_text' ) );
				add_filter( 'job_manager_get_listings_custom_filter_rss_args', array( $this, 'custom_filter_rss' ) );
			}

		}

		return $args;
	}

	public function job_manager_get_listings_args( $args ) {
		$params = array();

		if ( isset( $_POST[ 'form_data' ] ) ) {

			parse_str( $_POST[ 'form_data' ], $params );

			if ( isset( $params[ 'search_region' ] ) && 0 != $params[ 'search_region' ] ) {
				$args[ 'search_location' ] = null;
			}

		}

		return $args;
	}

	/**
	 * Append 'showing' text
	 * @return string
	 */
	public function custom_filter_text( $text ) {
		$params = array();

		parse_str( $_POST[ 'form_data' ], $params );

		$term = get_term( $params[ 'search_region' ], 'job_listing_region' );

		$text .= sprintf( ' ' .  __( 'in %s', 'wp-job-manager-locations' ) . ' ', $term->name );

		return $text;
	}

	/**
	 * apply_tag_filter_rss
	 * @return array
	 */
	public function custom_filter_rss( $args ) {
		$params = array();

		parse_str( $_POST[ 'form_data' ], $params );

		$args[ 'job_region' ] = $params[ 'search_region' ];

		return $args;
	}

	/**
	 * Loads the plugin language files
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		$mofile_local = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/' . $this->domain . '/' . $mofile;

		if ( file_exists( $mofile_global ) ) {
			return load_textdomain( $this->domain, $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			return load_textdomain( $this->domain, $mofile_local );
		}

		return false;
	}
}
add_action( 'plugins_loaded', array( 'Astoundify_Job_Manager_Regions', 'instance' ) );

/**
 * Start things up.
 *
 * Use this function instead of a global.
 *
 * $ajmr = ajmr();
 *
 * @since 1.0.0
 */
function wp_job_manager_regions() {
	return Astoundify_Job_Manager_Regions::instance();
}