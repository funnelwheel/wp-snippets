<?php
/**
* Snippet Name:	Count the number of distinct items in the WooCommerce Cart.
*/
 add_filter( 'woocommerce_cart_contents_count', 'count_distinct_items_in_cart', 9999, 1 );
 
function count_distinct_items_in_cart( $count ) {
   $count = count( WC()->cart->get_cart() );
   return $count;
}
