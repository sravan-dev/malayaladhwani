<?php
/**
 * Plugin Name: Media Upload Manager
 * Description: Allow additional media formats and control the WordPress upload size limit from Settings.
 * Version: 1.0.0
 * Author: Sravan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MUM_OPTION_KEY', 'mum_settings' );

/**
 * Default plugin settings.
 *
 * @return array
 */
function mum_default_settings() {
	return array(
		// 0 means no WordPress-level cap. Server/PHP caps still apply.
		'max_upload_mb'      => 0,
		'allowed_extensions' => 'jpg,jpeg,jpe,jfif,jfi,jif,jiff,png,gif,webp,avif,bmp,tif,tiff,heic,heif,svg,ico,mp4,mov,m4v,webm,mp3,wav,ogg,pdf,txt,csv,json,xml,doc,docx,xls,xlsx,ppt,pptx,zip,rar',
	);
}

/**
 * Get plugin settings merged with defaults.
 *
 * @return array
 */
function mum_get_settings() {
	$saved = get_option( MUM_OPTION_KEY, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return wp_parse_args( $saved, mum_default_settings() );
}

/**
 * Parse and normalize extension list.
 *
 * @param string $raw Raw extension list.
 * @return array
 */
function mum_parse_extensions( $raw ) {
	$raw   = strtolower( (string) $raw );
	$parts = preg_split( '/[\s,]+/', $raw );

	if ( ! is_array( $parts ) ) {
		return array();
	}

	$blocked = array(
		'php',
		'phtml',
		'phar',
		'cgi',
		'pl',
		'py',
		'rb',
		'js',
		'html',
		'htm',
		'sh',
		'bat',
		'cmd',
		'exe',
		'com',
		'dll',
		'msi',
		'ps1',
		'jar',
		'asp',
		'aspx',
		'jsp',
	);

	$extensions = array();

	foreach ( $parts as $extension ) {
		$extension = trim( (string) $extension, " .\t\n\r\0\x0B" );
		if ( '' === $extension ) {
			continue;
		}

		if ( ! preg_match( '/^[a-z0-9]+$/', $extension ) ) {
			continue;
		}

		if ( in_array( $extension, $blocked, true ) ) {
			continue;
		}

		$extensions[] = $extension;
	}

	return array_values( array_unique( $extensions ) );
}

/**
 * Build extension => mime map from WordPress + custom additions.
 *
 * @return array
 */
function mum_extension_to_mime_map() {
	$wp_mimes     = wp_get_mime_types();
	$custom_mimes = array(
		'jfif|jfi|jif|jiff' => 'image/jpeg',
		'avif'         => 'image/avif',
		'heic'         => 'image/heic',
		'heif'         => 'image/heif',
		'webp'         => 'image/webp',
		'csv'          => 'text/csv',
		'json'         => 'application/json',
	);

	$source_map = array_merge( $wp_mimes, $custom_mimes );
	$flat_map   = array();

	foreach ( $source_map as $extensions => $mime ) {
		$each = explode( '|', (string) $extensions );
		foreach ( $each as $extension ) {
			$extension = strtolower( trim( (string) $extension ) );
			if ( '' === $extension ) {
				continue;
			}
			$flat_map[ $extension ] = $mime;
		}
	}

	return $flat_map;
}

register_activation_hook(
	__FILE__,
	function() {
		if ( false === get_option( MUM_OPTION_KEY, false ) ) {
			add_option( MUM_OPTION_KEY, mum_default_settings() );
		}
	}
);

/**
 * Add settings page.
 */
function mum_add_settings_page() {
	add_options_page(
		'Media Upload Manager',
		'Media Upload Manager',
		'manage_options',
		'mum-settings',
		'mum_render_settings_page'
	);
}
add_action( 'admin_menu', 'mum_add_settings_page' );

/**
 * Register settings and fields.
 */
function mum_register_settings() {
	register_setting(
		'mum_settings_group',
		MUM_OPTION_KEY,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'mum_sanitize_settings',
			'default'           => mum_default_settings(),
		)
	);

	add_settings_section(
		'mum_main_section',
		'Upload Controls',
		'__return_false',
		'mum-settings'
	);

	add_settings_field(
		'mum_max_upload_mb',
		'Max Upload Size (MB)',
		'mum_render_max_upload_mb_field',
		'mum-settings',
		'mum_main_section'
	);

	add_settings_field(
		'mum_allowed_extensions',
		'Allowed Extensions',
		'mum_render_allowed_extensions_field',
		'mum-settings',
		'mum_main_section'
	);
}
add_action( 'admin_init', 'mum_register_settings' );

/**
 * Sanitize saved settings.
 *
 * @param array $input Raw setting input.
 * @return array
 */
