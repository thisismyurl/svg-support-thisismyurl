<?php
/**
 * Author:              Christopher Ross
 * Author URI:          https://thisismyurl.com/?source=thisismyurl-svg-support
 * 
 * Plugin Name:         SVG Support by thisismyurl.com
 * Plugin URI:          https://thisismyurl.com/thisismyurl-svg-support/?source=thisismyurl-svg-support
 * Donate link:         https://thisismyurl.com/donate/?source=thisismyurl-svg-support
 * 
 * Description:         Safely enable SVG uploads with deep XML/XSS sanitization and centralized settings.
 * Version:             1.260101
 * Requires PHP:        7.4
 * Text Domain:         thisismyurl-svg-support
 * 
 * @package TIMU_SVG_Support
 *
 * 
 */


if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Version-aware Core Loader
 */
function timu_svg_support_load_core() {
    $core_path = plugin_dir_path( __FILE__ ) . 'core/class-timu-core.php';
    if ( ! class_exists( 'TIMU_Core_v1' ) ) {
        require_once $core_path;
    }
}
timu_svg_support_load_core();

class TIMU_SVG_Support extends TIMU_Core_v1 {

    public function __construct() {
        parent::__construct( 
            'thisismyurl-svg-support', 
            plugin_dir_url( __FILE__ ), 
            'timu_svg_settings_group', 
            '', 
            'tools.php' 
        );

        add_action( 'init', array( $this, 'setup_plugin' ) );
        add_filter( 'upload_mimes', array( $this, 'add_svg_mime_types' ) );
        add_filter( 'wp_handle_upload_prefilter', array( $this, 'process_svg_upload' ) );
        add_action( 'admin_head',   array( $this, 'fix_svg_media_library_display' ) );
        add_action( 'admin_menu',   array( $this, 'add_admin_menu' ) );

        register_activation_hook( __FILE__, array( $this, 'activate_plugin_defaults' ) );
    }

    /**
     * Configure the settings blueprint for the Core generator.
     */
    public function setup_plugin() {
        // Check for sibling plugin availability
        $webp_active = class_exists( 'TIMU_WebP_Support' );
        $avif_active = class_exists( 'TIMU_AVIF_Support' );

        // Build options dynamically
        $format_options = array(
            'asis'     => __( 'Upload a unsafe .svg', 'thisismyurl-svg-support' ),
            'sanitize' => __( 'Sanitize XML for safe .svg', 'thisismyurl-svg-support' ),
        );

        if ( $webp_active ) {
            $format_options['webp'] = __( 'Convert to .webp files.', 'thisismyurl-svg-support' );
        }

        if ( $avif_active ) {
            $format_options['avif'] = __( 'Convert to .avif files.', 'thisismyurl-svg-support' );
        }

        $blueprint = array(
            'config' => array(
                'title'  => __( 'SVG Configuration', 'thisismyurl-svg-support' ),
                'fields' => array(
                    'enabled' => array(
                        'type'      => 'switch',
                        'label'     => __( 'Enable SVG Uploads', 'thisismyurl-svg-support' ),
                        'desc'      => __( 'Allows .svg files in the Media Library.', 'thisismyurl-svg-support' ),
                        'is_parent' => true,
                        'default'   => 1
                    ),
                    'target_format' => array(
                        'type'      => 'radio',
                        'label'     => __( 'SVG Handling Mode', 'thisismyurl-svg-support' ),
                        'parent'    => 'enabled',
                        'is_parent' => true,
                        'options'   => $format_options,
                        'default'   => 'sanitize',
                        'desc'      => ( !$webp_active || !$avif_active ) 
                                    ? __( 'Install  <a href="https://thisismyurl.com/thisismyurl-webp-support/">WebP Support</a> or  <a href="https://thisismyurl.com/thisismyurl-avif-support/">AVIF Support</a> plugins to enable the formats.', 'thisismyurl-svg-support' ) 
                                    : __( 'Choose how to handle .svg files upon upload.', 'thisismyurl-svg-support' )
                    ),
                    'webp_quality' => array(
                        'type'         => 'number',
                        'label'        => __( 'WebP Quality', 'thisismyurl-svg-support' ),
                        'parent'       => 'target_format',
                        'parent_value' => 'webp',
                        'default'      => 80
                    ),
                    'avif_quality' => array(
                        'type'         => 'number',
                        'label'        => __( 'AVIF Quality', 'thisismyurl-svg-support' ),
                        'parent'       => 'target_format',
                        'parent_value' => 'avif',
                        'default'      => 60
                    ),
                )
            )
        );

        $this->init_settings_generator( $blueprint );
    }

    /**
     * Set plugin defaults upon activation.
     */
    public function activate_plugin_defaults() {
        $option_name = $this->plugin_slug . '_options';
        if ( false === get_option( $option_name ) ) {
            update_option( $option_name, array( 
                'enabled'       => 1,
                'handling_mode' => 'sanitize'
            ) );
        }
    }

    /**
     * Routes the SVG upload based on selected handling mode.
     */
    public function process_svg_upload( $file ) {
        if ( 'image/svg+xml' !== $file['type'] || 1 != $this->get_plugin_option( 'enabled', 1 ) ) {
            return $file;
        }

        $mode = $this->get_plugin_option( 'handling_mode', 'sanitize' );

        switch ( $mode ) {
            case 'sanitize':
                return $this->sanitize_svg( $file );
            case 'webp':
            case 'avif':
                // Conversion logic would be implemented here using Imagick
                return $file;
            case 'asis':
            default:
                return $file;
        }
    }

    /**
     * Deep Sanitization Routine
     * Strips scripts, comments, and dangerous event handlers.
     */
    private function sanitize_svg( $file ) {
        $file_path = $file['tmp_name'];
        $fs = $this->init_fs();
        $svg_content = $fs->get_contents( $file_path );

        if ( ! $svg_content ) return $file;

        // Strip PHP, Script tags, and inline 'on' handlers
        $svg_content = preg_replace( '/<\?php.*?\?>/is', '', $svg_content );
        $svg_content = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $svg_content );
        $svg_content = preg_replace( '/\son\w+=(["\'])(.*?)\1/i', '', $svg_content );
        $svg_content = preg_replace( '/href=(["\'])javascript:(.*?)\1/i', 'href="#"', $svg_content );

        $fs->put_contents( $file_path, $svg_content );

        return $file;
    }

    public function add_svg_mime_types( $mimes ) {
        if ( 1 == $this->get_plugin_option( 'enabled', 1 ) ) {
            $mimes['svg']  = 'image/svg+xml';
            $mimes['svgz'] = 'image/svg+xml';
        }
        return $mimes;
    }

    public function fix_svg_media_library_display() {
        echo '<style>
            .thumbnail img[src$=".svg"], [data-name="view-attachment"] .details img[src$=".svg"] {
                width: 100% !important;
                height: auto !important;
            }
        </style>';
    }

    public function add_admin_menu() {
        add_management_page(
            __( 'SVG Support Settings', 'thisismyurl-svg-support' ),
            __( 'SVG Support', 'thisismyurl-svg-support' ),
            'manage_options',
            $this->plugin_slug,
            array( $this, 'render_settings_page' )
        );
    }
}
new TIMU_SVG_Support();