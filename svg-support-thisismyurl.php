<?php
/**
 * Author:              Christopher Ross
 * Author URI:          https://thisismyurl.com/?source=svg-support-thisismyurl
 * Plugin Name:         SVG Support by thisismyurl
 * Plugin URI:          https://thisismyurl.com/svg-support-thisismyurl/?source=svg-support-thisismyurl
 * Donate link:         https://thisismyurl.com/svg-support-thisismyurl/#register?source=svg-support-thisismyurl
 * 
 * Description:         Safely enable SVG uploads and convert existing images to AVIF format.
 * Tags:                svg, uploads, media library, optimization
 * 
 * Version: 1.26010222
 * Requires at least:   5.3
 * Requires PHP:        7.4
 * 
 * Update URI:          https://github.com/thisismyurl/svg-support-thisismyurl
 * GitHub Plugin URI:   https://github.com/thisismyurl/svg-support-thisismyurl
 * Primary Branch:      main
 * Text Domain:         svg-support-thisismyurl
 * 
 * License:             GPL2
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * @package TIMU_AVIF_Support
 * 
 * 
 */
/**
 * Security: Prevent direct file access to prevent path traversal or unauthorized execution.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Version-aware Core Loader
 *
 * Checks for the existence of the base TIMU_Core_v1 class to ensure the shared 
 * library is loaded exactly once, preventing class redeclaration errors in a 
 * multi-plugin environment.
 */
function timu_svg_support_load_core() {
	$core_path = plugin_dir_path( __FILE__ ) . 'core/class-timu-core.php';
	if ( ! class_exists( 'TIMU_Core_v1' ) ) {
		require_once $core_path;
	}
}
timu_svg_support_load_core();

/**
 * Class TIMU_SVG_Support
 *
 * Extends TIMU_Core_v1 to leverage shared settings generation and image conversion 
 * utilities. Implements specific logic for SVG sanitization and Media Library display.
 */
class TIMU_SVG_Support extends TIMU_Core_v1 {

