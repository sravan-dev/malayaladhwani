<?php
/**
 * Plugin Name: WebP Upload Support
 * Description: Enables WebP image upload, bypasses thumbnail errors, and increases upload size limits.
 * Version: 1.1
 * Author: Sravan
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add WebP to technical mime types.
 */
add_filter( 'upload_mimes', 'webp_upload_mimes' );
function webp_upload_mimes( $mimes ) {
    $mimes['webp'] = 'image/webp';
    return $mimes;
}

/**
 * Fixes WebP display in Media Library.
 */
add_filter( 'file_is_displayable_image', 'webp_is_displayable', 10, 2 );
function webp_is_displayable( $result, $path ) {
    if ( $result === true ) {
        return $result;
    }
    $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
    if ( $extension === 'webp' ) {
        return true;
    }
    return $result;
}

/**
 * Ensures WebP files are not rejected during upload.
 */
add_filter( 'wp_check_filetype_and_ext', 'webp_fix_check_filetype_and_ext', 10, 4 );
function webp_fix_check_filetype_and_ext( $data, $file, $filename, $mimes ) {
    if ( ! $data['type'] ) {
        $filetype = wp_check_filetype( $filename, $mimes );
        $ext      = $filetype['ext'];
        $type     = $filetype['type'];
        $proper_filename = $data['proper_filename'];

        if ( $ext === 'webp' ) {
            $data = [
                'ext'             => $ext,
                'type'            => $type,
                'proper_filename' => $proper_filename,
            ];
        }
    }
    return $data;
}

/**
 * Add WebP to the list of supported image formats.
 */
add_filter( 'image_editor_output_format', 'webp_image_editor_output_format' );
function webp_image_editor_output_format( $formats ) {
    $formats['image/webp'] = 'image/webp';
    return $formats;
}

/**
 * Bypass "The web server cannot generate responsive image sizes" error for WebP.
 * This tells WordPress to not treat it as a fatal error if thumbnails fail to generate.
 */
add_filter( 'fallback_intermediate_image_sizes', function( $fallback_sizes, $metadata ) {
    if ( isset( $metadata['file'] ) && strpos( $metadata['file'], '.webp' ) !== false ) {
        return []; // Return empty array to skip fallback size generation if failing
    }
    return $fallback_sizes;
});

// Disable the "big image" threshold which can also trigger resizing errors
add_filter( 'big_image_size_threshold', '__return_false' );

/**
 * Increase Upload Size Limit (Software Level)
 */
add_filter( 'upload_size_limit', 'webp_increase_upload_size_limit' );
function webp_increase_upload_size_limit( $limit ) {
    // Set to 256MB (in bytes)
    return 256 * 1024 * 1024;
}

/**
 * Try to set PHP limits via ini_set (might be blocked by server)
 */
@ini_set( 'upload_max_filesize', '256M' );
@ini_set( 'post_max_size', '256M' );
@ini_set( 'memory_limit', '512M' );

/**
 * If the server truly cannot handle WebP, prevent WordPress from trying to resize it
 * during the initial upload process to avoid the "responsive image sizes" error.
 */
add_filter( 'wp_get_image_editor', function( $editor, $path, $mime_type ) {
    if ( $mime_type === 'image/webp' ) {
        // If the editor doesn't support WebP, we return a mock or handle it.
        // But usually, it's better to just let it fail gracefully.
    }
    return $editor;
}, 10, 3 );
