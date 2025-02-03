<?php
/**
* Snippet Name:	woocommerce_update_order_review_fragments
* Uses on checkout page
*/
add_filter( 'woocommerce_update_order_review_fragments', function ( $fragments ) {
	if ( isset( $fragments['.woocommerce-checkout-payment'] ) && empty( $fragments['.woocommerce-checkout-payment'] ) ) {

		ob_start();
		if ( WC()->cart->needs_payment() ) {
			$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
			WC()->payment_gateways()->set_current_gateway( $available_gateways );
		} else {
			$available_gateways = array();
		}
		$checkout          = WC()->checkout();
		$order_button_text = apply_filters( 'woocommerce_order_button_text', __( 'Place order', 'woocommerce' ) );
		include TEMPLATE . '/checkout/payment.php';
		$html = ob_get_clean();
		if ( ! empty( $html ) ) {
			$fragments['.woocommerce-checkout-payment'] = $html;
		} else {
			unset( $fragments['.woocommerce-checkout-payment'] );
		}
	}

	return $fragments;
}, 999 );