	/**
	 * Constructor: Orchestrates the plugin lifecycle.
	 *
	 * Registers hooks for settings initialization, MIME type filtering, 
	 * and pre-upload processing.
	 */
	public function __construct() {
		parent::__construct(
			'svg-support-thisismyurl',      // Unique plugin slug.
			plugin_dir_url( __FILE__ ),       // Base URL for enqueuing assets.
			'timu_svg_settings_group',        // Settings API group name.
			'',                               // Custom icon URL (null for default).
			'tools.php'                       // Admin menu parent location.
		);

		/**
		 * Hook: Initialize settings blueprint after standard core initialization.
		 */
		add_action( 'init', array( $this, 'setup_plugin' ) );

		/**
		 * Filters: Lifecycle hooks for expanding and sanitizing uploads.
		 */
		add_filter( 'upload_mimes', array( $this, 'add_svg_mime_types' ) );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'process_svg_upload' ) );

		/**
		 * Actions: UI enhancements for the WordPress Admin dashboard.
		 */
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		/**
		 * Activation: Register defaults only once upon plugin activation.
		 */
		register_activation_hook( __FILE__, array( $this, 'activate_plugin_defaults' ) );

		add_action( 'timu_sidebar_under_banner', array( $this, 'render_default_sidebar_actions' ) );
	}

	/**
	 * Configuration Blueprint
	 *
	 * Defines the settings schema for the Core's automated UI engine. 
	 * Utilizes cascading visibility via parent/child keys for a streamlined UX.
	 */
	public function setup_plugin() {
		/** @var bool $webp_active Dependency check for sibling WebP plugin. */
		$webp_active = class_exists( 'TIMU_WebP_Support' );
		/** @var bool $avif_active Dependency check for sibling AVIF plugin. */
		$avif_active = class_exists( 'TIMU_AVIF_Support' );

		$this->is_licensed();

		/**
		 * Dynamically build the radio options based on the presence of siblings.
		 */
		$format_options = array(
			'svg'     => __( 'Upload as unsafe .svg', 'svg-support-thisismyurl' ),
			'sanitize' => __( 'Sanitize XML for safe .svg', 'svg-support-thisismyurl' ),
		);

		if ( $webp_active ) {
			$format_options['webp'] = __( 'Convert to .webp file format.', 'svg-support-thisismyurl' );
		}

		if ( $avif_active ) {
			$format_options['avif'] = __( 'Convert to .avif file format.', 'svg-support-thisismyurl' );
		}

		$blueprint = array(
			'config' => array(
				'title'  => __( 'SVG Configuration', 'svg-support-thisismyurl' ),
				'fields' => array(
					'enabled'       => array(
						'type'      => 'switch',
						'label'     => __( 'Enable SVG Support', 'svg-support-thisismyurl' ),
						'desc'      => __( 'Allows .svg files to be uploaded and processed by this plugin.', 'svg-support-thisismyurl' ),
						'is_parent' => true,
						'default'   => 1,
					),
					'target_format' => array(
						'type'      => 'radio',
						'label'     => __( 'SVG Handling Mode', 'svg-support-thisismyurl' ),
						'parent'    => 'enabled',
						'is_parent' => true,
						'options'   => $format_options,
						'default'   => 'sanitize',
						'desc'      => ( ! $webp_active || ! $avif_active )
									? __( 'Install <a href="https://thisismyurl.com/thisismyurl-webp-support/">WebP</a> or <a href="https://thisismyurl.com/thisismyurl-avif-support/">AVIF</a> plugins for more options.', 'svg-support-thisismyurl' )
									: __( 'Choose how to process .svg files upon upload.', 'svg-support-thisismyurl' ),
					),
					'webp_quality'  => array(
						'type'    => 'range', // Now a slider!
						'default' => 80,
						'min'     => 10,
						'max'     => 100,
						'label'        => __( 'WebP Quality', 'svg-support-thisismyurl' ),
						'default'      => 80,
						'show_if' => array(
							'field' => 'target_format', // Must match the ID of your radio buttons
							'value' => 'webp'           // Must match the value 'webp' in the radio option
						)
					),
					'avif_quality'  => array(
						'type'    => 'range', // Now a slider!
						'default' => 80,
						'min'     => 10,
						'max'     => 100,
						'label'        => __( 'AVIF Quality', 'svg-support-thisismyurl' ),
						'show_if' => array(
							'field' => 'target_format', // Must match the ID of your radio buttons
							'value' => 'avif'           // Must match the value 'webp' in the radio option
						)
					),
					'hr'  => array(
						'type'    	=> 'hr'
					),
					'license_key'  => array(
						'type'    => 'license',
						'default' => '',
						'label'   => __( 'License Key', 'webp-support-thisismyurl' ),
						'desc'      => ( $this->license_message )
					),
				),
			),
		);

		$this->init_settings_generator( $blueprint );
	}
	

	/**
	 * Default Option Initialization
	 *
	 * Adheres to standard update_option logic to avoid overwriting existing user data.
	 */
	public function activate_plugin_defaults() {
		$option_name = "{$this->plugin_slug}_options";
		if ( false === get_option( $option_name ) ) {
			update_option( $option_name, array(
				'enabled'       => 1,
				'target_format' => 'sanitize',
			) );
		}
	}

	/**
	 * Admin Menu Entry
	 *
	 * Hooks into the WordPress Tools menu.
	 */
	public function add_admin_menu() {
		add_management_page(
			__( 'SVG Support Settings', 'svg-support-thisismyurl' ),
			__( 'SVG Support', 'svg-support-thisismyurl' ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'render_settings_page' )
		);
	}
	/**
	 * Injects WebP-specific buttons into the Core sidebar.
	 */
	public function add_bulk_action_buttons( $current_slug ) {
		// Only show these buttons on the WebP settings page.
		if ( $current_slug !== $this->plugin_slug ) {
			return;
		}

	}
	/**
	 * Expand MIME Support
	 *
	 * Modifies the allowed MIME types to permit SVG and compressed SVG uploads.
	 *
	 * @param array $mimes Existing allowed MIME types.
	 * @return array Filtered MIME types.
	 */
	public function add_svg_mime_types( $mimes ) {
		if ( 1 === (int) $this->get_plugin_option( 'enabled', 1 ) ) {
			$mimes['svg']  = 'image/svg+xml';
			$mimes['svgz'] = 'image/svg+xml';
		}
		return $mimes;
	}
}

/**
 * Initialize the SVG support plugin.
 */
new TIMU_SVG_Support();
