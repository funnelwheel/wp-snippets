<?php
/**
* Snippet Name:	Change the WooCommerce variable product drop down place holder text
*/
add_filter( 'woocommerce_dropdown_variation_attribute_options_args', 'change_dropdown_text_args', 10 );
function change_dropdown_text_args( $args ) {
    $args['show_option_none'] = 'Choose a size';
    return $args;
}