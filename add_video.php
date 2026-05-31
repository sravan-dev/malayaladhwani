<?php
require 'wp-load.php';
$post_id = wp_insert_post([
    'post_title' => 'Sample YouTube Video',
    'post_type' => 'md_video',
    'post_status' => 'publish'
]);
update_post_meta($post_id, '_md_video_type', 'youtube');
update_post_meta($post_id, '_md_youtube_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
echo "Video created with ID: $post_id";
