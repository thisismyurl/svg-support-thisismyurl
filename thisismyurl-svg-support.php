<?php
/**
 * Author:              Christopher Ross
 * Author URI:          https://thisismyurl.com/?source=thisismyurl-svg-support
 * Plugin Name:         SVG Support by thisismyurl.com
 * Plugin URI:          https://thisismyurl.com/thisismyurl-svg-support/?source=thisismyurl-svg-support
 * Donate link:         https://thisismyurl.com/donate/?source=thisismyurl-svg-support
 * 
 * Description:         Safely enable SVG uploads and management in the WordPress Media Library.
 * Tags:                svg, uploads, media library
 * 
 * Version:             1.260101
 * Requires at least:   5.3
 * Requires PHP:        7.7.0
 * 
 * Update URI:          https://github.com/thisismyurl/thisismyurl-svg-support
 * GitHub Plugin URI:   https://github.com/thisismyurl/thisismyurl-svg-support
 * Primary Branch:      main
 * Text Domain:         thisismyurl-svg-support
 * 
 * License:             GPL2
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * 
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
            'tools.php' // New: Routes the Core action links to Tools
        );

        add_filter( 'upload_mimes', array( $this, 'add_svg_mime_types' ) );
        add_action( 'admin_head',   array( $this, 'fix_svg_media_library_display' ) );
        add_action( 'admin_menu',   array( $this, 'add_admin_menu' ) );

        register_activation_hook( __FILE__, array( $this, 'activate_plugin_defaults' ) );
    }

    /**
     * Activate Plugin Defaults:
     * Uses namespaced Core option pattern.
     */
    public function activate_plugin_defaults() {
        $option_name = $this->plugin_slug . '_options';
        if ( false === get_option( $option_name ) ) {
            update_option( $option_name, array( 'enabled' => 1 ) );
        }
    }

    /**
     * Filters allowed mime types to include SVG.
     * Uses Core helper get_plugin_option() for cleaner code.
     */
    public function add_svg_mime_types( $mimes ) {
        if ( 1 == $this->get_plugin_option( 'enabled', 1 ) ) {
            $mimes['svg']  = 'image/svg+xml';
            $mimes['svgz'] = 'image/svg+xml';
        }
        return $mimes;
    }

    /**
     * Fixes SVG rendering in the Media Library.
     */
    public function fix_svg_media_library_display() {
        echo '<style>
            .thumbnail img[src$=".svg"], [data-name="view-attachment"] .details img[src$=".svg"] {
                width: 100% !important;
                height: auto !important;
            }
        </style>';
    }

    /**
     * Adds the menu page under Tools.
     * Consistent with $this->menu_parent set in constructor.
     */
    public function add_admin_menu() {
        add_management_page(
            __( 'SVG Support Settings', 'thisismyurl-svg-support' ),
            __( 'SVG Support', 'thisismyurl-svg-support' ),
            'manage_options',
            $this->plugin_slug,
            array( $this, 'render_ui' )
        );
    }

    /**
     * Renders the UI utilizing standardized Core components.
     */
    public function render_ui() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $sidebar_extra = '';
        $current_val = $this->get_plugin_option( 'enabled', 1 ); 
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
                                                    <input type="checkbox" name="<?php echo esc_attr($this->plugin_slug); ?>_options[enabled]" value="1" <?php checked( 1, $current_val ); ?> />
                                                    <span class="timu-slider"></span>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'Allow .svg files to be uploaded to the Media Library.', 'thisismyurl-svg-support' ); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <?php $this->render_registration_field(); ?>
                            <?php submit_button( __( 'Save SVG Settings', 'thisismyurl-svg-support' ), 'primary large' ); ?>
                        </div>

                        <?php $this->render_core_sidebar( $sidebar_extra ); ?>

                    </div>
                </div>
            </form>
            
            <?php $this->render_core_footer(); ?>
        </div>
        <?php
    }
}

new TIMU_SVG_Support();