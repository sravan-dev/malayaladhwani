<?php
/**
 * WordPress Database Drop-in: MySQL / SQLite Toggle
 *
 * Controlled by DB_ENGINE constant in wp-config.php:
 *   define('DB_ENGINE', 'sqlite');  // Use SQLite
 *   define('DB_ENGINE', 'mysql');   // Use MySQL (default)
 *
 * SQLite database file: wp-content/database/.ht.sqlite
 */

// Only activate SQLite when explicitly set
if ( ! defined( 'DB_ENGINE' ) || 'sqlite' !== DB_ENGINE ) {
	// MySQL mode: return without doing anything.
	// WordPress will use its default wpdb class.
	return;
}

// --- SQLite mode below ---

define( 'SQLITE_DB_DROPIN_VERSION', '1.8.0' );

// Locate the SQLite integration plugin folder
$sqlite_plugin_folder = realpath( __DIR__ . '/plugins/sqlite-database-integration' );

if ( ! $sqlite_plugin_folder || ! file_exists( $sqlite_plugin_folder . '/wp-includes/sqlite/db.php' ) ) {
	// Plugin not found — show error and stop
	header( 'HTTP/1.1 500 Internal Server Error' );
	echo '<h1>SQLite Integration Error</h1>';
	echo '<p>The SQLite Database Integration plugin is not installed.</p>';
	echo '<p>Expected at: <code>' . esc_html( __DIR__ . '/plugins/sqlite-database-integration' ) . '</code></p>';
	echo '<p>To use MySQL instead, set <code>define(\'DB_ENGINE\', \'mysql\');</code> in wp-config.php</p>';
	die();
}

// Set the SQLite database file location
if ( ! defined( 'DB_DIR' ) ) {
	define( 'DB_DIR', __DIR__ . '/database/' );
}
if ( ! defined( 'DB_FILE' ) ) {
	define( 'DB_FILE', '.ht.sqlite' );
}

// Define DATABASE_TYPE for backward compatibility
if ( ! defined( 'DATABASE_TYPE' ) ) {
	define( 'DATABASE_TYPE', 'sqlite' );
}

// Load the SQLite implementation
require_once $sqlite_plugin_folder . '/wp-includes/sqlite/db.php';

// Auto-activate the plugin if not already active
add_action(
	'admin_footer',
	function () {
		if ( ! function_exists( 'is_plugin_inactive' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( function_exists( 'is_plugin_inactive' ) && is_plugin_inactive( 'sqlite-database-integration/load.php' ) ) {
			activate_plugin( 'sqlite-database-integration/load.php', '', false, true );
		}
	}
);
