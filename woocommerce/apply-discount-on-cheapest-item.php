<?php
/**
* Snippet Name:	Apply discount on the cheapest item
*/

add_action( 'woocommerce_before_calculate_totals', 'apply_discount_on_cheapest_item', 999 );
  
function apply_discount_on_cheapest_item( $cart ) {
  
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
  
    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) return;
  
    if ( count( $cart->get_cart() ) < 2 ) return; // Return if less than 2 products
 
    $min = PHP_FLOAT_MAX;
  
    // Find the cheapest item
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
      if ( $cart_item['data']->get_price() <= $min ) {
         $min = $cart_item['data']->get_price();
         $cheapest = $cart_item_key;
      }
    }
 
    // Reduce the cheapest item price by 50%
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
      if ( $cheapest == $cart_item_key ) {
         $price = $cart_item['data']->get_price() / 2;
         $cart_item['data']->set_price( $price );
         $cart_item['data']->set_sale_price( $price );
      }
    }
}
