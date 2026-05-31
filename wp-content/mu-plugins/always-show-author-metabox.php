<?php
/**
 * Plugin Name: Always Show Author Metabox
 * Description: Author metabox എപ്പോഴും visible ആക്കുന്നു + Reporter Name field
 */

// Mobile-ൽ logo double border fix + header layout tweaks (logo left, wordmarks centered, menu inline, trending bar styled)
add_action('wp_head', 'mad_logo_mobile_fix');
function mad_logo_mobile_fix() {
    echo '<style>
/* ======= Desktop header layout (matches reference screenshot) ======= */
@media (min-width: 1024px) {
    /* Header container: clean white background, thin bottom border */
    .td-header-wrap,
    .td-header-wrap .td-header-main-menu,
    .td-header-wrap .tdb-header-horiz-menu {
        background-color: #ffffff !important;
    }

    /* Logo column: left aligned, larger visual weight */
    .tdb_header_logo,
    .tdb_header_logo .tdb-block-inner {
        text-align: left !important;
        justify-content: flex-start !important;
        display: flex !important;
        align-items: center !important;
    }
    .tdb_header_logo .tdb-logo-img,
    .tdb-logo-img {
        max-height: 70px !important;
        width: auto !important;
    }

    /* Center wordmark blocks (Malayalam + English) — assumes they sit in columns next to logo */
    .td-header-wrap .vc_column_container.td_center_logo,
    .td-header-wrap .tdb-header-logo--center,
    .td-header-wrap .tdb_header_logo.td-logo-center {
        text-align: center !important;
        justify-content: center !important;
    }

    /* Main menu row: horizontal, evenly spaced, with active underline */
    .td-header-wrap .tdb-block-menu,
    .td-header-wrap .sf-menu {
        display: flex !important;
        justify-content: flex-start !important;
        gap: 6px !important;
    }
    .td-header-wrap .tdb-menu li > a,
    .td-header-wrap .sf-menu > li > a {
        text-transform: uppercase !important;
        font-weight: 600 !important;
        letter-spacing: 0.4px !important;
        font-size: 14px !important;
        padding: 14px 12px !important;
        color: #222 !important;
        border-bottom: 3px solid transparent !important;
    }
    .td-header-wrap .tdb-menu li.current-menu-item > a,
    .td-header-wrap .sf-menu > li.current-menu-item > a,
    .td-header-wrap .tdb-menu li > a:hover,
    .td-header-wrap .sf-menu > li > a:hover {
        color: #0a73d6 !important;
        border-bottom-color: #0a73d6 !important;
    }
}

/* ======= Trending Now bar (dark badge + light strip) ======= */
.td-trending-now-wrapper,
.tdb_module_trending_now {
    background-color: #f7f7f7 !important;
    border-top: 1px solid #e5e5e5 !important;
    border-bottom: 1px solid #e5e5e5 !important;
}
.td-trending-now-title,
.tdb-trending-now-title {
    background-color: #1a1a1a !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    padding: 8px 14px !important;
}

/* ======= Mobile: keep prior overflow fixes ======= */
@media (max-width: 767px) {
    .td-header-wrap,
    .td-header-wrap .tdc_zone,
    .td-header-wrap .vc_row,
    .td-header-wrap .wpb_row,
    .td-header-wrap .vc_column_container,
    .td-header-wrap .vc_column-inner,
    .td-header-wrap .wpb_wrapper,
    .tdb_header_logo,
    .tdb_header_logo .tdb-block-inner,
    .tdb_header_logo .tdb-logo-a {
        overflow: visible !important;
    }
    .tdb-logo-img {
        max-width: 100%;
        height: auto;
    }
}
</style>';
}

// Author + Post Settings metabox remove ചെ‌യ്യുന്നു
add_action('add_meta_boxes', 'mad_remove_unwanted_metaboxes', 99);
function mad_remove_unwanted_metaboxes() {
    remove_meta_box('authordiv', 'post', 'normal');
    remove_meta_box('authordiv', 'post', 'side');
    remove_meta_box('authordiv', 'page', 'normal');
    remove_meta_box('authordiv', 'page', 'side');
}

// Post Settings (WPAlchemy) CSS വഴി force hide
add_action('admin_head-post.php', 'mad_hide_post_settings_metabox');
add_action('admin_head-post-new.php', 'mad_hide_post_settings_metabox');
function mad_hide_post_settings_metabox() {
    echo '<style>#td_post_theme_settings, #authordiv { display: none !important; }</style>';
}

