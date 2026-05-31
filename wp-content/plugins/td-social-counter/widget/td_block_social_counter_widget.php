<?php

/**
 * check if the class exists, to prevent errors, better decoupling - it allows us to remove the td_block_widget class if needed
 */
if ( class_exists('td_block_widget') ) {

    // register our widget
    add_action( 'widgets_init', 'td_load_widget' );
    function td_load_widget() {
        $td_block_widget = new td_block_widget( 'td_block_social_counter' );
        register_widget( $td_block_widget );
    }

}