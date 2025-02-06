/**
 * WooCommerce: Change "Add to Cart" Text to "Buy Again" if Purchased
 * 
 */

add_filter( 'woocommerce_product_add_to_cart_text', 'buy_again_button_text' );

function buy_again_button_text( $text ) {
    global $product;
    
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        
        // Check if the user has purchased this product before
        $orders = wc_get_orders( array(
            'limit' => -1,
            'customer_id' => $user_id,
            'status' => array( 'wc-completed', 'wc-processing' ), // Adjust order statuses as needed
        ) );
        
        foreach ( $orders as $order ) {
            // Loop through order items to check if the user has purchased this specific product
            foreach ( $order->get_items() as $item ) {
                if ( $item->get_product_id() === $product->get_id() ) {
                    return 'Buy Again'; // Change text to 'Buy Again' if the user has purchased
                }
            }
        }
    }

    return $text; // Default text
}
