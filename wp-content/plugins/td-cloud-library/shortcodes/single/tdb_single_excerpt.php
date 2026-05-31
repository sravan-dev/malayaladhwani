<?php

/**
 * Class tdb_single_excerpt
 */
class tdb_single_excerpt extends td_block {

    public function get_custom_css() {
        // $unique_block_class - the unique class that is on the block. use this to target the specific instance via css
        $in_composer = td_util::tdc_is_live_editor_iframe() || td_util::tdc_is_live_editor_ajax();
        $in_element  = td_global::get_in_element();
        $unique_block_class_prefix = '';

        if ( $in_element || $in_composer ) {
            $unique_block_class_prefix = 'tdc-row .';

            if ( $in_element && $in_composer ) {
                $unique_block_class_prefix = 'tdc-row-composer .';
            }
        }

        $unique_block_class = $unique_block_class_prefix . $this->block_uid;

        $compiled_css = '';

        $raw_css =
            "<style>

                /* @style_general_single_excerpt */
                .tdb_single_excerpt {
                  margin-bottom: 14px;
                }
                .tdb_single_excerpt p {
                  font-family: var(--td_default_google_font_1, 'Open Sans', 'Open Sans Regular', sans-serif);
                  font-size: 16px;
                  font-style: italic;
                  font-weight: 300;
                  line-height: 24px;
                  margin-top: 0;
                  margin-bottom: 0;
                  color: #747474;
                }
                .tdb_single_excerpt.tdb-content-horiz-center {
                  text-align: center;
                }
                .tdb_single_excerpt.tdb-content-horiz-right {
                  text-align: right;
                }

                /* @excerpt_color */
                .$unique_block_class p {
                    color: @excerpt_color;
                }

                /* @excerpt_color_gradient */
                .$unique_block_class p {
                    @excerpt_color_gradient
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                }
                html[class*='ie'] .$unique_block_class p {
                    background: none;
                    color: @excerpt_color_gradient_1;
                }

                /* @align_center */
                .td-theme-wrap .$unique_block_class {
                    text-align: center;
                }
                /* @align_right */
                .td-theme-wrap .$unique_block_class {
                    text-align: right;
                }
                /* @align_left */
                .td-theme-wrap .$unique_block_class {
                    text-align: left;
                }

                /* @f_excerpt */
                .$unique_block_class p {
                    @f_excerpt
                }

            </style>";

        $td_css_res_compiler = new td_css_res_compiler( $raw_css );
        $td_css_res_compiler->load_settings( __CLASS__ . '::cssMedia', $this->get_all_atts() );

        $compiled_css .= $td_css_res_compiler->compile_css();
        return $compiled_css;
    }

    static function cssMedia( $res_ctx ) {

        $res_ctx->load_settings_raw( 'style_general_single_excerpt', 1 );

        // excerpt color
        $res_ctx->load_color_settings(
            'excerpt_color',
            'excerpt_color',
            'excerpt_color_gradient',
            'excerpt_color_gradient_1',
            ''
        );

        // content align
        $content_align = $res_ctx->get_shortcode_att('content_align_horizontal');
        if ( $content_align == 'content-horiz-center' ) {
            $res_ctx->load_settings_raw( 'align_center', 1 );
        } else if ( $content_align == 'content-horiz-right' ) {
            $res_ctx->load_settings_raw( 'align_right', 1 );
        } else if ( $content_align == 'content-horiz-left' ) {
            $res_ctx->load_settings_raw( 'align_left', 1 );
        }

        /*-- FONTS -- */
        $res_ctx->load_font_settings( 'f_excerpt' );

    }

    /**
     * Disable loop block features. This block does not use a loop and it doesn't need to run a query.
     */
    function __construct() {
        parent::disable_loop_block_features();
    }

    function render($atts, $content = null) {
        parent::render($atts); // sets the live atts, $this->atts, $this->block_uid, $this->td_query (it runs the query)

        global $tdb_state_single;

        // Get excerpt data from TagDiv single state
        $post_excerpt_data = $tdb_state_single->post_excerpt->__invoke();

        $additional_classes = array();

        $buffy = ''; // output buffer

        // if TagDiv state doesn't provide an excerpt, fallback to WP's get_the_excerpt()
        if ( empty( $post_excerpt_data['post_excerpt'] ) ) {
            $fallback_excerpt = get_the_excerpt();
            if ( empty( $fallback_excerpt ) ) {
                // nothing to show, bail out on frontend
                return $buffy;
            }
            $post_excerpt_data['post_excerpt'] = $fallback_excerpt;
        }

        $buffy .= '<div class="' . $this->get_block_classes( $additional_classes )  . '" ' . $this->get_block_html_atts() . '>';

        // get the block css
        $buffy .= $this->get_block_css();

        // get the js for this block
        $buffy .= $this->get_block_js();

        $buffy .= '<div class="tdb-block-inner td-fix-index">';
        $buffy .= '<p>' . $post_excerpt_data['post_excerpt'] . '</p>';
        $buffy .= '</div>';

        $buffy .= '</div>';

        return $buffy;
    }
}