function mum_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();

	$max_upload_mb = isset( $input['max_upload_mb'] ) ? absint( $input['max_upload_mb'] ) : 0;
	if ( $max_upload_mb > 102400 ) {
		$max_upload_mb = 102400;
	}

	$extensions_raw = isset( $input['allowed_extensions'] ) ? (string) $input['allowed_extensions'] : '';
	$extensions     = mum_parse_extensions( $extensions_raw );

	if ( empty( $extensions ) ) {
		$extensions = mum_parse_extensions( mum_default_settings()['allowed_extensions'] );
	}

	return array(
		'max_upload_mb'      => $max_upload_mb,
		'allowed_extensions' => implode( ',', $extensions ),
	);
}

/**
 * Get effective server-side upload cap in bytes.
 *
 * @return int
 */
function mum_get_server_cap_bytes() {
	$limits = array();

	foreach ( array( 'upload_max_filesize', 'post_max_size' ) as $ini_key ) {
		$raw = ini_get( $ini_key );
		if ( false === $raw || '' === $raw ) {
			continue;
		}
		$bytes = wp_convert_hr_to_bytes( $raw );
		if ( $bytes > 0 ) {
			$limits[] = $bytes;
		}
	}

	if ( empty( $limits ) ) {
		return 0;
	}

	return min( $limits );
}

/**
 * Render max size field.
 */
function mum_render_max_upload_mb_field() {
	$settings         = mum_get_settings();
	$value            = isset( $settings['max_upload_mb'] ) ? (int) $settings['max_upload_mb'] : 0;
	$server_cap_bytes = mum_get_server_cap_bytes();

	echo '<input type="number" min="0" max="102400" step="1" name="' . esc_attr( MUM_OPTION_KEY ) . '[max_upload_mb]" value="' . esc_attr( $value ) . '" class="small-text" />';
	echo '<p class="description">Set <strong>0</strong> for no WordPress-level limit.</p>';

	if ( $server_cap_bytes > 0 ) {
		echo '<p class="description">Current server cap (PHP): <strong>' . esc_html( size_format( $server_cap_bytes ) ) . '</strong>. Uploads above this still fail unless server limits are increased.</p>';
	}
}

/**
 * Render extension field.
 */
function mum_render_allowed_extensions_field() {
	$settings = mum_get_settings();
	$value    = isset( $settings['allowed_extensions'] ) ? (string) $settings['allowed_extensions'] : '';

	echo '<textarea name="' . esc_attr( MUM_OPTION_KEY ) . '[allowed_extensions]" rows="4" cols="70" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
	echo '<p class="description">Comma or space separated. Example: <code>webp jfif jif jfi png avif heic pdf mp4</code></p>';
}

/**
 * Render settings page.
 */
function mum_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>Media Upload Manager</h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'mum_settings_group' );
			do_settings_sections( 'mum-settings' );
			submit_button( 'Save Changes' );
			?>
		</form>
	</div>
	<?php
}

/**
 * Set upload size limit.
 *
 * @param int $size Existing size in bytes.
 * @return int
 */
function mum_upload_size_limit( $size ) {
	$settings = mum_get_settings();
	$mb       = isset( $settings['max_upload_mb'] ) ? (int) $settings['max_upload_mb'] : 0;

	if ( $mb <= 0 ) {
		return PHP_INT_MAX;
	}

	$bytes = $mb * MB_IN_BYTES;
	return ( $bytes > 0 ) ? $bytes : $size;
}
add_filter( 'upload_size_limit', 'mum_upload_size_limit', 20 );

/**
 * Allow configured extensions in uploader.
 *
 * @param array $mimes Existing mime map.
 * @return array
 */
function mum_upload_mimes( $mimes ) {
	$settings   = mum_get_settings();
	$extensions = mum_parse_extensions( $settings['allowed_extensions'] );
	$mime_map   = mum_extension_to_mime_map();

	foreach ( $extensions as $extension ) {
		$mimes[ $extension ] = isset( $mime_map[ $extension ] ) ? $mime_map[ $extension ] : 'application/octet-stream';
	}

	return $mimes;
}
add_filter( 'upload_mimes', 'mum_upload_mimes', 20 );

/**
 * Keep extension + mime when WordPress cannot infer uncommon extensions.
 *
 * @param array  $types        Values for the extension, mime type, and corrected filename.
 * @param string $file         Full path to the file.
 * @param string $filename     The name of the file.
 * @param array  $mimes        Key is the file extension with value as the mime type.
 * @return array
 */
function mum_check_filetype_and_ext( $types, $file, $filename, $mimes ) {
	if ( ! empty( $types['ext'] ) || ! empty( $types['type'] ) ) {
		return $types;
	}

	$extension = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
	if ( '' === $extension ) {
		return $types;
	}

	$settings   = mum_get_settings();
	$extensions = mum_parse_extensions( $settings['allowed_extensions'] );

	if ( ! in_array( $extension, $extensions, true ) ) {
		return $types;
	}

	$mime_map = mum_extension_to_mime_map();

	$types['ext']  = $extension;
	$types['type'] = isset( $mime_map[ $extension ] ) ? $mime_map[ $extension ] : 'application/octet-stream';

	return $types;
}
add_filter( 'wp_check_filetype_and_ext', 'mum_check_filetype_and_ext', 10, 4 );
