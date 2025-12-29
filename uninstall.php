<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( strpos( WP_UNINSTALL_PLUGIN, 'thisismyurl-svg-support' ) !== false ) {
    delete_option( 'timu_svg_options' );
}