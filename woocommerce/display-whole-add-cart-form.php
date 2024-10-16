<?php
/**
* Snippet Name: Display the Add Cart Form on WooCommerce Shop page for variable products.
*/
add_action( 'woocommerce_after_shop_loop_item', 'display_whole_add_cart_form', 1 );
function display_whole_add_cart_form() {
   global $product;
   if ( ! $product->is_purchasable() ) return;
   remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
   add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_single_add_to_cart', 11 );
}
