<?php
require 'wp-load.php';
$page_id = 280;
$post = get_post($page_id);
$content = $post->post_content;

// 1. After Make it modern
$content = preg_replace(
    '/(\[td_flex_block_1[^\]]*custom_title="Make it modern"[^\]]*\])/',
    '$1[md_video offset="0"]',
    $content
);

// 2. After Holiday Recipes
$content = preg_replace(
    '/(\[td_flex_block_5[^\]]*custom_title="Holiday Recipes"[^\]]*\])/',
    '$1[md_video offset="1"]',
    $content
);

// 3. After Most Popular
$content = preg_replace(
    '/(\[td_flex_block_1[^\]]*custom_title="Most Popular"[^\]]*\])/',
    '$1[md_video offset="2"]',
    $content
);

wp_update_post([
    'ID' => $page_id,
    'post_content' => $content
]);

echo "Updated successfully.";
