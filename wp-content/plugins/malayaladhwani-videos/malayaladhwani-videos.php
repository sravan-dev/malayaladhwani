<?php
/**
 * Plugin Name: Malayaladhwani Videos
 * Description: Custom plugin to manage and display YouTube or uploaded videos via shortcode.
 * Version: 1.0
 * Author: Sravan
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Register Custom Post Type
add_action( 'init', 'md_register_video_cpt' );
function md_register_video_cpt() {
    $labels = array(
        'name'               => 'Videos',
        'singular_name'      => 'Video',
        'menu_name'          => 'Videos',
        'name_admin_bar'     => 'Video',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Video',
        'new_item'           => 'New Video',
        'edit_item'          => 'Edit Video',
        'view_item'          => 'View Video',
        'all_items'          => 'All Videos',
        'search_items'       => 'Search Videos',
        'parent_item_colon'  => 'Parent Videos:',
        'not_found'          => 'No videos found.',
        'not_found_in_trash' => 'No videos found in Trash.'
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'video' ),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-video-alt3',
        'supports'           => array( 'title' )
    );

    register_post_type( 'md_video', $args );
}

// 2. Add Meta Boxes for Video Details
add_action( 'add_meta_boxes', 'md_add_video_meta_boxes' );
function md_add_video_meta_boxes() {
    add_meta_box(
        'md_video_details',
        'Video Details',
        'md_render_video_meta_box',
        'md_video',
        'normal',
        'high'
    );
}

function md_render_video_meta_box( $post ) {
    wp_nonce_field( 'md_save_video_meta', 'md_video_meta_nonce' );

    $video_type = get_post_meta( $post->ID, '_md_video_type', true );
    $youtube_url = get_post_meta( $post->ID, '_md_youtube_url', true );
    $upload_url = get_post_meta( $post->ID, '_md_upload_url', true );

    if ( empty( $video_type ) ) {
        $video_type = 'youtube';
    }
    ?>
    <style>
        .md-video-field { margin-bottom: 15px; }
        .md-video-field label { font-weight: bold; display: block; margin-bottom: 5px; }
        .md-video-field input[type="text"] { width: 100%; max-width: 600px; }
        .md-hidden { display: none; }
    </style>
    
    <div class="md-video-field">
        <label for="md_video_type">Video Source:</label>
        <select name="md_video_type" id="md_video_type">
            <option value="youtube" <?php selected( $video_type, 'youtube' ); ?>>YouTube Link</option>
            <option value="upload" <?php selected( $video_type, 'upload' ); ?>>Upload MP4 File</option>
        </select>
    </div>

    <div class="md-video-field" id="md_youtube_field" class="<?php echo $video_type === 'upload' ? 'md-hidden' : ''; ?>">
        <label for="md_youtube_url">YouTube URL:</label>
        <input type="text" name="md_youtube_url" id="md_youtube_url" value="<?php echo esc_attr( $youtube_url ); ?>" placeholder="https://www.youtube.com/watch?v=..." />
    </div>

    <div class="md-video-field" id="md_upload_field" class="<?php echo $video_type === 'youtube' ? 'md-hidden' : ''; ?>">
        <label for="md_upload_url">Uploaded Video URL:</label>
        <input type="text" name="md_upload_url" id="md_upload_url" value="<?php echo esc_attr( $upload_url ); ?>" />
        <button type="button" class="button md-upload-btn">Select or Upload Video</button>
    </div>

    <script>
        jQuery(document).ready(function($){
            // Toggle fields based on dropdown
            $('#md_video_type').on('change', function() {
                if($(this).val() === 'youtube') {
                    $('#md_youtube_field').show();
                    $('#md_upload_field').hide();
                } else {
                    $('#md_youtube_field').hide();
                    $('#md_upload_field').show();
                }
            }).trigger('change');

            // Media Uploader
            var frame;
            $('.md-upload-btn').on('click', function(e) {
                e.preventDefault();
                if (frame) {
                    frame.open();
                    return;
                }
                frame = wp.media({
                    title: 'Select or Upload Video',
                    button: { text: 'Use this video' },
                    library: { type: 'video' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#md_upload_url').val(attachment.url);
                });
                frame.open();
            });
        });
    </script>
    <?php
}

// 3. Save Meta Box Data
add_action( 'save_post', 'md_save_video_meta_data' );
function md_save_video_meta_data( $post_id ) {
    if ( ! isset( $_POST['md_video_meta_nonce'] ) || ! wp_verify_nonce( $_POST['md_video_meta_nonce'], 'md_save_video_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['md_video_type'] ) ) {
        update_post_meta( $post_id, '_md_video_type', sanitize_text_field( $_POST['md_video_type'] ) );
    }
    if ( isset( $_POST['md_youtube_url'] ) ) {
        update_post_meta( $post_id, '_md_youtube_url', esc_url_raw( $_POST['md_youtube_url'] ) );
    }
    if ( isset( $_POST['md_upload_url'] ) ) {
        update_post_meta( $post_id, '_md_upload_url', esc_url_raw( $_POST['md_upload_url'] ) );
    }
}

// Enqueue media scripts for admin
add_action( 'admin_enqueue_scripts', 'md_enqueue_admin_scripts' );
function md_enqueue_admin_scripts( $hook ) {
    global $post;
    if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
        if ( 'md_video' === $post->post_type ) {
            wp_enqueue_media();
        }
    }
}

// 4. Custom Columns for the List View
add_filter( 'manage_md_video_posts_columns', 'md_set_custom_video_columns' );
function md_set_custom_video_columns( $columns ) {
    $columns['video_type'] = 'Type';
    $columns['video_source'] = 'Source';
    return $columns;
}

add_action( 'manage_md_video_posts_custom_column', 'md_custom_video_column', 10, 2 );
function md_custom_video_column( $column, $post_id ) {
    switch ( $column ) {
        case 'video_type':
            $type = get_post_meta( $post_id, '_md_video_type', true );
            echo ucfirst( $type );
            break;
        case 'video_source':
            $type = get_post_meta( $post_id, '_md_video_type', true );
            if ( $type === 'youtube' ) {
                echo esc_html( get_post_meta( $post_id, '_md_youtube_url', true ) );
            } else {
                echo esc_html( get_post_meta( $post_id, '_md_upload_url', true ) );
            }
            break;
    }
}

// 5. Frontend Shortcode [md_video]
add_shortcode( 'md_video', 'md_video_shortcode' );
function md_video_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'id'     => '',
        'offset' => 0,
    ), $atts, 'md_video' );

    $args = array(
        'post_type'      => 'md_video',
        'posts_per_page' => 1,
        'post_status'    => 'publish'
    );

    if ( ! empty( $atts['id'] ) ) {
        $args['p'] = intval( $atts['id'] );
    } else {
        $args['offset'] = intval( $atts['offset'] );
    }

    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        return ''; // No video found
    }

    $query->the_post();
    $post_id = get_the_ID();
    $video_type = get_post_meta( $post_id, '_md_video_type', true );
    $output = '<div class="md-video-container" style="margin: 20px 0; text-align: center; max-width: 100%;">';

    if ( $video_type === 'youtube' ) {
        $youtube_url = get_post_meta( $post_id, '_md_youtube_url', true );
        // Extract video ID
        preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $youtube_url, $match);
        $video_id = isset($match[1]) ? $match[1] : '';
        
        if ( $video_id ) {
            $output .= '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">';
            $output .= '<iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" src="https://www.youtube.com/embed/' . esc_attr( $video_id ) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            $output .= '</div>';
        }
    } else {
        $upload_url = get_post_meta( $post_id, '_md_upload_url', true );
        if ( $upload_url ) {
            $output .= '<video width="100%" controls style="max-width: 100%; height: auto;">';
            $output .= '<source src="' . esc_url( $upload_url ) . '" type="video/mp4">';
            $output .= 'Your browser does not support the video tag.';
            $output .= '</video>';
        }
    }

    $output .= '</div>';
    wp_reset_postdata();

    return $output;
}
