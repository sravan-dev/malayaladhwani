<?php
/**
 * Plugin Name: Hide Unwanted Metaboxes
 * Description: Format, Featured Audio metaboxes hide ചെ‌യ്യുന്നു
 */

add_action('add_meta_boxes', 'mad_hide_extra_metaboxes', 999);
function mad_hide_extra_metaboxes() {
    // Format metabox (WordPress built-in)
    remove_meta_box('formatdiv', 'post', 'side');
    remove_meta_box('formatdiv', 'post', 'normal');

    // Featured Video (Newspaper/td-composer via WPAlchemy)
    remove_meta_box('td_post_video_metabox', 'post', 'side');
    remove_meta_box('td_post_video_metabox', 'post', 'normal');
    remove_meta_box('td_post_video_metabox', 'post', 'advanced');

    // Featured Audio (Newspaper/td-composer via WPAlchemy)
    remove_meta_box('td_post_audio_metabox', 'post', 'side');
    remove_meta_box('td_post_audio_metabox', 'post', 'normal');

    // Post Settings (Newspaper/td-composer via WPAlchemy)
    remove_meta_box('td_post_theme_settings_metabox', 'post', 'normal');
    remove_meta_box('td_post_theme_settings_metabox', 'post', 'side');
    remove_meta_box('td_post_theme_settings_metabox', 'post', 'advanced');
}
