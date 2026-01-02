<?php
/**
 * Author:              Christopher Ross
 * Author URI:          https://thisismyurl.com/?source=thisismyurl-svg-support
 * Plugin Name:         SVG Support by thisismyurl.com
 * Plugin URI:          https://thisismyurl.com/thisismyurl-svg-support/?source=thisismyurl-svg-support
 * Donate link:         https://thisismyurl.com/donate/?source=thisismyurl-svg-support
 * * Description:         Safely enable SVG uploads with deep XML/XSS sanitization.
 * Version:             1.260101
 * Requires PHP:        7.4
 * Text Domain:         thisismyurl-svg-support
 * * @package TIMU_SVG_Support
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

    /**
     * Constructor: Initializes Core and SVG specific hooks.
     * Passes 'tools.php' as the 5th argument to determine admin routing.
     */
    public function __construct() {
        parent::__construct( 
            'thisismyurl-svg-support', 
            plugin_dir_url( __FILE__ ), 
            'timu_svg_settings_group', 
            '', 
            'tools.php' 
        );

        add_filter( 'upload_mimes', array( $this, 'add_svg_mime_types' ) );
        add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_svg_upload' ) ); // Security Hook
        add_action( 'admin_head',   array( $this, 'fix_svg_media_library_display' ) );
        add_action( 'admin_menu',   array( $this, 'add_admin_menu' ) );

        register_activation_hook( __FILE__, array( $this, 'activate_plugin_defaults' ) );
    }

    public function activate_plugin_defaults() {
        $option_name = $this->plugin_slug . '_options';
        if ( false === get_option( $option_name ) ) {
            update_option( $option_name, array( 
                'enabled'    => 1,
                'sanitize'   => 1 // New default for security
            ) );
        }
    }

    /**
     * Deep Sanitization Routine
     * Strips scripts, comments, and dangerous event handlers.
     */
    public function sanitize_svg_upload( $file ) {
        if ( 'image/svg+xml' !== $file['type'] || ! $this->get_plugin_option( 'sanitize', 1 ) ) {
            return $file;
        }

        $file_path = $file['tmp_name'];
        $fs = $this->init_fs();
        $svg_content = $fs->get_contents( $file_path );

        if ( ! $svg_content ) return $file;

        // 1. Strip PHP tags
        $svg_content = preg_replace( '/<\?php.*?\?>/is', '', $svg_content );

        // 2. Strip Script tags and their contents
        $svg_content = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $svg_content );

        // 3. Use the jailbreak logic pattern to strip 'on' event handlers (onclick, onload, etc.)
        // Derived from the Link Support jailbreak_sanitizer logic
        $svg_content = preg_replace( '/\son\w+=(["\'])(.*?)\1/i', '', $svg_content );

        // 4. Strip potentially malicious href="javascript:..."
        $svg_content = preg_replace( '/href=(["\'])javascript:(.*?)\1/i', 'href="#"', $svg_content );

        // Save sanitized content back to the temporary file
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
            array( $this, 'render_ui' )
        );
    }

    public function render_ui() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $enabled  = $this->get_plugin_option( 'enabled', 1 ); 
        $sanitize = $this->get_plugin_option( 'sanitize', 1 ); 
        ?>
        <div class="wrap timu-admin-wrap">
            <?php $this->render_core_header(); ?>

            <form method="post" action="options.php">
                <?php settings_fields( $this->options_group ); ?>
                
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content">
                            <div class="timu-card">
                                <div class="timu-card-header"><?php esc_html_e( 'SVG Configuration', 'thisismyurl-svg-support' ); ?></div>
                                <div class="timu-card-body">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Enable SVG Uploads', 'thisismyurl-svg-support' ); ?></th>
                                            <td>
                                                <label class="timu-switch">
                                                    <input type="checkbox" name="<?php echo esc_attr($this->plugin_slug); ?>_options[enabled]" value="1" <?php checked( 1, $enabled ); ?> />
                                                    <span class="timu-slider"></span>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Sanitize on Upload', 'thisismyurl-svg-support' ); ?></th>
                                            <td>
                                                <label class="timu-switch">
                                                    <input type="checkbox" name="<?php echo esc_attr($this->plugin_slug); ?>_options[sanitize]" value="1" <?php checked( 1, $sanitize ); ?> />
                                                    <span class="timu-slider"></span>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'Removes potentially malicious scripts and code from SVG files.', 'thisismyurl-svg-support' ); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <?php $this->render_registration_field(); ?>
                            <?php submit_button( __( 'Save SVG Settings', 'thisismyurl-svg-support' ), 'primary large' ); ?>
                        </div>
                        <?php $this->render_core_sidebar(); ?>
                    </div>
                </div>
            </form>
            <?php $this->render_core_footer(); ?>
        </div>
        <?php
    }
}
new TIMU_SVG_Support();