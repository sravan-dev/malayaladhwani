<?php
/**
 * Plugin Name: NMA Magazines
 * Description: Adds a Magazines section under News with title, document upload (PDF/DOC/DOCX), preview, and publish workflow.
 * Version: 1.0.0
 * Author: Sravan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NMA_MAGAZINES_POST_TYPE', 'nma_magazine' );
define( 'NMA_MAGAZINES_META_KEY', '_nma_magazine_document_id' );
define( 'NMA_MAGAZINES_BACKFILL_OPTION', 'nma_magazines_category_backfill_done' );

/**
 * Register the Magazines post type.
 */
function nma_magazines_register_post_type() {
	$labels = array(
		'name'                  => __( 'Magazines', 'nma-magazines' ),
		'singular_name'         => __( 'Magazine', 'nma-magazines' ),
		'menu_name'             => __( 'Magazines', 'nma-magazines' ),
		'name_admin_bar'        => __( 'Magazine', 'nma-magazines' ),
		'add_new'               => __( 'Add Magazine', 'nma-magazines' ),
		'add_new_item'          => __( 'Add New Magazine', 'nma-magazines' ),
		'new_item'              => __( 'New Magazine', 'nma-magazines' ),
		'edit_item'             => __( 'Edit Magazine', 'nma-magazines' ),
		'view_item'             => __( 'View Magazine', 'nma-magazines' ),
		'all_items'             => __( 'All Magazines', 'nma-magazines' ),
		'search_items'          => __( 'Search Magazines', 'nma-magazines' ),
		'not_found'             => __( 'No magazines found.', 'nma-magazines' ),
		'not_found_in_trash'    => __( 'No magazines found in Trash.', 'nma-magazines' ),
		'featured_image'        => __( 'Magazine Cover', 'nma-magazines' ),
		'set_featured_image'    => __( 'Set magazine cover', 'nma-magazines' ),
		'remove_featured_image' => __( 'Remove magazine cover', 'nma-magazines' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		// We add explicit submenu links under the custom News menu in admin_menu.
		'show_in_menu'       => false,
		'show_in_nav_menus'  => true,
		'show_in_admin_bar'  => true,
		'has_archive'        => true,
		'rewrite'            => array( 'slug' => 'magazines' ),
		'menu_position'      => null,
		'supports'           => array( 'title', 'thumbnail' ),
		'taxonomies'         => array( 'category' ),
		'capability_type'    => 'post',
		'map_meta_cap'       => true,
	);

	register_post_type( NMA_MAGAZINES_POST_TYPE, $args );
}
add_action( 'init', 'nma_magazines_register_post_type' );

/**
 * Get or create the default "Magazines" category term id.
 *
 * @return int
 */
function nma_magazines_get_default_category_id() {
	$term = get_term_by( 'slug', 'magazines', 'category' );
	if ( $term && ! is_wp_error( $term ) ) {
		return (int) $term->term_id;
	}

	$term = get_term_by( 'name', 'Magazines', 'category' );
	if ( $term && ! is_wp_error( $term ) ) {
		return (int) $term->term_id;
	}

	$inserted = wp_insert_term(
		'Magazines',
		'category',
		array(
			'slug' => 'magazines',
		)
	);

	if ( is_wp_error( $inserted ) || empty( $inserted['term_id'] ) ) {
		return 0;
	}

	return (int) $inserted['term_id'];
}

/**
 * Ensure the default category exists.
 */
function nma_magazines_ensure_default_category() {
	nma_magazines_get_default_category_id();
}
add_action( 'init', 'nma_magazines_ensure_default_category', 20 );

/**
 * Backfill existing magazine posts with the default Magazines category once.
 */
function nma_magazines_backfill_existing_posts_category() {
	if ( get_option( NMA_MAGAZINES_BACKFILL_OPTION ) ) {
		return;
	}

	$default_category_id = nma_magazines_get_default_category_id();
	if ( ! $default_category_id ) {
		return;
	}

	$ids = get_posts(
		array(
			'post_type'      => NMA_MAGAZINES_POST_TYPE,
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);

	if ( ! empty( $ids ) ) {
		foreach ( $ids as $post_id ) {
			$current_categories = wp_get_post_terms(
				$post_id,
				'category',
				array(
					'fields' => 'ids',
				)
			);

			if ( is_wp_error( $current_categories ) ) {
				continue;
			}

			if ( ! in_array( $default_category_id, $current_categories, true ) ) {
				$current_categories[] = $default_category_id;
				$current_categories   = array_values( array_unique( array_map( 'intval', $current_categories ) ) );
				wp_set_post_terms( $post_id, $current_categories, 'category', false );
			}
		}
	}

	update_option( NMA_MAGAZINES_BACKFILL_OPTION, 1, false );
}
add_action( 'admin_init', 'nma_magazines_backfill_existing_posts_category' );

/**
 * Add Magazines links under News.
 */
function nma_magazines_register_news_submenus() {
	add_submenu_page(
		'nma-list-news',
		__( 'Magazines', 'nma-magazines' ),
		__( 'Magazines', 'nma-magazines' ),
		'edit_posts',
		'edit.php?post_type=' . NMA_MAGAZINES_POST_TYPE
	);

	add_submenu_page(
		'nma-list-news',
		__( 'Add Magazine', 'nma-magazines' ),
		__( 'Add Magazine', 'nma-magazines' ),
		'edit_posts',
		'post-new.php?post_type=' . NMA_MAGAZINES_POST_TYPE
	);
}
add_action( 'admin_menu', 'nma_magazines_register_news_submenus', 20 );

/**
 * Include magazine CPT posts in the "Magazines" category archive.
 *
 * @param WP_Query $query Query object.
 */
function nma_magazines_include_cpt_in_magazines_category( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_category( 'magazines' ) ) {
		return;
	}

	$post_types = $query->get( 'post_type' );

	if ( empty( $post_types ) ) {
		$query->set( 'post_type', array( 'post', NMA_MAGAZINES_POST_TYPE ) );
		return;
	}

	$post_types = (array) $post_types;
	if ( ! in_array( NMA_MAGAZINES_POST_TYPE, $post_types, true ) ) {
		$post_types[] = NMA_MAGAZINES_POST_TYPE;
	}

	$query->set( 'post_type', $post_types );
}
add_action( 'pre_get_posts', 'nma_magazines_include_cpt_in_magazines_category' );

/**
 * Use a grid archive template for magazine preview pages.
 *
 * @param string $template Theme-selected template path.
 * @return string
 */
function nma_magazines_template_include( $template ) {
	$is_magazines_category = is_category( 'magazines' );
	$is_magazines_archive  = is_post_type_archive( NMA_MAGAZINES_POST_TYPE );

	if ( ! $is_magazines_category && ! $is_magazines_archive ) {
		return $template;
	}

	$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/magazines-grid.php';
	if ( file_exists( $plugin_template ) ) {
		return $plugin_template;
	}

	return $template;
}
add_filter( 'template_include', 'nma_magazines_template_include', 99 );

/**
 * Flush rewrite rules on activation/deactivation.
 */
function nma_magazines_activate() {
	nma_magazines_register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'nma_magazines_activate' );

function nma_magazines_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'nma_magazines_deactivate' );

