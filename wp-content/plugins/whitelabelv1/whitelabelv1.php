<?php
/**
 * Plugin Name: WhitelabelV1
 * Description: Hides selected admin menu and admin bar items for Editor users.
 * Version: 1.0.0
 * Author: Sravan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check whether the current logged-in user has the Editor role.
 *
 * @return bool
 */
function whitelabelv1_is_editor_user() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	$user = wp_get_current_user();

	return in_array( 'editor', (array) $user->roles, true );
}

/**
 * Add a top-level Magazines menu item for editor users.
 */
function whitelabelv1_add_editor_magazines_menu() {
	if ( ! whitelabelv1_is_editor_user() ) {
		return;
	}

	add_menu_page(
		'Magazines',
		'Magazines',
		'edit_posts',
		'edit.php?post_type=nma_magazine',
		'',
		'dashicons-book-alt',
		27
	);
}
add_action( 'admin_menu', 'whitelabelv1_add_editor_magazines_menu', 20 );

/**
 * Hide admin menu pages for editor users.
 */
function whitelabelv1_hide_editor_menu_pages() {
	if ( ! whitelabelv1_is_editor_user() ) {
		return;
	}

	remove_menu_page( 'tdb_cloud_templates' );
	remove_menu_page( 'profile.php' );
	remove_menu_page( 'tools.php' );
}
add_action( 'admin_menu', 'whitelabelv1_hide_editor_menu_pages', 999 );

/**
 * Ensure a visible Dashboard menu exists for editor users.
 */
function whitelabelv1_ensure_dashboard_menu() {
	if ( ! whitelabelv1_is_editor_user() ) {
		return;
	}

	// Remove any previous custom slug and re-add native Dashboard as first item.
	remove_menu_page( 'whitelabelv1-dashboard' );
	remove_menu_page( 'index.php' );

	add_menu_page(
		'Dashboard',
		'Dashboard',
		'read',
		'index.php',
		'',
		'dashicons-dashboard',
		2
	);
}
add_action( 'admin_menu', 'whitelabelv1_ensure_dashboard_menu', PHP_INT_MAX );

/**
 * Keep required menu items unhidden when user-menu-manager stores per-user hidden items.
 */
function whitelabelv1_unhide_required_items_from_user_meta() {
	if ( ! whitelabelv1_is_editor_user() ) {
		return;
	}

	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return;
	}

	$hidden_items = get_user_meta( $user_id, 'umm_hidden_items', true );

	if ( ! is_array( $hidden_items ) ) {
		return;
	}

	$updated_hidden_items = array_values(
		array_diff(
			$hidden_items,
			array( 'index.php', 'whitelabelv1-dashboard', 'nma-list-news', 'nma-add-news', 'nma-advertisement', 'edit.php?post_type=nma_magazine', 'post-new.php?post_type=nma_magazine' )
		)
	);

	if ( $updated_hidden_items !== $hidden_items ) {
		update_user_meta( $user_id, 'umm_hidden_items', $updated_hidden_items );
	}
}
add_action( 'admin_init', 'whitelabelv1_unhide_required_items_from_user_meta', 1 );

/**
 * Enable menu ordering filters for editor users.
 *
 * @param bool $enabled Existing custom order status.
 * @return bool
 */
function whitelabelv1_enable_editor_custom_menu_order( $enabled ) {
	if ( whitelabelv1_is_editor_user() ) {
		return true;
	}

	return $enabled;
}
add_filter( 'custom_menu_order', 'whitelabelv1_enable_editor_custom_menu_order', 999 );

/**
 * Put Dashboard first and News second in left admin menu for editor users.
 *
 * @param array|false $menu_order Menu order array.
 * @return array|false
 */
function whitelabelv1_editor_dashboard_first_menu_order( $menu_order ) {
	if ( ! whitelabelv1_is_editor_user() || ! is_array( $menu_order ) ) {
		return $menu_order;
	}

	$priority_slugs = array( 'index.php', 'nma-list-news' );
	$ordered_prefix = array();

	foreach ( $priority_slugs as $slug ) {
		$menu_position = array_search( $slug, $menu_order, true );

		if ( false !== $menu_position ) {
			unset( $menu_order[ $menu_position ] );
			$ordered_prefix[] = $slug;
		}
	}

	return array_values( array_merge( $ordered_prefix, $menu_order ) );
}
add_filter( 'menu_order', 'whitelabelv1_editor_dashboard_first_menu_order', 999 );

/**
 * Hide the "Collapse Menu" control and "Howdy" text for editor users.
 */
function whitelabelv1_hide_collapse_menu() {
	if ( ! whitelabelv1_is_editor_user() ) {
		return;
	}

	echo '<style>#collapse-menu,#collapse-button{display:none !important;}#adminmenu #menu-dashboard,#adminmenu #toplevel_page_nma-list-news{display:block !important;visibility:visible !important;}#wpadminbar #wp-admin-bar-my-account .ab-label,#wpadminbar #wp-admin-bar-my-account .display-name{display:none !important;}</style>';
}
add_action( 'admin_head', 'whitelabelv1_hide_collapse_menu' );

/**
 * Prevent direct access to hidden admin pages.
 */
function whitelabelv1_block_hidden_pages() {
	if ( ! whitelabelv1_is_editor_user() ) {
		return;
	}

	global $pagenow;

	$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$is_hidden_page = 'profile.php' === $pagenow
		|| 'tools.php' === $pagenow
		|| 'tdb_cloud_templates' === $current_page;

	if ( $is_hidden_page ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
add_action( 'admin_init', 'whitelabelv1_block_hidden_pages' );

/**
 * Keep only a top-right logout link in the admin bar for editor users.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
 */
function whitelabelv1_editor_admin_bar_logout_only( $wp_admin_bar ) {
	if ( ! whitelabelv1_is_editor_user() || ! is_admin_bar_showing() ) {
		return;
	}

	$remove_ids = array(
		'search',
		'my-account',
		'user-actions',
		'edit-profile',
		'logout',
		'my-sites',
		'my-sites-super-admin',
		'my-sites-list',
	);

	foreach ( $remove_ids as $node_id ) {
		$wp_admin_bar->remove_node( $node_id );
	}

	$nodes = $wp_admin_bar->get_nodes();

	if ( is_array( $nodes ) ) {
		foreach ( $nodes as $node ) {
			$node_title = wp_strip_all_tags( (string) $node->title );

			if ( 'top-secondary' === $node->parent || false !== stripos( $node_title, 'howdy' ) ) {
				$wp_admin_bar->remove_node( $node->id );
			}
		}
	}

	$wp_admin_bar->add_node(
		array(
			'id'     => 'whitelabelv1-logout',
			'parent' => 'top-secondary',
			'title'  => 'Log Out',
			'href'   => wp_logout_url( admin_url() ),
		)
	);
}
add_action( 'admin_bar_menu', 'whitelabelv1_editor_admin_bar_logout_only', PHP_INT_MAX );
