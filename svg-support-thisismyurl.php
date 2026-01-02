<?php
/**
 * TIMU SVG Support Plugin
 *
 * This plugin facilitates the secure handling of Scalable Vector Graphics (SVG) within
 * the WordPress Media Library. It provides dual-layer protection via XML sanitization
 * and optional rasterization into WebP or AVIF formats through the TIMU Shared Core.
 *
 * @package    TIMU_SVG_Support
 * @author     Christopher Ross <https://thisismyurl.com/>
 * @version    1.260102
 * @license    GPL-2.0+
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
			'thisismyurl-svg-support',      // Unique plugin slug.
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
		add_action( 'admin_head', array( $this, 'fix_svg_media_library_display' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		/**
		 * Activation: Register defaults only once upon plugin activation.
		 */
		register_activation_hook( __FILE__, array( $this, 'activate_plugin_defaults' ) );
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

		/**
		 * Dynamically build the radio options based on the presence of siblings.
		 */
		$format_options = array(
			'asis'     => __( 'Upload as unsafe .svg', 'thisismyurl-svg-support' ),
			'sanitize' => __( 'Sanitize XML for safe .svg', 'thisismyurl-svg-support' ),
		);

		if ( $webp_active ) {
			$format_options['webp'] = __( 'Convert to .webp file format.', 'thisismyurl-svg-support' );
		}

		if ( $avif_active ) {
			$format_options['avif'] = __( 'Convert to .avif file format.', 'thisismyurl-svg-support' );
		}

		$blueprint = array(
			'config' => array(
				'title'  => __( 'SVG Configuration', 'thisismyurl-svg-support' ),
				'fields' => array(
					'enabled'       => array(
						'type'      => 'switch',
						'label'     => __( 'Enable SVG Uploads', 'thisismyurl-svg-support' ),
						'desc'      => __( 'Allows .svg files in the Media Library.', 'thisismyurl-svg-support' ),
						'is_parent' => true,
						'default'   => 1,
					),
					'target_format' => array(
						'type'      => 'radio',
						'label'     => __( 'SVG Handling Mode', 'thisismyurl-svg-support' ),
						'parent'    => 'enabled',
						'is_parent' => true,
						'options'   => $format_options,
						'default'   => 'sanitize',
						'desc'      => ( ! $webp_active || ! $avif_active )
									? __( 'Install <a href="https://thisismyurl.com/thisismyurl-webp-support/">WebP</a> or <a href="https://thisismyurl.com/thisismyurl-avif-support/">AVIF</a> plugins for more options.', 'thisismyurl-svg-support' )
									: __( 'Choose how to process .svg files upon upload.', 'thisismyurl-svg-support' ),
					),
					'webp_quality'  => array(
						'type'         => 'number',
						'label'        => __( 'WebP Quality', 'thisismyurl-svg-support' ),
						'parent'       => 'target_format',
						'parent_value' => 'webp',
						'default'      => 80,
					),
					'avif_quality'  => array(
						'type'         => 'number',
						'label'        => __( 'AVIF Quality', 'thisismyurl-svg-support' ),
						'parent'       => 'target_format',
						'parent_value' => 'avif',
						'default'      => 60,
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
			__( 'SVG Support Settings', 'thisismyurl-svg-support' ),
			__( 'SVG Support', 'thisismyurl-svg-support' ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'render_settings_page' )
		);
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

	/**
	 * XML/XSS Sanitization Routine
	 *
	 * Strips potentially dangerous elements such as PHP tags, <script> blocks, 
	 * and 'on*' event handlers to mitigate XSS risks in SVG vectors.
	 *
	 * @param array $file Standard WordPress file data.
	 * @return array Sanitized file data.
	 */
	private function sanitize_svg( $file ) {
		$file_path = $file['tmp_name'];
		$fs        = $this->init_fs();
		$content   = $fs->get_contents( $file_path );

		if ( empty( $content ) ) {
			return $file;
		}

		/**
		 * Pattern matching for malicious injections.
		 */
		$content = preg_replace( '/<\?php.*?\?>/is', '', $content );
		$content = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $content );
		$content = preg_replace( '/\son\w+=(["\'])(.*?)\1/i', '', $content );
		$content = preg_replace( '/href=(["\'])javascript:(.*?)\1/i', 'href="#"', $content );

		$fs->put_contents( $file_path, $content );

		return $file;
	}

	/**
	 * Admin UI Fix
	 *
	 * Injects CSS into the admin head to ensure SVG thumbnails scale correctly 
	 * within the Media Library grid and attachment details view.
	 */
	public function fix_svg_media_library_display() {
		?>
		<style id="timu-svg-support-admin-css">
			.thumbnail img[src$=".svg"], 
			[data-name="view-attachment"] .details img[src$=".svg"] {
				width: 100% !important;
				height: auto !important;
			}
		</style>
		<?php
	}

	/**
	 * Upload Traffic Controller
	 *
	 * Intercepts SVG uploads to determine if the file should be sanitized 
	 * as a vector or rasterized into WebP/AVIF via the Shared Core.
	 *
	 * @param array $file The temporary file data from $_FILES.
	 * @return array Processed file data.
	 */
	public function process_svg_upload( $file ) {
		/**
		 * Validation: Ensure the file is an SVG and the plugin is active.
		 */
		if ( 'image/svg+xml' !== $file['type'] || 1 !== (int) $this->get_plugin_option( 'enabled', 1 ) ) {
			return $file;
		}

		$mode = $this->get_plugin_option( 'target_format', 'sanitize' );

		/**
		 * Rasterization Path: Convert SVG to raster formats.
		 */
		if ( in_array( $mode, array( 'webp', 'avif' ), true ) ) {
			$quality = (int) $this->get_plugin_option( $mode . '_quality', 80 );
			
			/**
			 * The process_image_conversion method handles Imagick format 
			 * definitions and resource management centrally.
			 */
			return $this->process_image_conversion( $file, $mode, $quality );
		}

		/**
		 * Vector Path: Sanitize or leave as-is.
		 */
		return ( 'sanitize' === $mode ) ? $this->sanitize_svg( $file ) : $file;
	}
}

/**
 * Initialize the SVG support plugin.
 */
new TIMU_SVG_Support();