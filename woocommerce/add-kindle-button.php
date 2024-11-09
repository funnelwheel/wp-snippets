<?php
/**
* Snippet Name:	Add the Buy on Kindle button near Add to Cart in WooCommerce PDP.
* Don't forget to add kindle_button_url in custom field
*/
add_action('woocommerce_after_add_to_cart_button','add_kindle_button');
function add_kindle_button() {
    $kindle_button_url =  get_post_meta( get_the_ID(),'kindle_button_url',true);
    if ( isset($kindle_button_url) && filter_var($kindle_button_url, FILTER_VALIDATE_URL)) {
    	$style = 'style="background-color: #ff9900; color: #fff;';
    	$button = '<button ' . $style . ' type="button" class="button" >Buy on Kindle</button>';
    	echo '<a href="'.$kindle_button_url.'" target="_blank">' . $button . '</a>';
    }   
}
