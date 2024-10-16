<?php
/**
* Snippet Name: Remove the coupon from the WooCommerce cart.
*/
add_filter( 'woocommerce_coupons_enabled', 'remove_coupons_on_cart' );
 
function remove_coupons_on_cart() {
   if ( is_cart() ) return false;
   return true;
}