/**
 * Add document upload meta box.
 */
function nma_magazines_add_meta_boxes() {
	add_meta_box(
		'nma-magazine-document',
		__( 'Magazine Document', 'nma-magazines' ),
		'nma_magazines_render_document_metabox',
		NMA_MAGAZINES_POST_TYPE,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'nma_magazines_add_meta_boxes' );

/**
 * Hide unrelated CPT settings metabox on Magazine screens.
 */
function nma_magazines_hide_unwanted_metaboxes() {
	global $wp_meta_boxes;

	if ( empty( $wp_meta_boxes[ NMA_MAGAZINES_POST_TYPE ] ) || ! is_array( $wp_meta_boxes[ NMA_MAGAZINES_POST_TYPE ] ) ) {
		return;
	}

	foreach ( $wp_meta_boxes[ NMA_MAGAZINES_POST_TYPE ] as $context => $priorities ) {
		if ( ! is_array( $priorities ) ) {
			continue;
		}

		foreach ( $priorities as $priority => $metaboxes ) {
			if ( ! is_array( $metaboxes ) ) {
				continue;
			}

			foreach ( $metaboxes as $metabox_id => $metabox ) {
				$title = isset( $metabox['title'] ) ? strtolower( wp_strip_all_tags( (string) $metabox['title'] ) ) : '';
				$id    = strtolower( (string) $metabox_id );

				if (
					false !== strpos( $title, 'custom post type - settings' ) ||
					false !== strpos( $title, 'custom post type settings' ) ||
					false !== strpos( $id, 'custom-post-type' ) ||
					false !== strpos( $id, 'custom_post_type' ) ||
					false !== strpos( $id, 'cpt-settings' )
				) {
					unset( $wp_meta_boxes[ NMA_MAGAZINES_POST_TYPE ][ $context ][ $priority ][ $metabox_id ] );
				}
			}
		}
	}
}
add_action( 'add_meta_boxes_' . NMA_MAGAZINES_POST_TYPE, 'nma_magazines_hide_unwanted_metaboxes', 100 );

/**
 * Allowed document mime types.
 *
 * @return array
 */
function nma_magazines_allowed_mimes() {
	return array(
		'application/pdf',
		'application/msword',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	);
}

/**
 * Render upload metabox content.
 *
 * @param WP_Post $post Post object.
 */
function nma_magazines_render_document_metabox( $post ) {
	wp_nonce_field( 'nma_magazine_save_document', 'nma_magazine_document_nonce' );

	$attachment_id = (int) get_post_meta( $post->ID, NMA_MAGAZINES_META_KEY, true );
	$file_url      = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
	$file_mime     = $attachment_id ? get_post_mime_type( $attachment_id ) : '';

	?>
	<p><?php esc_html_e( 'Upload a document file for this magazine (PDF, DOC, DOCX).', 'nma-magazines' ); ?></p>

	<input type="hidden" id="nma_magazine_document_id" name="nma_magazine_document_id" value="<?php echo esc_attr( $attachment_id ); ?>" />
	<input type="text" id="nma_magazine_document_url" class="regular-text" value="<?php echo esc_url( $file_url ); ?>" readonly />
	<button type="button" class="button" id="nma_magazine_upload_button"><?php esc_html_e( 'Upload / Select File', 'nma-magazines' ); ?></button>
	<button type="button" class="button" id="nma_magazine_remove_button" <?php echo $attachment_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove File', 'nma-magazines' ); ?></button>

	<div id="nma_magazine_preview_wrapper" style="margin-top:16px;">
		<?php if ( $attachment_id && $file_url ) : ?>
			<p><strong><?php esc_html_e( 'Current File Preview', 'nma-magazines' ); ?></strong></p>
			<?php if ( 'application/pdf' === $file_mime ) : ?>
				<iframe src="<?php echo esc_url( $file_url ); ?>" style="width:100%;height:520px;border:1px solid #ccd0d4;" title="<?php esc_attr_e( 'Magazine PDF Preview', 'nma-magazines' ); ?>"></iframe>
			<?php else : ?>
				<p>
					<a class="button button-secondary" href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Preview Document in New Tab', 'nma-magazines' ); ?>
					</a>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Enqueue media uploader script for magazine edit pages.
 *
 * @param string $hook Current admin hook.
 */
function nma_magazines_admin_enqueue( $hook ) {
	if ( ! in_array( $hook, array( 'post-new.php', 'post.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || NMA_MAGAZINES_POST_TYPE !== $screen->post_type ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script( 'jquery' );

	$allowed_mimes_json = wp_json_encode( nma_magazines_allowed_mimes() );
	if ( ! $allowed_mimes_json ) {
		$allowed_mimes_json = '[]';
	}

	$default_category_id = nma_magazines_get_default_category_id();

	$inline_js = "
	(function($){
		let frame;
		const allowedMimes = $allowed_mimes_json;
		const defaultCategoryId = " . (int) $default_category_id . ";

		function isAllowedMime(mime){
			return allowedMimes.indexOf(mime || '') !== -1;
		}

		function setPreview(url, mime){
			const wrapper = $('#nma_magazine_preview_wrapper');
			if (!url) {
				wrapper.html('');
				return;
			}

			if (mime === 'application/pdf') {
				wrapper.html(
					'<p><strong>Current File Preview</strong></p>' +
					'<iframe src=\"' + url + '\" style=\"width:100%;height:520px;border:1px solid #ccd0d4;\" title=\"Magazine PDF Preview\"></iframe>'
				);
				return;
			}

			wrapper.html(
				'<p><strong>Current File Preview</strong></p>' +
				'<p><a class=\"button button-secondary\" href=\"' + url + '\" target=\"_blank\" rel=\"noopener noreferrer\">Preview Document in New Tab</a></p>'
			);
		}

		function hideUnwantedMetaboxes() {
			$('.postbox').each(function(){
				const title = ($(this).find('.postbox-header h2, .hndle').first().text() || '').trim().toLowerCase();
				if (title === 'custom post type - settings' || title === 'custom post type settings') {
					$(this).remove();
				}
			});
		}

		function ensureDefaultCategoryChecked() {
			if (!defaultCategoryId) {
				return;
			}

			const checkboxes = $('#categorychecklist input[type=checkbox]');
			if (!checkboxes.length) {
				return;
			}

			const checked = checkboxes.filter(':checked');
			if (checked.length) {
				return;
			}

			$('#in-category-' + defaultCategoryId).prop('checked', true);
		}

		$(function(){
			hideUnwantedMetaboxes();
			setTimeout(hideUnwantedMetaboxes, 300);
			ensureDefaultCategoryChecked();

			$(document).on('click', '#nma_magazine_upload_button', function(e){
				e.preventDefault();

				if (typeof wp === 'undefined' || !wp.media) {
					window.alert('Media uploader is not available. Please refresh and try again.');
					return;
				}

				if (frame) {
					frame.open();
					return;
				}

				frame = wp.media({
					title: 'Select Magazine Document',
					button: { text: 'Use this file' },
					multiple: false
				});

				frame.on('select', function(){
					const attachment = frame.state().get('selection').first().toJSON();

					if (!isAllowedMime(attachment.mime)) {
						window.alert('Please select only PDF, DOC, or DOCX file.');
						return;
					}

					$('#nma_magazine_document_id').val(attachment.id || '');
					$('#nma_magazine_document_url').val(attachment.url || '');
					$('#nma_magazine_remove_button').show();
					setPreview(attachment.url || '', attachment.mime || '');
				});

				frame.open();
			});

			$(document).on('click', '#nma_magazine_remove_button', function(e){
				e.preventDefault();
				$('#nma_magazine_document_id').val('');
				$('#nma_magazine_document_url').val('');
				$(this).hide();
				setPreview('', '');
			});
		});
	})(jQuery);
	";

	wp_add_inline_script( 'jquery', $inline_js );
}
add_action( 'admin_enqueue_scripts', 'nma_magazines_admin_enqueue' );

/**
 * Save uploaded document attachment ID.
 *
 * @param int $post_id Post ID.
 */
function nma_magazines_save_document( $post_id ) {
	if ( empty( $_POST['nma_magazine_document_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nma_magazine_document_nonce'] ) ), 'nma_magazine_save_document' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$post_type = get_post_type( $post_id );
	if ( NMA_MAGAZINES_POST_TYPE !== $post_type ) {
		return;
	}

	$attachment_id = isset( $_POST['nma_magazine_document_id'] ) ? absint( $_POST['nma_magazine_document_id'] ) : 0;

	if ( 0 === $attachment_id ) {
		delete_post_meta( $post_id, NMA_MAGAZINES_META_KEY );
		return;
	}

	$mime_type = get_post_mime_type( $attachment_id );
	if ( ! in_array( $mime_type, nma_magazines_allowed_mimes(), true ) ) {
		return;
	}

	update_post_meta( $post_id, NMA_MAGAZINES_META_KEY, $attachment_id );
}
add_action( 'save_post', 'nma_magazines_save_document' );

/**
 * Ensure each magazine post is assigned to the "Magazines" category.
 *
 * @param int $post_id Post ID.
 */
function nma_magazines_assign_default_category( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( NMA_MAGAZINES_POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}

	$default_category_id = nma_magazines_get_default_category_id();
	if ( ! $default_category_id ) {
		return;
	}

	$current_categories = wp_get_post_terms(
		$post_id,
		'category',
		array(
			'fields' => 'ids',
		)
	);

	if ( is_wp_error( $current_categories ) ) {
		return;
	}

	if ( ! in_array( $default_category_id, $current_categories, true ) ) {
		$current_categories[] = $default_category_id;
		$current_categories   = array_values( array_unique( array_map( 'intval', $current_categories ) ) );
		wp_set_post_terms( $post_id, $current_categories, 'category', false );
	}
}
add_action( 'save_post_' . NMA_MAGAZINES_POST_TYPE, 'nma_magazines_assign_default_category', 20 );

/**
 * Add a document column in list table.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function nma_magazines_columns( $columns ) {
	$new_columns = array();

	foreach ( $columns as $key => $label ) {
		$new_columns[ $key ] = $label;
		if ( 'title' === $key ) {
			$new_columns['nma_document'] = __( 'Document', 'nma-magazines' );
		}
	}

	return $new_columns;
}
add_filter( 'manage_' . NMA_MAGAZINES_POST_TYPE . '_posts_columns', 'nma_magazines_columns' );

/**
 * Render custom document column.
 *
 * @param string $column Column key.
 * @param int    $post_id Post ID.
 */
function nma_magazines_column_content( $column, $post_id ) {
	if ( 'nma_document' !== $column ) {
		return;
	}

	$attachment_id = (int) get_post_meta( $post_id, NMA_MAGAZINES_META_KEY, true );
	if ( ! $attachment_id ) {
		echo esc_html__( 'No file', 'nma-magazines' );
		return;
	}

	$url = wp_get_attachment_url( $attachment_id );
	if ( ! $url ) {
		echo esc_html__( 'No file', 'nma-magazines' );
		return;
	}

	echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Preview', 'nma-magazines' ) . '</a>';
}
add_action( 'manage_' . NMA_MAGAZINES_POST_TYPE . '_posts_custom_column', 'nma_magazines_column_content', 10, 2 );

/**
 * Keep News menu highlighted on Magazine screens.
 *
 * @param string $parent_file Parent menu slug.
 * @return string
 */
function nma_magazines_parent_file( $parent_file ) {
	global $current_screen;

	if ( $current_screen && NMA_MAGAZINES_POST_TYPE === $current_screen->post_type ) {
		return 'nma-list-news';
	}

	return $parent_file;
}
add_filter( 'parent_file', 'nma_magazines_parent_file' );

/**
 * Keep correct submenu highlighted on Magazine screens.
 *
 * @param string $submenu_file Submenu slug.
 * @return string
 */
function nma_magazines_submenu_file( $submenu_file ) {
	global $current_screen;

	if ( ! $current_screen || NMA_MAGAZINES_POST_TYPE !== $current_screen->post_type ) {
		return $submenu_file;
	}

	if ( 'post' === $current_screen->base ) {
		return 'post-new.php?post_type=' . NMA_MAGAZINES_POST_TYPE;
	}

	return 'edit.php?post_type=' . NMA_MAGAZINES_POST_TYPE;
}
add_filter( 'submenu_file', 'nma_magazines_submenu_file' );

/**
 * Show document preview block on front-end single magazine view.
 *
 * @param string $content Post content.
 * @return string
 */
function nma_magazines_single_content( $content ) {
	if ( ! is_singular( NMA_MAGAZINES_POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$post_id       = get_the_ID();
	$attachment_id = (int) get_post_meta( $post_id, NMA_MAGAZINES_META_KEY, true );
	if ( ! $attachment_id ) {
		return $content;
	}

	$url  = wp_get_attachment_url( $attachment_id );
	$mime = get_post_mime_type( $attachment_id );
	if ( ! $url ) {
		return $content;
	}

	$output  = '<div class="nma-magazine-file" style="margin-top:20px;">';
	$output .= '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open Magazine Document', 'nma-magazines' ) . '</a></p>';

	if ( 'application/pdf' === $mime ) {
		$output .= '<iframe src="' . esc_url( $url ) . '" style="width:100%;min-height:700px;border:1px solid #ddd;" title="' . esc_attr__( 'Magazine PDF', 'nma-magazines' ) . '"></iframe>';
	}

	$output .= '</div>';

	return $content . $output;
}
add_filter( 'the_content', 'nma_magazines_single_content' );
