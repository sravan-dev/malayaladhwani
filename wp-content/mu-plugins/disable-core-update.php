<?php
/**
 * Plugin Name: Disable Core Update Notices
 * Description: Hide the "WordPress X.X is available! Please update now." nag and disable core auto-updates.
 * Version: 1.0.0
 * Author: Sravan M
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Remove the yellow update nag bar at the top of wp-admin (single + multisite).
add_action( 'admin_init', function () {
    remove_action( 'admin_notices', 'update_nag', 3 );
    remove_action( 'network_admin_notices', 'update_nag', 3 );
} );

// Stop WP from checking / reporting a new core version.
add_filter( 'pre_site_transient_update_core', 'mad_fake_core_update_response' );
add_filter( 'pre_transient_update_core',      'mad_fake_core_update_response' );
function mad_fake_core_update_response() {
    global $wp_version;
    return (object) array(
        'last_checked'    => time(),
        'version_checked' => $wp_version,
        'updates'         => array(),
        'translations'    => array(),
    );
}

// Hide the "Updates" submenu badge count + dashboard "At a Glance" version nag.
add_filter( 'wp_get_update_data', function ( $update_data ) {
    if ( isset( $update_data['counts']['wordpress'] ) ) {
        $update_data['counts']['wordpress'] = 0;
        $update_data['counts']['total']     = max( 0, ( $update_data['counts']['total'] ?? 0 ) - 1 );
    }
    return $update_data;
} );

// Disable all automatic core updates.
add_filter( 'automatic_updater_disabled',                '__return_true' );
add_filter( 'allow_minor_auto_core_updates',             '__return_false' );
add_filter( 'allow_major_auto_core_updates',             '__return_false' );
add_filter( 'allow_dev_auto_core_updates',               '__return_false' );
add_filter( 'auto_update_core',                          '__return_false' );
add_filter( 'wp_auto_update_core',                       '__return_false' );

// Stop the scheduled version check cron from running.
add_action( 'init', function () {
    remove_action( 'wp_version_check',       'wp_version_check' );
    remove_action( 'admin_init',             '_maybe_update_core' );
    remove_action( 'wp_maybe_auto_update',   'wp_maybe_auto_update' );
    wp_clear_scheduled_hook( 'wp_version_check' );
} );

// Belt-and-braces CSS hide in case any notice still slips through.
add_action( 'admin_head', function () {
    echo '<style>.update-nag, #wp-admin-bar-updates, .wp-core-ui .notice-warning.update-message { display: none !important; }</style>';
} );
