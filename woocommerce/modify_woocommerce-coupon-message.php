<?php
/**
* Snippet Name:	Modify WooCommerce Coupon Message
*/
add_filter( 'woocommerce_coupon_message', 'modify_woocommerce_coupon_message', 10, 3 );
function modify_woocommerce_coupon_message( $msg, $msg_code, $coupon ) {
    if( $msg === __( 'Coupon code applied successfully.', 'woocommerce' ) ) {
        $msg = sprintf( 
            __( "You are getting $%s.", "woocommerce" ), 
            '<strong>' . $coupon->get_amount() . '</strong>' 
        );
    }
    return $msg;
}