// Reporter Name metabox add ചെ‌യ്യുന്നു
add_action('add_meta_boxes', 'mad_add_reporter_name_metabox');
function mad_add_reporter_name_metabox() {
    add_meta_box(
        'mad_reporter_name',
        'Reporter Name',
        'mad_reporter_name_metabox_html',
        array('post', 'page'),
        'side',
        'high'
    );
}

function mad_reporter_name_metabox_html($post) {
    $reporter_name = get_post_meta($post->ID, '_mad_reporter_name', true);
    $reporter_date = get_post_meta($post->ID, '_mad_reporter_date', true);
    if (empty($reporter_date)) {
        $reporter_date = current_time('Y-m-d');
    }
    wp_nonce_field('mad_reporter_name_nonce', 'mad_reporter_name_nonce');
    ?>
    <p style="margin-bottom:4px;color:#666;font-size:12px;">Select ചെ‌യ്യുക അല്ലെങ്കിൽ manually type ചെ‌യ്യുക:</p>
    <select id="mad_reporter_select" style="width:100%;margin-bottom:6px;">
        <option value="">-- Select Reporter --</option>
        <?php
        $users = get_users(array('orderby' => 'display_name', 'fields' => array('ID', 'display_name')));
        foreach ($users as $user) {
            $selected = ($reporter_name === $user->display_name) ? 'selected' : '';
            echo '<option value="' . esc_attr($user->display_name) . '" ' . $selected . '>' . esc_html($user->display_name) . '</option>';
        }
        ?>
    </select>
    <input type="text"
           id="mad_reporter_name_field"
           name="mad_reporter_name"
           value="<?php echo esc_attr($reporter_name); ?>"
           placeholder="അല്ലെങ്കിൽ ഇവിടെ type ചെ‌യ്യുക..."
           style="width:100%;box-sizing:border-box;margin-bottom:10px;" />

    <p style="margin-bottom:4px;color:#666;font-size:12px;">തീയതി:</p>
    <input type="date"
           id="mad_reporter_date"
           name="mad_reporter_date"
           value="<?php echo esc_attr($reporter_date); ?>"
           style="width:100%;box-sizing:border-box;" />

    <script>
    jQuery(document).ready(function($) {
        $('#mad_reporter_select').on('change', function() {
            if ($(this).val() !== '') {
                $('#mad_reporter_name_field').val($(this).val());
            }
        });
    });
    </script>
    <?php
}

// Filter the_author to use reporter name when set (priority 20 to override other plugins)
add_filter('the_author', 'mad_filter_reporter_name_author', 20);
function mad_filter_reporter_name_author($display_name) {
    $post = get_post();
    if (!($post instanceof WP_Post)) {
        return $display_name;
    }
    $reporter_name = get_post_meta($post->ID, '_mad_reporter_name', true);
    return !empty($reporter_name) ? $reporter_name : $display_name;
}

// Enqueue media uploader on post edit pages
add_action('admin_enqueue_scripts', 'mad_enqueue_media_on_post_edit');
function mad_enqueue_media_on_post_edit($hook) {
    if (in_array($hook, array('post.php', 'post-new.php'), true)) {
        wp_enqueue_media();
    }
}

// Author Image metabox
add_action('add_meta_boxes', 'mad_add_author_image_metabox');
function mad_add_author_image_metabox() {
    add_meta_box(
        'mad_author_image',
        'Author Image',
        'mad_author_image_metabox_html',
        array('post', 'page'),
        'side',
        'high'
    );
}

