<?php
/**
 * Plugin Name: Layout Tweaks
 * Description: Custom tweaks for header layout, logo centering, and ad removal.
 * Version: 1.0.0
 * Author: Antigravity
 */

if (!defined('ABSPATH')) {
    exit;
}

function md_layout_tweaks_css()
{
    ?>
    <style type="text/css">
        /* 1. Remove the top advertisement area */
        .td-header-sp-recs,
        .td-header-desktop-wrap .vc_row_inner>.td-pb-span8,
        .td-header-desktop-wrap .tdb_header_logo+.td-pb-span8 {
            display: none !important;
        }

        /* 2. Custom Logo Styling - User defined */
        .tdb_header_logo .tdb-logo-a, 
        .tdb_header_logo h1 {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: center !important;
            border: 2px solid #2f3337 !important;
            border-radius: 12px !important;
            overflow: hidden !important;
        }

        /* 3. Reduce Logo size and ensure centering */
        .tdb_header_logo img,
        .td-header-logo img,
        .tdb-logo-img {
            max-width: 286px !important;
            height: auto !important;
            margin: 0 auto !important;
            display: block !important;
            border: none !important;
        }
        /* 4. Force Video Modules to Display */
        .td_block_video_youtube,
        .td_block_video_vimeo,
        .wpb_video_wrapper,
        .td-video-playlist,
        .td-theme-video,
        .td-post-video,
        .td-video-embed {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
    </style>
    <?php
}
add_action('wp_head', 'md_layout_tweaks_css', 100);

/**
 * Custom Video Shortcode
 * Usage: [md_video url="https://www.youtube.com/embed/VIDEO_ID"]
 */
function md_custom_video_shortcode($atts) {
    $atts = shortcode_atts(array(
        'url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ' // Default video
    ), $atts);

    $video_url = esc_url($atts['url']);
    
    // Output a responsive video wrapper
    $output = '<div class="md-custom-video-wrapper" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; margin-bottom: 20px;">';
    $output .= '<iframe src="' . $video_url . '" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" allowfullscreen title="Video player"></iframe>';
    $output .= '</div>';

    return $output;
}
add_shortcode('md_video', 'md_custom_video_shortcode');

/**
 * Disable plugin and theme updates permanently (enable WordPress core updates only)
 */
add_filter('site_transient_update_plugins', '__return_false');
add_filter('site_transient_update_themes', '__return_false');
