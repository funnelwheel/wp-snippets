<?php
/**
* Snippet Name:	Add the Delivery Time Message near Add to Cart in WooCommerce PDP.
* Don't forget to add delivery_time_message in custom field
*/
add_action('woocommerce_after_add_to_cart_button','add_delivery_time_message');
function add_delivery_time_message() {
    $delivery_time_message = get_post_meta( get_the_ID(),'delivery_time_message',true);
    if ( isset($delivery_time_message) ) {
    	echo "<br /><br />" . $delivery_time_message;   
    }
}
