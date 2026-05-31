<?php
/**
 * Plugin Name: News Manager Admin
 * Description: Adds News and Advertisement admin menus for managing posts and Newspaper ad spots.
 * Version: 1.0.0
 * Author: Sravan
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NMA_News_Manager_Admin {

    /**
     * Preferred category order.
     *
     * @return string[]
     */
    private function managed_categories() {
        return [
            'Qatar',
            'Gulf',
            'Kerala',
            'India',
            'Grama Vartha',
            'Writers Corner',
            'TECH',
            'MOVIES',
            'USA',
            'Sports',
            'Magazines',
        ];
    }

    /**
     * Newspaper ad spots.
     *
     * @return array<string, string>
     */
    private function ad_spots() {
        return [
            'header'         => 'Header ad',
            'sidebar'        => 'Sidebar ad',
            'content_top'    => 'Article top ad',
            'content_inline' => 'Article inline ad',
            'content_bottom' => 'Article bottom ad',
            'custom_ad_1'    => 'Custom ad 1',
            'custom_ad_2'    => 'Custom ad 2',
            'custom_ad_3'    => 'Custom ad 3',
            'custom_ad_4'    => 'Custom ad 4',
            'custom_ad_5'    => 'Custom ad 5',
        ];
    }

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_filter( 'the_author', [ $this, 'filter_manual_author_name' ] );
        add_filter( 'get_the_author_display_name', [ $this, 'filter_manual_author_display_name' ], 10, 3 );
    }

    /**
     * Enqueue media picker on plugin pages that need image selection.
     */
    public function enqueue_admin_assets() {
        if ( empty( $_GET['page'] ) ) {
            return;
        }

        $page = sanitize_key( wp_unslash( $_GET['page'] ) );
        if ( ! in_array( $page, [ 'nma-advertisement', 'nma-add-news' ], true ) ) {
            return;
        }

        wp_enqueue_media();
    }

    /**
     * Ensure managed categories exist.
     */
    public function ensure_categories_exist() {
        foreach ( $this->managed_categories() as $category_name ) {
            $slug = sanitize_title( $category_name );
            $term = get_term_by( 'slug', $slug, 'category' );

            if ( ! $term ) {
                wp_insert_term( $category_name, 'category', [ 'slug' => $slug ] );
            }
        }
    }

    /**
     * Register News menu and submenus.
     */
    public function register_admin_menu() {
        add_menu_page(
            'News',
            'News',
            'edit_posts',
            'nma-list-news',
            [ $this, 'render_list_news_page' ],
            'dashicons-media-document',
            25
        );

        add_submenu_page(
            'nma-list-news',
            'Add News',
            'Add News',
            'edit_posts',
            'nma-add-news',
            [ $this, 'render_add_news_page' ]
        );

        add_submenu_page(
            'nma-list-news',
            'List News',
            'List News',
            'edit_posts',
            'nma-list-news',
            [ $this, 'render_list_news_page' ]
        );

        add_menu_page(
            'Advertisement',
            'Advertisement',
            'edit_posts',
            'nma-advertisement',
            [ $this, 'render_advertisement_page' ],
            'dashicons-megaphone',
            26
        );

        add_submenu_page(
            'nma-advertisement',
            'Manage Ads',
            'Manage Ads',
            'edit_posts',
            'nma-advertisement',
            [ $this, 'render_advertisement_page' ]
        );
    }

    /**
     * Handle add/edit/delete actions.
     */
    public function handle_actions() {
        if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        if ( empty( $_POST['nma_action'] ) ) {
            return;
        }

        if ( ! isset( $_POST['nma_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nma_nonce'] ) ), 'nma_news_action' ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['nma_action'] ) );

        if ( 'save_news' === $action ) {
            $this->save_news_post();
        }

        if ( 'delete_news' === $action ) {
            $this->delete_news_post();
        }

        if ( 'save_ads' === $action ) {
            $this->save_ads_settings();
        }
    }

    /**
     * Save new or existing post.
     */
    private function save_news_post() {
        $post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $title    = isset( $_POST['news_title'] ) ? sanitize_text_field( wp_unslash( $_POST['news_title'] ) ) : '';
        $content  = isset( $_POST['news_content'] ) ? wp_kses_post( wp_unslash( $_POST['news_content'] ) ) : '';
        $excerpt  = isset( $_POST['news_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['news_excerpt'] ) ) : '';
        $category = isset( $_POST['news_category'] ) ? absint( $_POST['news_category'] ) : 0;
        $status   = isset( $_POST['news_status'] ) ? sanitize_key( wp_unslash( $_POST['news_status'] ) ) : 'draft';
        $date_raw = isset( $_POST['news_post_date'] ) ? sanitize_text_field( wp_unslash( $_POST['news_post_date'] ) ) : '';
        $featured_image_id = isset( $_POST['news_featured_image_id'] ) ? absint( $_POST['news_featured_image_id'] ) : 0;
        $author_name = isset( $_POST['news_author'] ) ? sanitize_text_field( wp_unslash( $_POST['news_author'] ) ) : '';
        $preview_requested = isset( $_POST['nma_preview_news'] );

        if ( empty( $title ) ) {
            $this->redirect_with_notice( 'nma-add-news', 'error', 'Title is required.' );
        }

        if ( ! in_array( $status, [ 'draft', 'publish', 'pending' ], true ) ) {
            $status = 'draft';
        }

        $selected_date = $this->normalize_news_post_date( $date_raw );
        if ( '' === $selected_date ) {
            $this->redirect_with_notice( 'nma-add-news', 'error', 'Please provide a valid post date.', $post_id ? $post_id : null );
        }

        $author_id = $this->resolve_news_author( $post_id, $author_name );

        $postarr = [
            'post_type'    => 'post',
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $status,
            'post_category'=> $category ? [ $category ] : [],
            'post_date'    => $selected_date . ' 00:00:00',
            'post_author'  => $author_id,
        ];

        if ( $post_id ) {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                $this->redirect_with_notice( 'nma-list-news', 'error', 'Permission denied for editing this post.' );
            }

            $postarr['ID'] = $post_id;
            $postarr['edit_date'] = true;
            $result = wp_update_post( $postarr, true );
            if ( is_wp_error( $result ) ) {
                $this->redirect_with_notice( 'nma-add-news', 'error', $result->get_error_message(), $post_id );
            }

            $this->update_manual_author_name( $post_id, $author_name );
            $this->update_news_featured_image( $post_id, $featured_image_id );
            if ( $preview_requested ) {
                $this->redirect_to_news_preview( $post_id );
            }
            $this->redirect_with_notice( 'nma-list-news', 'success', 'News updated successfully.' );
        }

        $result = wp_insert_post( $postarr, true );
        if ( is_wp_error( $result ) ) {
            $this->redirect_with_notice( 'nma-add-news', 'error', $result->get_error_message() );
        }

        $this->update_manual_author_name( (int) $result, $author_name );
        $this->update_news_featured_image( (int) $result, $featured_image_id );
        if ( $preview_requested ) {
            $this->redirect_to_news_preview( (int) $result );
        }
        $this->redirect_with_notice( 'nma-list-news', 'success', 'News added successfully.' );
    }

    /**
     * Redirect to frontend preview/permalink for a post.
     *
     * @param int $post_id
     */
    private function redirect_to_news_preview( $post_id ) {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
            $this->redirect_with_notice( 'nma-add-news', 'error', 'Unable to open preview.' );
        }

        $post_obj = get_post( $post_id );
        if ( ! $post_obj || 'post' !== $post_obj->post_type ) {
            $this->redirect_with_notice( 'nma-add-news', 'error', 'Unable to open preview.' );
        }

        $preview_url = get_preview_post_link( $post_obj );
        if ( empty( $preview_url ) ) {
            $preview_url = get_permalink( $post_id );
        }

        if ( empty( $preview_url ) ) {
            $this->redirect_with_notice( 'nma-add-news', 'error', 'Unable to generate preview link.', $post_id );
        }

        wp_safe_redirect( $preview_url );
        exit;
    }

    /**
     * Resolve author ID for post create/update from typed input.
     *
     * @param int $post_id
     * @param string $author_name
     * @return int
     */
    private function resolve_news_author( $post_id, $author_name ) {
        $post_id = (int) $post_id;
        $author_name = trim( (string) $author_name );
        $current_user_id = (int) get_current_user_id();
        $fallback_author_id = $current_user_id;

        if ( $post_id > 0 ) {
            $existing_author_id = (int) get_post_field( 'post_author', $post_id );
            if ( $existing_author_id > 0 ) {
                $fallback_author_id = $existing_author_id;
            }
        }

        if ( '' === $author_name ) {
            return $fallback_author_id;
        }

        $matched_author_id = $this->find_author_user_id( $author_name );
        if ( $matched_author_id <= 0 ) {
            return $fallback_author_id;
        }

        if ( $matched_author_id === $current_user_id || current_user_can( 'edit_others_posts' ) ) {
            return $matched_author_id;
        }

        return $fallback_author_id;
    }

    /**
     * Find matching author user ID from typed value.
     *
     * @param string $author_name
     * @return int
     */
    private function find_author_user_id( $author_name ) {
        $author_name = trim( (string) $author_name );
        if ( '' === $author_name ) {
            return 0;
        }

        if ( ctype_digit( $author_name ) ) {
            $user_by_id = get_user_by( 'id', (int) $author_name );
            if ( $user_by_id instanceof WP_User ) {
                return (int) $user_by_id->ID;
            }
        }

        $user_by_login = get_user_by( 'login', $author_name );
        if ( $user_by_login instanceof WP_User ) {
            return (int) $user_by_login->ID;
        }

        $user_by_email = get_user_by( 'email', $author_name );
        if ( $user_by_email instanceof WP_User ) {
            return (int) $user_by_email->ID;
        }

        $author_users = get_users(
            [
                'fields'     => [ 'ID', 'display_name', 'user_login', 'user_email' ],
                'orderby'    => 'display_name',
                'order'      => 'ASC',
                'capability' => [ 'edit_posts' ],
            ]
        );

        foreach ( $author_users as $author_user ) {
            if (
                0 === strcasecmp( $author_name, (string) $author_user->display_name ) ||
                0 === strcasecmp( $author_name, (string) $author_user->user_login ) ||
                0 === strcasecmp( $author_name, (string) $author_user->user_email )
            ) {
                return (int) $author_user->ID;
            }
        }

        return 0;
    }

    /**
     * Save manual author label for display override.
     *
     * @param int $post_id
     * @param string $author_name
     */
    private function update_manual_author_name( $post_id, $author_name ) {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 ) {
            return;
        }

        $author_name = trim( sanitize_text_field( (string) $author_name ) );
        if ( '' === $author_name ) {
            delete_post_meta( $post_id, '_nma_manual_author_name' );
            return;
        }

        update_post_meta( $post_id, '_nma_manual_author_name', $author_name );
    }

    /**
     * Return author suggestions for datalist.
     *
     * @return string[]
     */
    private function get_author_suggestions() {
        $suggestions = [];

        $author_users = get_users(
            [
                'fields'     => [ 'display_name', 'user_login' ],
                'orderby'    => 'display_name',
                'order'      => 'ASC',
                'capability' => [ 'edit_posts' ],
            ]
        );

        foreach ( $author_users as $author_user ) {
            $display_name = trim( (string) $author_user->display_name );
            if ( '' !== $display_name ) {
                $suggestions[] = $display_name;
            }
        }

        $suggestions = array_values( array_unique( $suggestions ) );
        sort( $suggestions, SORT_NATURAL | SORT_FLAG_CASE );

        return $suggestions;
    }

    /**
     * Read current post manual author value.
     *
     * @return string
     */
    private function get_current_post_manual_author_name() {
        global $post;

        if ( ! ( $post instanceof WP_Post ) || 'post' !== $post->post_type ) {
            return '';
        }

        $manual_author = get_post_meta( $post->ID, '_nma_manual_author_name', true );
        if ( ! is_string( $manual_author ) ) {
            return '';
        }

        return trim( $manual_author );
    }

    /**
     * Replace the displayed author when manual author is set on post.
     *
     * @param string $display_name
     * @return string
     */
    public function filter_manual_author_name( $display_name ) {
        $manual_author = $this->get_current_post_manual_author_name();
        return '' !== $manual_author ? $manual_author : $display_name;
    }

    /**
     * Replace author display_name value when manual author is set on post.
     *
     * @param string $display_name
     * @param int $user_id
     * @param int $original_user_id
     * @return string
     */
    public function filter_manual_author_display_name( $display_name, $user_id, $original_user_id ) {
        $manual_author = $this->get_current_post_manual_author_name();
        return '' !== $manual_author ? $manual_author : $display_name;
    }

    /**
     * Set or remove featured image for a news post.
     *
     * @param int $post_id
     * @param int $attachment_id
     */
    private function update_news_featured_image( $post_id, $attachment_id ) {
        $post_id = (int) $post_id;
        $attachment_id = (int) $attachment_id;

        if ( $post_id <= 0 ) {
            return;
        }

        if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) && wp_attachment_is_image( $attachment_id ) ) {
            set_post_thumbnail( $post_id, $attachment_id );
            return;
        }

        delete_post_thumbnail( $post_id );
    }

    /**
     * Normalize submitted post date as Y-m-d.
     *
     * @param string $raw_date
     * @return string
     */
    private function normalize_news_post_date( $raw_date ) {
        $raw_date = trim( (string) $raw_date );

        if ( '' === $raw_date ) {
            return current_time( 'Y-m-d' );
        }

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date ) ) {
            return '';
        }

        $parts = explode( '-', $raw_date );
        if ( 3 !== count( $parts ) ) {
            return '';
        }

        $year  = (int) $parts[0];
        $month = (int) $parts[1];
        $day   = (int) $parts[2];

        if ( ! checkdate( $month, $day, $year ) ) {
            return '';
        }

        return sprintf( '%04d-%02d-%02d', $year, $month, $day );
    }

    /**
     * Delete post from list page.
     */
    private function delete_news_post() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $post_id || ! current_user_can( 'delete_post', $post_id ) ) {
            $this->redirect_with_notice( 'nma-list-news', 'error', 'Permission denied for deleting this post.' );
        }

        wp_delete_post( $post_id, true );
        $this->redirect_with_notice( 'nma-list-news', 'success', 'News deleted successfully.' );
    }

    /**
     * Redirect to plugin page with message.
     *
     * @param string   $page Slug.
     * @param string   $type Type.
     * @param string   $message Message.
     * @param int|null $post_id Optional post ID for edit form.
     */
    private function redirect_with_notice( $page, $type, $message, $post_id = null ) {
        $args = [
            'page'        => $page,
            'nma_notice'  => rawurlencode( $message ),
            'nma_type'    => $type,
        ];

        if ( $post_id ) {
            $args['edit_post'] = (int) $post_id;
        }

        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Print admin notice from query args.
     */
    private function render_notice() {
        if ( empty( $_GET['nma_notice'] ) ) {
            return;
        }

        $message = sanitize_text_field( wp_unslash( $_GET['nma_notice'] ) );
        $type    = isset( $_GET['nma_type'] ) ? sanitize_key( wp_unslash( $_GET['nma_type'] ) ) : 'success';
        $class   = ( 'error' === $type ) ? 'notice notice-error' : 'notice notice-success';

        echo '<div class="' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }

    /**
     * Build category dropdown options from managed categories.
     *
     * @return array<int, string>
     */
    private function get_category_options() {
        $options = [];

        foreach ( $this->managed_categories() as $category_name ) {
            $term = get_term_by( 'slug', sanitize_title( $category_name ), 'category' );
            if ( $term && ! is_wp_error( $term ) ) {
                $options[ (int) $term->term_id ] = $term->name;
            }
        }

        return $options;
    }

    /**
     * Read td_ads from theme options.
     *
     * @return array<string, mixed>
     */
    private function get_td_ads_options() {
        if ( class_exists( 'td_options' ) ) {
            return td_options::get_array( 'td_ads', [] );
        }

        $theme_option_name = defined( 'TD_THEME_OPTIONS_NAME' ) ? TD_THEME_OPTIONS_NAME : 'td_011';
        $theme_options = get_option( $theme_option_name, [] );
        if ( ! is_array( $theme_options ) ) {
            return [];
        }

        return ( isset( $theme_options['td_ads'] ) && is_array( $theme_options['td_ads'] ) ) ? $theme_options['td_ads'] : [];
    }

    /**
     * Persist td_ads into theme options.
     *
     * @param array<string, mixed> $ads
     */
    private function update_td_ads_options( $ads ) {
        if ( class_exists( 'td_options' ) ) {
            td_options::update_array( 'td_ads', $ads );
            return;
        }

        $theme_option_name = defined( 'TD_THEME_OPTIONS_NAME' ) ? TD_THEME_OPTIONS_NAME : 'td_011';
        $theme_options = get_option( $theme_option_name, [] );
        if ( ! is_array( $theme_options ) ) {
            $theme_options = [];
        }
        $theme_options['td_ads'] = $ads;
        update_option( $theme_option_name, $theme_options );
    }

    /**
     * Resolve a source value to absolute URL when possible.
     *
     * @param string $src
     * @return string
     */
    private function resolve_ad_src( $src ) {
        $src = trim( $src );
        if ( '' === $src ) {
            return '';
        }

        if ( preg_match( '#^https?://#i', $src ) ) {
            return $src;
        }

        if ( 0 === strpos( $src, '/' ) ) {
            return home_url( $src );
        }

        return home_url( '/' . ltrim( $src, '/' ) );
    }

    /**
     * Extract image src from stored ad code.
     *
     * @param string $ad_code
     * @return string
     */
    private function extract_ad_src( $ad_code ) {
        $ad_code = wp_unslash( (string) $ad_code );
        if ( preg_match( '/<img[^>]*src=["\']([^"\']+)["\']/i', $ad_code, $matches ) ) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Build Newspaper-compatible ad code from src only.
     *
     * @param string $src
     * @return string
     */
    private function build_ad_code_from_src( $src ) {
        $resolved_src = $this->resolve_ad_src( $src );
        if ( '' === $resolved_src ) {
            return '';
        }

        return '<div class="td-all-devices"><img alt="" src="' . esc_url( $resolved_src ) . '" /></div>';
    }

    /**
     * Save advertisement spot ad code.
     */
    private function save_ads_settings() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            $this->redirect_with_notice( 'nma-advertisement', 'error', 'Permission denied for updating ads.' );
        }

        $posted_ads_src = isset( $_POST['ads_src'] ) ? wp_unslash( $_POST['ads_src'] ) : [];
        if ( ! is_array( $posted_ads_src ) ) {
            $posted_ads_src = [];
        }

        $existing_ads = $this->get_td_ads_options();
        $allowed_spots = $this->ad_spots();

        foreach ( $allowed_spots as $spot_id => $spot_label ) {
            $raw_src = isset( $posted_ads_src[ $spot_id ] ) ? sanitize_text_field( (string) $posted_ads_src[ $spot_id ] ) : '';
            $ad_code = $this->build_ad_code_from_src( $raw_src );

            $spot_data = [];
            if ( isset( $existing_ads[ $spot_id ] ) && is_array( $existing_ads[ $spot_id ] ) ) {
                $spot_data = $existing_ads[ $spot_id ];
            }

            $spot_data['ad_code'] = $ad_code;
            $spot_data['current_ad_type'] = ( '' !== $ad_code ) ? 'other' : '';
            $existing_ads[ $spot_id ] = $spot_data;
        }

        $this->update_td_ads_options( $existing_ads );
        $this->redirect_with_notice( 'nma-advertisement', 'success', 'Advertisement settings updated.' );
    }

    /**
     * Add/Edit form page.
     */
    public function render_add_news_page() {
        $this->ensure_categories_exist();

        $editing_post_id = isset( $_GET['edit_post'] ) ? absint( $_GET['edit_post'] ) : 0;
        $post_obj = null;

        if ( $editing_post_id ) {
            $post_obj = get_post( $editing_post_id );
            if ( ! $post_obj || 'post' !== $post_obj->post_type || ! current_user_can( 'edit_post', $editing_post_id ) ) {
                $this->redirect_with_notice( 'nma-list-news', 'error', 'Invalid post for editing.' );
            }
        }

        $title   = $post_obj ? $post_obj->post_title : '';
        $content = $post_obj ? $post_obj->post_content : '';
        $excerpt = $post_obj ? $post_obj->post_excerpt : '';
        $status  = $post_obj ? $post_obj->post_status : 'draft';
        $cats    = $post_obj ? wp_get_post_categories( $post_obj->ID ) : [];
        $cat_id  = ! empty( $cats ) ? (int) $cats[0] : 0;
        $post_date = $post_obj ? get_the_date( 'Y-m-d', $post_obj->ID ) : current_time( 'Y-m-d' );
        $featured_image_id = $post_obj ? (int) get_post_thumbnail_id( $post_obj->ID ) : 0;
        $featured_image_url = $featured_image_id ? wp_get_attachment_image_url( $featured_image_id, 'medium' ) : '';
        $author_id = $post_obj ? (int) $post_obj->post_author : (int) get_current_user_id();
        $author_name = '';
        if ( ! $featured_image_url && $featured_image_id ) {
            $featured_image_url = wp_get_attachment_url( $featured_image_id );
        }

        $category_options = $this->get_category_options();
        $current_user = wp_get_current_user();
        $author_name = $post_obj ? (string) get_post_meta( $post_obj->ID, '_nma_manual_author_name', true ) : '';
        if ( '' === trim( $author_name ) ) {
            if ( $author_id > 0 ) {
                $resolved_author_name = get_the_author_meta( 'display_name', $author_id );
                if ( is_string( $resolved_author_name ) ) {
                    $author_name = $resolved_author_name;
                }
            } elseif ( $current_user->exists() ) {
                $author_name = (string) $current_user->display_name;
            }
        }
        $author_suggestions = $this->get_author_suggestions();
        ?>
        <div class="wrap">
            <h1><?php echo $post_obj ? esc_html__( 'Edit News', 'nma' ) : esc_html__( 'Add News', 'nma' ); ?></h1>
            <?php $this->render_notice(); ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=nma-add-news' ) ); ?>">
                <?php wp_nonce_field( 'nma_news_action', 'nma_nonce' ); ?>
                <input type="hidden" name="nma_action" value="save_news">
                <input type="hidden" name="post_id" value="<?php echo esc_attr( $editing_post_id ); ?>">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="news_title">Title</label></th>
                        <td><input type="text" class="regular-text" id="news_title" name="news_title" value="<?php echo esc_attr( $title ); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="news_category">Category</label></th>
                        <td>
                            <select id="news_category" name="news_category" required>
                                <option value="">Select category</option>
                                <?php foreach ( $category_options as $term_id => $term_name ) : ?>
                                    <option value="<?php echo esc_attr( $term_id ); ?>" <?php selected( $cat_id, $term_id ); ?>>
                                        <?php echo esc_html( $term_name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="news_status">Status</label></th>
                        <td>
                            <select id="news_status" name="news_status">
                                <option value="draft" <?php selected( $status, 'draft' ); ?>>Draft</option>
                                <option value="publish" <?php selected( $status, 'publish' ); ?>>Publish</option>
                                <option value="pending" <?php selected( $status, 'pending' ); ?>>Pending Review</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="news_author">Author</label></th>
                        <td>
                            <input type="text" class="regular-text" id="news_author" name="news_author" list="nma-author-suggestions" value="<?php echo esc_attr( $author_name ); ?>" placeholder="Type author name">
                            <datalist id="nma-author-suggestions">
                                <?php foreach ( $author_suggestions as $author_suggestion ) : ?>
                                    <option value="<?php echo esc_attr( $author_suggestion ); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <p class="description">Type author manually. Matching user names will be linked automatically.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="news_post_date">Post Date</label></th>
                        <td>
                            <input type="date" id="news_post_date" name="news_post_date" value="<?php echo esc_attr( $post_date ); ?>" required>
                            <p class="description">Choose the publish date for this news item (defaults to today).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="news_excerpt">Excerpt</label></th>
                        <td><textarea id="news_excerpt" name="news_excerpt" rows="4" class="large-text"><?php echo esc_textarea( $excerpt ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="news_featured_image_id">Featured Image</label></th>
                        <td>
                            <input type="hidden" id="news_featured_image_id" name="news_featured_image_id" value="<?php echo esc_attr( $featured_image_id ); ?>">
                            <div id="news_featured_image_preview_wrap" style="width:100%;max-width:520px;min-height:120px;border:1px solid #dcdcde;background:#fff;padding:10px;display:flex;align-items:center;justify-content:center;">
                                <?php if ( ! empty( $featured_image_url ) ) : ?>
                                    <img id="news_featured_image_preview" src="<?php echo esc_url( $featured_image_url ); ?>" alt="" style="max-width:100%;height:auto;">
                                <?php else : ?>
                                    <img id="news_featured_image_preview" src="" alt="" style="display:none;max-width:100%;height:auto;">
                                    <span id="news_featured_image_text" style="color:#646970;">No featured image selected.</span>
                                <?php endif; ?>
                            </div>
                            <p style="margin:8px 0 0;">
                                <button type="button" class="button" id="news_featured_image_pick">Set featured image</button>
                                <button type="button" class="button" id="news_featured_image_remove" <?php echo $featured_image_id ? '' : 'style="display:none;"'; ?>>Remove featured image</button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Content</th>
                        <td>
                            <?php
                            wp_editor(
                                $content,
                                'news_content',
                                [
                                    'textarea_name' => 'news_content',
                                    'media_buttons' => true,
                                    'textarea_rows' => 12,
                                ]
                            );
                            ?>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <?php submit_button( $post_obj ? 'Update News' : 'Save News', 'primary', 'submit', false ); ?>
                    <?php submit_button( 'Preview News', 'secondary', 'nma_preview_news', false ); ?>
                </p>
            </form>
        </div>
        <script>
            jQuery(function($) {
                var $input = $('#news_featured_image_id');
                var $preview = $('#news_featured_image_preview');
                var $previewWrap = $('#news_featured_image_preview_wrap');
                var $previewText = $('#news_featured_image_text');
                var $removeButton = $('#news_featured_image_remove');
                var frame;

                function clearFeaturedImage() {
                    $input.val('');
                    $preview.attr('src', '').hide();
                    if ($previewText.length) {
                        $previewText.show();
                    } else {
                        $previewWrap.append('<span id="news_featured_image_text" style="color:#646970;">No featured image selected.</span>');
                        $previewText = $('#news_featured_image_text');
                    }
                    $removeButton.hide();
                }

                function setFeaturedImage(attachment) {
                    var imageUrl = '';

                    if (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) {
                        imageUrl = attachment.sizes.medium.url;
                    } else if (attachment.url) {
                        imageUrl = attachment.url;
                    }

                    if (!attachment.id || !imageUrl) {
                        return;
                    }

                    $input.val(attachment.id);
                    $preview.attr('src', imageUrl).show();
                    if ($previewText.length) {
                        $previewText.hide();
                    }
                    $removeButton.show();
                }

                $('#news_featured_image_pick').on('click', function(e) {
                    e.preventDefault();

                    if (frame) {
                        frame.open();
                        return;
                    }

                    frame = wp.media({
                        title: 'Select featured image',
                        button: { text: 'Use this image' },
                        multiple: false,
                        library: { type: 'image' }
                    });

                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        setFeaturedImage(attachment);
                    });

                    frame.open();
                });

                $removeButton.on('click', function(e) {
                    e.preventDefault();
                    clearFeaturedImage();
                });
            });
        </script>
        <?php
    }

    /**
     * List posts with edit/delete actions.
     */
    public function render_list_news_page() {
        $paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 20;

        $query = new WP_Query(
            [
                'post_type'      => 'post',
                'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
                'posts_per_page' => $per_page,
                'paged'          => $paged,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );
        ?>
        <div class="wrap">
            <h1>List News</h1>
            <?php $this->render_notice(); ?>

            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=nma-add-news' ) ); ?>" class="button button-primary">Add News</a></p>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 32%;">Title</th>
                        <th style="width: 15%;">Category</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 16%;">Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $query->have_posts() ) : ?>
                        <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                            <?php
                            $post_id = get_the_ID();
                            $cat_names = wp_get_post_categories( $post_id, [ 'fields' => 'names' ] );
                            $edit_url = add_query_arg(
                                [
                                    'page'      => 'nma-add-news',
                                    'edit_post' => $post_id,
                                ],
                                admin_url( 'admin.php' )
                            );
                            ?>
                            <tr>
                                <td><?php echo esc_html( get_the_title() ); ?></td>
                                <td><?php echo esc_html( ! empty( $cat_names ) ? implode( ', ', $cat_names ) : '-' ); ?></td>
                                <td><?php echo esc_html( get_post_status( $post_id ) ); ?></td>
                                <td><?php echo esc_html( get_the_date( 'Y-m-d H:i', $post_id ) ); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>">Edit</a>
                                    <button
                                        type="button"
                                        class="button button-small nma-copy-url"
                                        style="margin-left:6px;"
                                        data-url="<?php echo esc_attr( rawurldecode( get_permalink( $post_id ) ) ); ?>"
                                    >Copy URL</button>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=nma-list-news' ) ); ?>" style="display:inline-block; margin-left: 6px;">
                                        <?php wp_nonce_field( 'nma_news_action', 'nma_nonce' ); ?>
                                        <input type="hidden" name="nma_action" value="delete_news">
                                        <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
                                        <button type="submit" class="button button-small" onclick="return confirm('Delete this news post?');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr><td colspan="5">No posts found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            $total_pages = (int) $query->max_num_pages;
            if ( $total_pages > 1 ) {
                echo '<div style="margin-top:12px;">';
                echo wp_kses_post(
                    paginate_links(
                        [
                            'base'      => add_query_arg( [ 'page' => 'nma-list-news', 'paged' => '%#%' ], admin_url( 'admin.php' ) ),
                            'format'    => '',
                            'current'   => $paged,
                            'total'     => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ]
                    )
                );
                echo '</div>';
            }

            wp_reset_postdata();
            ?>
        </div>
        <script>
        (function() {
            document.querySelectorAll('.nma-copy-url').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var url = btn.getAttribute('data-url');
                    var original = btn.textContent;
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(url);
                    } else {
                        var inp = document.createElement('input');
                        inp.style.cssText = 'position:fixed;top:-9999px;left:-9999px';
                        inp.value = url;
                        document.body.appendChild(inp);
                        inp.select();
                        document.execCommand('copy');
                        document.body.removeChild(inp);
                    }
                    btn.textContent = 'Copied!';
                    btn.disabled = true;
                    setTimeout(function() {
                        btn.textContent = original;
                        btn.disabled = false;
                    }, 1500);
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * Advertisement manager page.
     */
    public function render_advertisement_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'nma' ) );
        }

        $ads = $this->get_td_ads_options();
        ?>
        <div class="wrap">
            <h1>Advertisement</h1>
            <?php $this->render_notice(); ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=nma-advertisement' ) ); ?>">
                <?php wp_nonce_field( 'nma_news_action', 'nma_nonce' ); ?>
                <input type="hidden" name="nma_action" value="save_ads">

                <table class="form-table" role="presentation">
                    <?php foreach ( $this->ad_spots() as $spot_id => $spot_label ) : ?>
                        <?php
                        $spot_data = ( isset( $ads[ $spot_id ] ) && is_array( $ads[ $spot_id ] ) ) ? $ads[ $spot_id ] : [];
                        $ad_code = isset( $spot_data['ad_code'] ) ? (string) $spot_data['ad_code'] : '';
                        $ad_src = $this->extract_ad_src( $ad_code );
                        $preview_src = $this->resolve_ad_src( $ad_src );
                        ?>
                        <tr>
                            <th scope="row">
                                <label for="ads_<?php echo esc_attr( $spot_id ); ?>"><?php echo esc_html( $spot_label ); ?></label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="ads_<?php echo esc_attr( $spot_id ); ?>"
                                    name="ads_src[<?php echo esc_attr( $spot_id ); ?>]"
                                    class="regular-text"
                                    style="width:100%;max-width:1000px;"
                                    placeholder="wp-content/uploads/2026/01/your-ad.jpg or https://example.com/ad.jpg"
                                    value="<?php echo esc_attr( $ad_src ); ?>">
                                <p style="margin:8px 0 0;">
                                    <button
                                        type="button"
                                        class="button nma-media-pick"
                                        data-target-input="ads_<?php echo esc_attr( $spot_id ); ?>"
                                        data-target-preview="ads_preview_<?php echo esc_attr( $spot_id ); ?>">
                                        Browse Media
                                    </button>
                                </p>
                                <p class="description">Enter only image path/URL for <strong><?php echo esc_html( $spot_id ); ?></strong>. Leave empty to disable this spot.</p>
                                <div style="margin-top:10px;">
                                    <strong>Preview:</strong>
                                    <?php if ( '' !== trim( $preview_src ) ) : ?>
                                        <div id="ads_preview_wrap_<?php echo esc_attr( $spot_id ); ?>" style="display:block;width:100%;max-width:1000px;min-height:90px;border:1px solid #dcdcde;background:#fff;margin-top:6px;padding:10px;overflow:auto;">
                                            <img id="ads_preview_<?php echo esc_attr( $spot_id ); ?>" src="<?php echo esc_url( $preview_src ); ?>" alt="" style="max-width:100%;height:auto;">
                                        </div>
                                    <?php else : ?>
                                        <div id="ads_preview_wrap_<?php echo esc_attr( $spot_id ); ?>" style="display:block;width:100%;max-width:1000px;min-height:90px;border:1px dashed #dcdcde;background:#fff;margin-top:6px;padding:10px;overflow:auto;color:#646970;">
                                            <img id="ads_preview_<?php echo esc_attr( $spot_id ); ?>" src="" alt="" style="display:none;max-width:100%;height:auto;">
                                            <span id="ads_preview_text_<?php echo esc_attr( $spot_id ); ?>">No ad source set for this spot.</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button( 'Save Advertisement Settings' ); ?>
            </form>
        </div>
        <script>
            jQuery(function($) {
                $('.nma-media-pick').on('click', function(e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var inputId = $btn.data('target-input');
                    var previewId = $btn.data('target-preview');
                    var $input = $('#' + inputId);
                    var $preview = $('#' + previewId);
                    var $previewWrap = $('#ads_preview_wrap_' + previewId.replace('ads_preview_', ''));
                    var $previewText = $('#ads_preview_text_' + previewId.replace('ads_preview_', ''));

                    var frame = wp.media({
                        title: 'Select ad image',
                        button: { text: 'Use this image' },
                        multiple: false,
                        library: { type: 'image' }
                    });

                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        if (!attachment || !attachment.url) {
                            return;
                        }

                        $input.val(attachment.url).trigger('change');
                        $preview.attr('src', attachment.url).show();
                        $previewWrap.css('border-style', 'solid').css('color', '');
                        if ($previewText.length) {
                            $previewText.hide();
                        }
                    });

                    frame.open();
                });
            });
        </script>
        <?php
    }
}

$nma_news_manager = new NMA_News_Manager_Admin();
register_activation_hook( __FILE__, [ $nma_news_manager, 'ensure_categories_exist' ] );
