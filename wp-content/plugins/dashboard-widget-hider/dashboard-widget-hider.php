<?php
/**
 * Plugin Name: Dashboard Widget Hider
 * Description: Hides default WordPress dashboard widgets.
 * Version: 1.0.0
 * Author: Sravan
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Remove default dashboard widgets.
 */
function dwh_hide_default_dashboard_widgets() {
    // Main dashboard widgets.
    remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
    remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
    remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );

    // Side dashboard widgets.
    remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
    remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );

    // Optional: remove Welcome panel.
    remove_action( 'welcome_panel', 'wp_welcome_panel' );

    // Add custom latest news widget.
    wp_add_dashboard_widget(
        'dwh_latest_news_widget',
        'Latest News (5)',
        'dwh_render_latest_news_widget'
    );

    // Add summary/counts widget.
    wp_add_dashboard_widget(
        'dwh_news_summary_widget',
        'News Summary',
        'dwh_render_news_summary_widget',
        null,
        null,
        'side',
        'high'
    );
}
add_action( 'wp_dashboard_setup', 'dwh_hide_default_dashboard_widgets', 999 );

/**
 * Render custom latest news dashboard widget.
 */
function dwh_render_latest_news_widget() {
    $query = new WP_Query(
        [
            'post_type'           => 'post',
            'post_status'         => [ 'publish', 'draft', 'pending', 'future', 'private' ],
            'posts_per_page'      => 5,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
        ]
    );

    if ( ! $query->have_posts() ) {
        echo '<p>No news posts found.</p>';
        return;
    }

    echo '<ul style="margin:0;">';
    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id = get_the_ID();
        $title = get_the_title();
        $edit_url = get_edit_post_link( $post_id );
        $permalink = get_permalink( $post_id );
        $date = get_the_date( 'Y-m-d H:i', $post_id );

        echo '<li style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid #e2e4e7;">';

        echo '<div style="width:60px;min-width:60px;">';
        if ( has_post_thumbnail( $post_id ) ) {
            echo get_the_post_thumbnail( $post_id, [ 60, 60 ], [ 'style' => 'display:block;width:60px;height:60px;object-fit:cover;border-radius:4px;' ] );
        } else {
            echo '<div style="width:60px;height:60px;background:#f0f0f1;border:1px solid #dcdcde;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#8c8f94;font-size:11px;">No Image</div>';
        }
        echo '</div>';

        echo '<div style="min-width:0;">';
        echo '<div style="font-weight:600;line-height:1.35;">';
        if ( $permalink ) {
            echo '<a href="' . esc_url( $permalink ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $title ) . '</a>';
        } else {
            echo esc_html( $title );
        }
        echo '</div>';
        echo '<div style="color:#646970;font-size:12px;margin-top:3px;">' . esc_html( $date ) . '</div>';
        if ( $edit_url && current_user_can( 'edit_post', $post_id ) ) {
            echo '<div style="margin-top:4px;"><a href="' . esc_url( $edit_url ) . '">Edit</a></div>';
        }
        echo '</div>';

        echo '</li>';
    }
    echo '</ul>';

    wp_reset_postdata();
}

/**
 * Count configured advertisement spots from Newspaper theme options.
 *
 * @return int
 */
function dwh_get_configured_ads_count() {
    $theme_option_name = defined( 'TD_THEME_OPTIONS_NAME' ) ? TD_THEME_OPTIONS_NAME : 'td_011';
    $theme_options = get_option( $theme_option_name, [] );

    if ( ! is_array( $theme_options ) || empty( $theme_options['td_ads'] ) || ! is_array( $theme_options['td_ads'] ) ) {
        return 0;
    }

    $count = 0;
    foreach ( $theme_options['td_ads'] as $spot ) {
        if ( is_array( $spot ) && ! empty( $spot['ad_code'] ) ) {
            $count++;
        }
    }

    return $count;
}

/**
 * Render summary counters widget.
 */
function dwh_render_news_summary_widget() {
    $post_counts = wp_count_posts( 'post' );
    $published_count = isset( $post_counts->publish ) ? (int) $post_counts->publish : 0;
    $draft_count = isset( $post_counts->draft ) ? (int) $post_counts->draft : 0;
    $pending_count = isset( $post_counts->pending ) ? (int) $post_counts->pending : 0;
    $future_count = isset( $post_counts->future ) ? (int) $post_counts->future : 0;
    $private_count = isset( $post_counts->private ) ? (int) $post_counts->private : 0;
    $unpublished_count = $draft_count + $pending_count + $future_count + $private_count;

    $category_count = wp_count_terms(
        [
            'taxonomy'   => 'category',
            'hide_empty' => false,
        ]
    );

    if ( is_wp_error( $category_count ) ) {
        $category_count = 0;
    }

    $ads_count = dwh_get_configured_ads_count();

    $cards = [
        [
            'label' => 'All News',
            'count' => $published_count,
            'color' => '#18a2b8',
            'link'  => admin_url( 'edit.php?post_type=post&post_status=publish' ),
        ],
        [
            'label' => 'All Categories',
            'count' => (int) $category_count,
            'color' => '#2dce89',
            'link'  => admin_url( 'edit-tags.php?taxonomy=category' ),
        ],
        [
            'label' => 'Advertisements',
            'count' => $ads_count,
            'color' => '#f5a623',
            'link'  => admin_url( 'admin.php?page=nma-advertisement' ),
        ],
        [
            'label' => 'Unpublished',
            'count' => $unpublished_count,
            'color' => '#f5365c',
            'link'  => admin_url( 'edit.php?post_type=post&post_status=draft' ),
        ],
    ];

    echo '<style>
        .dwh-summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:0}
        .dwh-summary-card{display:block;border-radius:8px;overflow:hidden;color:#fff;text-decoration:none;box-shadow:0 1px 3px rgba(0,0,0,.15)}
        .dwh-summary-top{padding:12px 12px 10px}
        .dwh-summary-count{font-size:28px;line-height:1;font-weight:700;margin:0 0 6px}
        .dwh-summary-label{font-size:13px;line-height:1.2;opacity:.95}
        .dwh-summary-bottom{padding:7px 12px;font-size:12px;line-height:1.2;background:rgba(0,0,0,.12)}
        .dwh-summary-card:hover .dwh-summary-bottom{background:rgba(0,0,0,.2)}
        @media (max-width: 782px){.dwh-summary-grid{grid-template-columns:1fr}}
    </style>';

    echo '<div class="dwh-summary-grid">';
    foreach ( $cards as $card ) {
        echo '<a class="dwh-summary-card" href="' . esc_url( $card['link'] ) . '" style="background:' . esc_attr( $card['color'] ) . ';">';
        echo '<div class="dwh-summary-top">';
        echo '<div class="dwh-summary-count">' . esc_html( (string) $card['count'] ) . '</div>';
        echo '<div class="dwh-summary-label">' . esc_html( $card['label'] ) . '</div>';
        echo '</div>';
        echo '<div class="dwh-summary-bottom">More info</div>';
        echo '</a>';
    }
    echo '</div>';
}

/**
 * Optional cleanup: hide Screen Options and Help tabs on dashboard.
 */
function dwh_hide_dashboard_screen_tabs() {
    $screen = get_current_screen();

    if ( $screen && 'dashboard' === $screen->id ) {
        add_filter( 'screen_options_show_screen', '__return_false' );
        $screen->remove_help_tabs();
    }
}
add_action( 'current_screen', 'dwh_hide_dashboard_screen_tabs' );
