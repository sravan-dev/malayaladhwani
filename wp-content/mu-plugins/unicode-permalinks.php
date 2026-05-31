<?php
/**
 * Plugin Name: Unicode Permalinks Fix
 * Description: Decode percent-encoded Unicode characters in permalinks so Malayalam URLs appear readable when shared.
 * Version: 1.0
 */

function malayaladhwani_decode_permalink( $url ) {
    return rawurldecode( $url );
}

add_filter( 'the_permalink',   'malayaladhwani_decode_permalink' );
add_filter( 'post_link',       'malayaladhwani_decode_permalink' );
add_filter( 'page_link',       'malayaladhwani_decode_permalink' );
add_filter( 'post_type_link',  'malayaladhwani_decode_permalink' );
add_filter( 'term_link',       'malayaladhwani_decode_permalink' );
add_filter( 'attachment_link', 'malayaladhwani_decode_permalink' );

// Fix: logo border + ad height consistent on all pages
add_action( 'wp_head', 'malayaladhwani_header_fixes_css' );
function malayaladhwani_header_fixes_css() {
    ?>
    <style>
    /* Logo border – all pages (replaces fragile .tdi_32 selector in Customizer) */
    .tdb_header_logo .tdb-logo-a,
    .tdb_header_logo h1 {
        flex-direction: row;
        align-items: center;
        justify-content: center;
        border: 5px solid #0049a6 !important;
    }
    /* Ad banner – match logo height (87px image + 10px border = 97px total box) */
    .td-header-desktop-wrap .td-a-rec-id-header {
        height: 97px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-end !important;
        overflow: hidden;
    }
    .td-header-desktop-wrap .td-a-rec-id-header .td-all-devices {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        height: 100%;
    }
    .td-header-desktop-wrap .td-a-rec-id-header img {
        height: 87px !important;
        width: auto !important;
        max-width: 100%;
        display: block;
    }
    </style>
    <?php
}

// Add og:title, og:description, og:url so WhatsApp previews show the full heading
add_action( 'wp_head', 'malayaladhwani_og_tags', 2 );
function malayaladhwani_og_tags() {
    // Skip if a SEO plugin is already handling OG tags
    if ( class_exists( 'WPSEO_Frontend' ) || class_exists( 'RankMath' ) || class_exists( 'AIOSEOAbstract' ) ) {
        return;
    }

    if ( ! is_singular() ) return;

    $post       = get_queried_object();
    $title      = get_the_title( $post->ID );
    $excerpt    = has_excerpt( $post->ID )
                    ? wp_strip_all_tags( get_the_excerpt() )
                    : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '' );
    $url        = rawurldecode( get_permalink( $post->ID ) );

    echo '<meta property="og:type"        content="article" />' . "\n";
    echo '<meta property="og:title"       content="' . esc_attr( $title )   . '" />' . "\n";
    echo '<meta property="og:description" content="' . esc_attr( $excerpt ) . '" />' . "\n";
    echo '<meta property="og:url"         content="' . esc_url( $url )      . '" />' . "\n";
    echo '<meta property="og:site_name"   content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";

    // Twitter / X card tags
    echo '<meta name="twitter:title"       content="' . esc_attr( $title )   . '" />' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr( $excerpt ) . '" />' . "\n";
}

// Inject Copy Link button next to share buttons via JS
add_action( 'wp_footer', 'malayaladhwani_copy_link_button' );
function malayaladhwani_copy_link_button() {
    if ( ! is_singular() ) return;
    $decoded_url = rawurldecode( get_permalink() );
    ?>
    <style>
    .ma-copy-link-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: #555;
        border-radius: 3px;
        cursor: pointer;
        margin-left: 4px;
        text-decoration: none;
        vertical-align: middle;
        position: relative;
        flex-shrink: 0;
    }
    .ma-copy-link-btn svg {
        width: 17px;
        height: 17px;
        fill: #fff;
        transition: opacity .2s;
    }
    .ma-copy-link-btn .ma-copy-check {
        display: none;
        position: absolute;
        color: #fff;
        font-size: 17px;
        line-height: 1;
    }
    .ma-copy-link-btn.copied .ma-copy-icon { display: none; }
    .ma-copy-link-btn.copied .ma-copy-check { display: block; }
    .ma-copy-link-btn.copied { background: #2e7d32; }
    </style>
    <script>
    (function(){
        function addCopyBtn() {
            var containers = document.querySelectorAll('.td-post-sharing-visible, .tdb_single_post_share .td-post-sharing-visible');
            containers.forEach(function(container) {
                if (container.querySelector('.ma-copy-link-btn')) return;

                var btn = document.createElement('a');
                btn.href = '#';
                btn.className = 'ma-copy-link-btn';
                btn.title = 'Copy Link';
                btn.setAttribute('aria-label', 'Copy link');
                btn.innerHTML =
                    '<svg class="ma-copy-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
                        '<path d="M16 1H4C2.9 1 2 1.9 2 3v14h2V3h12V1zm3 4H8C6.9 5 6 5.9 6 7v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>' +
                    '</svg>' +
                    '<span class="ma-copy-check">&#10003;</span>';

                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var url = <?php echo json_encode( $decoded_url ); ?>;
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(url);
                    } else {
                        var inp = document.createElement('input');
                        inp.style.cssText = 'position:fixed;top:-9999px;left:-9999px';
                        inp.value = url;
                        document.body.appendChild(inp);
                        inp.select();
                        document.execCommand('copy');
                        document.body.removeChild(inp);
                    }
                    btn.classList.add('copied');
                    setTimeout(function(){ btn.classList.remove('copied'); }, 1500);
                });

                container.appendChild(btn);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', addCopyBtn);
        } else {
            addCopyBtn();
        }
    })();
    </script>
    <?php
}
