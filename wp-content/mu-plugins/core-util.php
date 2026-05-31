<?php
/**
 * Plugin Name: Core Util
 * Description: Increases PHP limits for WordPress where the server allows runtime changes.
 * Version: 1.0.0
 * Author: Vendomark
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PHP Execution Time
 * Recommended: 60 or more
 */
if (function_exists('ini_set')) {
    @ini_set('max_execution_time', '60');
}

if (function_exists('set_time_limit')) {
    @set_time_limit(60);
}

/**
 * PHP Max Input Vars
 * Recommended: 2000 or more
 *
 * Note:
 * Many servers do NOT allow max_input_vars to be changed from WordPress/plugin code.
 * If it still shows 1000, add the .user.ini code given below.
 */
if (function_exists('ini_set')) {
    @ini_set('max_input_vars', '2000');
}

/**
 * Extra recommended PHP values
 */
if (function_exists('ini_set')) {
    @ini_set('memory_limit', '256M');
    @ini_set('post_max_size', '64M');
    @ini_set('upload_max_filesize', '64M');
    @ini_set('max_input_time', '120');
}

/**
 * WordPress memory constants.
 * Define only if not already defined in wp-config.php.
 */
if (!defined('WP_MEMORY_LIMIT')) {
    define('WP_MEMORY_LIMIT', '256M');
}

if (!defined('WP_MAX_MEMORY_LIMIT')) {
    define('WP_MAX_MEMORY_LIMIT', '512M');
}