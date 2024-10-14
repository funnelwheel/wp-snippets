<?php
/**
* Snippet Name: Exclude Product From Discount Coupons.
*/
add_filter( 'woocommerce_coupon_is_valid_for_product', 'exclude_product_from_product_promotions', 999, 4 );
function exclude_product_from_product_promotions( $valid, $product, $coupon, $values ) {
   // PRODUCT ID HERE (i.e. 123)
   if ( 123 == $product->get_id() ) {
      $valid = false;
   }
   return $valid;
}