function mad_author_image_metabox_html($post) {
    $image_id  = (int) get_post_meta($post->ID, '_mad_author_image_id', true);
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, array(96, 96)) : '';
    wp_nonce_field('mad_author_image_nonce', 'mad_author_image_nonce');
    ?>
    <div style="margin-bottom:8px;">
        <?php if ($image_url): ?>
            <img id="mad_author_image_preview"
                 src="<?php echo esc_url($image_url); ?>"
                 style="width:96px;height:96px;object-fit:cover;display:block;margin-bottom:6px;" />
        <?php else: ?>
            <img id="mad_author_image_preview"
                 src=""
                 style="width:96px;height:96px;object-fit:cover;display:<?php echo $image_url ? 'block' : 'none'; ?>;margin-bottom:6px;" />
        <?php endif; ?>
    </div>
    <input type="hidden" id="mad_author_image_id" name="mad_author_image_id" value="<?php echo esc_attr($image_id ?: ''); ?>" />
    <button type="button" class="button" id="mad_author_image_upload"><?php echo $image_id ? 'Change Image' : 'Upload Image'; ?></button>
    <?php if ($image_id): ?>
        <button type="button" class="button" id="mad_author_image_remove" style="margin-top:4px;">Remove</button>
    <?php else: ?>
        <button type="button" class="button" id="mad_author_image_remove" style="margin-top:4px;display:none;">Remove</button>
    <?php endif; ?>
    <script>
    jQuery(document).ready(function($) {
        var frame;
        $('#mad_author_image_upload').on('click', function(e) {
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({ title: 'Select Author Image', button: { text: 'Use this image' }, multiple: false });
            frame.on('select', function() {
                var att = frame.state().get('selection').first().toJSON();
                $('#mad_author_image_id').val(att.id);
                $('#mad_author_image_preview').attr('src', att.url).show();
                $('#mad_author_image_upload').text('Change Image');
                $('#mad_author_image_remove').show();
            });
            frame.open();
        });
        $('#mad_author_image_remove').on('click', function(e) {
            e.preventDefault();
            $('#mad_author_image_id').val('');
            $('#mad_author_image_preview').attr('src', '').hide();
            $('#mad_author_image_upload').text('Upload Image');
            $(this).hide();
        });
    });
    </script>
    <?php
}

// Save author image
add_action('save_post', 'mad_save_author_image');
function mad_save_author_image($post_id) {
    if (!isset($_POST['mad_author_image_nonce'])) return;
    if (!wp_verify_nonce($_POST['mad_author_image_nonce'], 'mad_author_image_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $image_id = isset($_POST['mad_author_image_id']) ? absint($_POST['mad_author_image_id']) : 0;
    if ($image_id) {
        update_post_meta($post_id, '_mad_author_image_id', $image_id);
    } else {
        delete_post_meta($post_id, '_mad_author_image_id');
    }
}

// Save reporter name
add_action('save_post', 'mad_save_reporter_name');
function mad_save_reporter_name($post_id) {
    if (!isset($_POST['mad_reporter_name_nonce'])) return;
    if (!wp_verify_nonce($_POST['mad_reporter_name_nonce'], 'mad_reporter_name_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $reporter_name = sanitize_text_field($_POST['mad_reporter_name']);
    update_post_meta($post_id, '_mad_reporter_name', $reporter_name);

    $reporter_date = sanitize_text_field($_POST['mad_reporter_date']);
    if (!empty($reporter_date)) {
        update_post_meta($post_id, '_mad_reporter_date', $reporter_date);
    }
}

// Sync Reporter Date -> post_date so the published date matches the reporter date.
// Runs before wpdb update so the override persists for both new and existing posts.
add_filter('wp_insert_post_data', 'mad_sync_reporter_date_to_post_date', 10, 2);
function mad_sync_reporter_date_to_post_date($data, $postarr) {
    // Skip revisions / autosaves / non-target post types.
    if (in_array($data['post_type'], array('revision', 'auto-draft'), true)) {
        return $data;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $data;
    }
    if (!isset($_POST['mad_reporter_name_nonce'])) {
        return $data;
    }
    if (!wp_verify_nonce($_POST['mad_reporter_name_nonce'], 'mad_reporter_name_nonce')) {
        return $data;
    }
    if (empty($_POST['mad_reporter_date'])) {
        return $data;
    }

    $reporter_date = sanitize_text_field($_POST['mad_reporter_date']);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reporter_date)) {
        return $data;
    }

    // Preserve time portion from existing post_date so chronological ordering stays stable.
    $time_part = current_time('H:i:s');
    if (!empty($data['post_date']) && preg_match('/\s(\d{2}:\d{2}:\d{2})$/', $data['post_date'], $m)) {
        $time_part = $m[1];
    }

    $new_post_date = $reporter_date . ' ' . $time_part;
    if (strtotime($new_post_date) === false) {
        return $data;
    }

    $data['post_date']     = $new_post_date;
    $data['post_date_gmt'] = get_gmt_from_date($new_post_date);

    // If WP set status to 'future' but the reporter date is past/now, force publish.
    if (isset($data['post_status']) && $data['post_status'] === 'future' && strtotime($new_post_date) <= current_time('timestamp')) {
        $data['post_status'] = 'publish';
    }

    return $data;
}
