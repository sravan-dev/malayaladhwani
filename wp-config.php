<?php



/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// =============================================================================
// DATABASE ENGINE SELECTOR
// =============================================================================
// Set to 'mysql'  → uses MySQL (XAMPP/MariaDB) — current active database
// Set to 'sqlite' → uses SQLite (wp-content/database/.ht.sqlite)
//
// To switch to SQLite:
//   1. Change 'mysql' to 'sqlite' below
//   2. Run the migration: http://localhost/malayaladhwani/wp-content/sqlite-migrate.php
//   3. Delete sqlite-migrate.php after migration is complete
// =============================================================================
define( 'DB_ENGINE', 'sqlite' ); // ← Change to 'sqlite' AFTER running migration

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'malayaladhwani' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'nHj~cWYB7GBTFItn#arkuNf)%Z%2oc]z%!ZC~%ZA;VZOD@.][1w#cSt<(&Hruc|:' );
define( 'SECURE_AUTH_KEY',  '`i0r_djS-vemoY9qS>vny[u;R3pRRGJn/eTo45`By%1R@arEe<m.0omW_yO/p,4p' );
define( 'LOGGED_IN_KEY',    '~{}W<f |X`#lpm&& Qd{oS3[?apWRlqD{cpZ>{)+lH}b.x{#z6 |krEZW3Oa(*J,' );
define( 'NONCE_KEY',        '8ipK?@4?h]Ow~-iJT/3`T=z7uIo}4t=ba_K3`i;.Hg|sp< k,5VEj[yCuYG-#Q9*' );
define( 'AUTH_SALT',        'Kr$Bpl4E$pc*gN<.0^!aO/D$ZO!&FlqX.A L/?9hGB%//a!>Xk:,MA3W&BnF+I(C' );
define( 'SECURE_AUTH_SALT', 'r0o2;G-jymCYM!3IN4gGjC}@(wyq8V,lTtM73SM7]v]VJNL& +xyh~sK-G(?pdfM' );
define( 'LOGGED_IN_SALT',   '9g? 5&ma?[q9z@_If|,T?s}yk#baDrl_OkL+[9y5Fz|5[9/6*X % +xsWT7S#Ivu' );
define( 'NONCE_SALT',       'lL9xbtNo#F,kH:;Q4{$-%d]wY9Dh`V$m.yW+ 2FF-lM KScD<vct[; _HybTX<mw' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', true );

/* Add any custom values between this line and the "stop editing" line. */
if ( ! defined( 'WP_HOME' ) && ! empty( $_SERVER['HTTP_HOST'] ) ) {
	$codex_scheme = 'http';

	if (
		( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ||
		( isset( $_SERVER['SERVER_PORT'] ) && '443' === (string) $_SERVER['SERVER_PORT'] )
	) {
		$codex_scheme = 'https';
	}

	$codex_script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? str_replace( '\\', '/', $_SERVER['SCRIPT_NAME'] ) : '';
	$codex_base_path   = '';

	if ( '' !== $codex_script_name ) {
		$codex_base_path = dirname( $codex_script_name );

		if ( '.' === $codex_base_path ) {
			$codex_base_path = '';
		}

		$codex_base_path = (string) preg_replace( '#/(wp-admin|wp-content|wp-includes)(/.*)?$#', '', $codex_base_path );
		$codex_base_path = '/' === $codex_base_path ? '' : '/' . trim( $codex_base_path, '/' );
	}

	define( 'WP_HOME', $codex_scheme . '://' . $_SERVER['HTTP_HOST'] . $codex_base_path );
	define( 'WP_SITEURL', WP_HOME );
}

define( 'DISALLOW_FILE_EDIT', true );
//define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
set_time_limit(300);

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
