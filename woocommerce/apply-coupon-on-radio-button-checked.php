<?php
/**
 * Snippet: Apply Coupon when a radio button is clicked on The Product Page
 * Author: https://profiles.wordpress.org/kishores
 * Url: https://upnrunn.com
 * Note: Please make sure to create the coupon in the WC Admin
 */

// Add radio button below Add to Cart button
add_action( 'woocommerce_after_add_to_cart_button', 'add_coupon_radio_button', 10 );
function add_coupon_radio_button() {
    	echo '<br /><input type="radio" id="10%OFF" name="add_coupon" value="10%OFF">
		<label for="10%OFF">Apply a coupon:<b>10%OFF</b> by checking this box!</label><br />';
		echo '<input type="radio" id="20%OFF" name="add_coupon" value="20%OFF">
		<label for="20%OFF">Apply a coupon:<b>20%OFF</b> by checking this box!</label><br />';
}

// Apply coupon when radio button is clicked
add_action( 'woocommerce_add_to_cart', 'apply_coupon_on_radio_button_clicked', 10, 6 );
function apply_coupon_on_radio_button_clicked( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
    if ( isset( $_POST['add_coupon'] ) ) {
    	if ($_POST['add_coupon'] == '10%OFF') {
    		// WC()->cart->remove_coupons(); // Remove all remove coupons
    		WC()->cart->remove_coupon('20%OFF');
        	$coupon_code = '10%OFF'; // Replace with your coupon code
        	$coupon = new WC_Coupon( $coupon_code );
        	WC()->cart->apply_coupon( $coupon_code );
        }
        if ($_POST['add_coupon'] == '20%OFF') {
        	// WC()->cart->remove_coupons(); // Remove all remove coupons
        	WC()->cart->remove_coupon('10%OFF');
        	$coupon_code = '20%OFF'; // Replace with your coupon code
        	$coupon = new WC_Coupon( $coupon_code );
        	WC()->cart->apply_coupon( $coupon_code );
        }
    }
}