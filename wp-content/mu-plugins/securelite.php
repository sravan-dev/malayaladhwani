<?php
/**
 * Plugin Name: Securelite
 * Description: A lightweight security plugin to prevent XSS, SQL injection, and block brute-force attempts targeting unknown usernames.
 * Version: 1.0.0
 * Author: Sravan M
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Securelite {

    private $max_failed_attempts = 3;
    private $lockout_duration = 900; // 15 minutes in seconds
    private $login_slug = 'login';

    public function __construct() {
        // 1. Basic Firewall
        $this->run_firewall();

        // 2. Security Headers
        add_action( 'send_headers', array( $this, 'add_security_headers' ) );

        // 3. Unknown Username Lockout
        add_filter( 'authenticate', array( $this, 'check_ip_lockout' ), 10, 3 );
        add_action( 'wp_login_failed', array( $this, 'record_failed_login' ) );

        // 4. Custom Login URL
        add_action( 'init', array( $this, 'custom_login_routing' ), 1 );
        add_filter( 'site_url', array( $this, 'rewrite_login_urls' ), 10, 4 );
        add_filter( 'network_site_url', array( $this, 'rewrite_login_urls' ), 10, 3 );
        add_filter( 'wp_redirect', array( $this, 'intercept_login_redirects' ), 10, 2 );

        // 5. Admin Dashboard
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    /**
     * Inspect incoming requests for common malicious patterns
     */
    private function run_firewall() {
        $bad_patterns = array(
            '/(?:<script.*?>.*?<\/script>)/is', // Basic XSS script tags
            '/(?:javascript\s*:)/i', // JS pseudo-protocol
            '/(?:vbscript\s*:)/i', // VBScript pseudo-protocol
            '/(?:onload|onerror|onmouseover|onfocus|onblur)\s*=/i', // Common event handlers
            '/(?:union\s+select)/i', // SQLi
            '/(?:base64_decode\s*\()/i', // Malicious PHP execution
            '/(?:\.\.\/)+/' // Directory traversal
        );

        $request_data = array_merge( $_GET, $_POST, $_COOKIE );

        foreach ( $request_data as $key => $value ) {
            if ( is_string( $value ) ) {
                foreach ( $bad_patterns as $pattern ) {
                    if ( preg_match( $pattern, $value ) ) {
                        // Record the block
                        $blocks = get_option( 'securelite_firewall_blocks', 0 );
                        update_option( 'securelite_firewall_blocks', $blocks + 1 );
                        
                        // Immediately block the request
                        header( 'HTTP/1.1 403 Forbidden' );
                        die( 'Securelite Firewall: Malicious request blocked.' );
                    }
                }
            }
        }
    }

    /**
     * Add security headers to the HTTP response
     */
    public function add_security_headers() {
        if ( ! headers_sent() ) {
            header( 'X-XSS-Protection: 1; mode=block' );
            header( 'X-Content-Type-Options: nosniff' );
            header( 'X-Frame-Options: SAMEORIGIN' );
            header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
        }
    }

    /**
     * Get the real IP address of the user
     */
    private function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'];
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
        }
        return trim( $ip );
    }

    /**
     * Check if the current IP is locked out before allowing authentication
     */
    public function check_ip_lockout( $user, $username, $password ) {
        $ip = $this->get_client_ip();
        $transient_name = 'sl_lockout_' . md5( $ip );
        $attempts = get_transient( $transient_name );

        if ( $attempts !== false && $attempts >= $this->max_failed_attempts ) {
            // Block the authentication attempt
            return new WP_Error(
                'securelite_locked_out',
                '<strong>ERROR</strong>: Your IP address has been temporarily locked out due to too many failed login attempts with unknown usernames. Please try again later.'
            );
        }

        return $user;
    }

    /**
     * Record a failed login attempt if the username does NOT exist
     */
    public function record_failed_login( $username ) {
        // We only care if the username DOES NOT exist in the database.
        // This stops bots trying to guess 'admin', 'administrator', etc.
        if ( ! username_exists( $username ) ) {
            $ip = $this->get_client_ip();
            $transient_name = 'sl_lockout_' . md5( $ip );
            
            $attempts = get_transient( $transient_name );
            if ( $attempts === false ) {
                $attempts = 0;
            }
            
            $attempts++;
            
            // Set/update the transient with the lockout duration
            set_transient( $transient_name, $attempts, $this->lockout_duration );
        }
    }

    /**
     * Add Securelite to the Settings menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Securelite Dashboard',
            'Securelite',
            'manage_options',
            'securelite-dashboard',
            array( $this, 'render_dashboard' )
        );
    }

    /**
     * Render the Securelite Dashboard UI
     */
    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        
        $firewall_blocks = get_option( 'securelite_firewall_blocks', 0 );
        
        global $wpdb;
        $lockouts = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_sl_lockout_%'" );
        $locked_ips_count = count( $lockouts );

        echo '<div class="wrap">';
        echo '<h1>Securelite Dashboard</h1>';
        echo '<div style="display: flex; gap: 20px; margin-top: 20px;">';
        
        // Card 1: Firewall Blocks
        echo '<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); width: 300px; text-align: center;">';
        echo '<h3>Total Firewall Blocks</h3>';
        echo '<p style="font-size: 36px; font-weight: bold; color: #d63638; margin: 10px 0;">' . esc_html( $firewall_blocks ) . '</p>';
        echo '<p style="color: #646970;">Malicious requests blocked</p>';
        echo '</div>';

        // Card 2: Active Lockouts
        echo '<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); width: 300px; text-align: center;">';
        echo '<h3>Active IP Lockouts</h3>';
        echo '<p style="font-size: 36px; font-weight: bold; color: #f56e28; margin: 10px 0;">' . esc_html( $locked_ips_count ) . '</p>';
        echo '<p style="color: #646970;">IPs currently blocked from login</p>';
        echo '</div>';

        echo '</div>';
        
        // Active Lockouts Table
        if ( $locked_ips_count > 0 ) {
            echo '<h2 style="margin-top: 30px;">Currently Locked IPs (Hashed)</h2>';
            echo '<table class="wp-list-table widefat fixed striped" style="max-width: 620px;">';
            echo '<thead><tr><th>IP Hash</th><th>Failed Attempts</th></tr></thead>';
            echo '<tbody>';
            foreach ( $lockouts as $lockout ) {
                $hash = str_replace( '_transient_sl_lockout_', '', $lockout->option_name );
                echo '<tr>';
                echo '<td>' . esc_html( $hash ) . '</td>';
                echo '<td>' . esc_html( $lockout->option_value ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }

    /**
     * Handle the custom login routing and block direct wp-login.php/wp-admin access
     */
    public function custom_login_routing() {
        // Removed aggressive home redirect to allow standard WP auth flow
        
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = parse_url( $request_uri, PHP_URL_PATH );
        $path = trim( $path, '/' );

        // If the path ends with our custom slug
        if ( preg_match( '/' . preg_quote( $this->login_slug, '/' ) . '$/', $path ) ) {
            // We are hitting the custom login page
            if ( ! defined( 'SL_CUSTOM_LOGIN' ) ) {
                define( 'SL_CUSTOM_LOGIN', true );
            }
            $_SERVER['REQUEST_URI'] = str_replace( '/' . $this->login_slug, '/wp-login.php', $request_uri );
            
            // Fix variable scope for wp-login.php
            global $error, $user_login, $action, $interim_login;
            $error = '';
            $user_login = '';
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'login';
            $interim_login = isset($_REQUEST['interim-login']);
            
            require_once ABSPATH . 'wp-login.php';
            exit;
        }

        // Block direct access to wp-login.php if it's not a POST request and not explicitly allowed
        if ( strpos( $path, 'wp-login.php' ) !== false && $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            if ( ! defined( 'SL_CUSTOM_LOGIN' ) ) {
                // If it's a logout request, allow it to process but redirect to home after
                if ( isset( $_GET['action'] ) && $_GET['action'] === 'logout' ) {
                    return;
                }
                wp_redirect( home_url() );
                exit;
            }
        }
    }

    /**
     * Rewrite internal WordPress URLs that point to wp-login.php
     */
    public function rewrite_login_urls( $url, $path, $scheme, $blog_id = null ) {
        if ( strpos( $url, 'wp-login.php' ) !== false && strpos( $url, 'action=postpass' ) === false ) {
            $url = str_replace( 'wp-login.php', $this->login_slug, $url );
        }
        return $url;
    }

    /**
     * Intercept wp_redirect calls going to wp-login.php
     */
    public function intercept_login_redirects( $location, $status ) {
        if ( strpos( $location, 'wp-login.php' ) !== false && strpos( $location, 'action=postpass' ) === false ) {
            $location = str_replace( 'wp-login.php', $this->login_slug, $location );
        }
        return $location;
    }
}

// Initialize the plugin
new Securelite();
