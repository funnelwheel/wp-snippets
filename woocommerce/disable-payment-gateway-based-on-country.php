<?php
/**
 * Snippet: Disable a Payment Gateway for a country
 */
  
add_filter( 'woocommerce_available_payment_gateways', 'disable_payment_gateway_based_on_country', 9999 );
  
function disable_payment_gateway_based_on_country( $available_gateways ) {
    if ( is_admin() ) return $available_gateways;
    if ( isset( $available_gateways['stripe'] ) && WC()->customer && WC()->customer->get_billing_country() == 'IN' ) {
        unset( $available_gateways['stripe'] );
    }
    return $available_gateways;
}
