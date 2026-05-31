<?php

/**
 * Class tdb_global_shortcode
 */
class tdb_global_shortcode extends td_block {

    function render( $atts, $content = null ) {
        parent::render( $atts ); // sets live atts, $this->atts, $this->block_uid, $this->td_query

        $atts = shortcode_atts(
            array(
                'content_general' => base64_encode( __( 'Html code here! Even shortcodes! Replace this with your code and that\'s it.', 'td_composer' ) ),
                'el_class'        => '',
            ),
            $atts,
            'tdb_global_shortcode'
        );

        if ( is_null( $content ) || empty( $content ) ) {
            $content = $atts['content_general'];
        }

        $content = rawurldecode( base64_decode( strip_tags( $content ) ) );

        $buffy  = '<div class="wpb_wrapper ' . $this->get_wrapper_class() . ' ' . $this->get_block_classes( array( $atts['el_class'] ) ) . '">';

        // Block CSS
        $buffy .= $this->get_block_css();

        $buffy .= '<div class="td-fix-index">' . do_shortcode( shortcode_unautop( $content ) ) . '</div>';

        $buffy .= '</div>';

        return $buffy;
    }

}
