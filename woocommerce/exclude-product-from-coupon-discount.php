
function exclude_product_from_coupon_discount( $valid, $coupon, $discount ) {
    // Set the product ID you want to exclude
    $excluded_product_id = 187; // Replace with the ID of the product to exclude from the coupon
    
    // Loop through the cart items and check if the excluded product is in the cart
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( $cart_item['product_id'] == $excluded_product_id ) {
            // If the excluded product is in the cart, invalidate the coupon
            $valid = false;
            break;
        }
    }

    return $valid;
}
add_filter( 'woocommerce_coupon_is_valid', 'exclude_product_from_coupon_discount', 10, 3 );
