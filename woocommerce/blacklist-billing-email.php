<?php
/**
* Snippet Name:	Blacklist Billing Email on the WooCommerce checkout page.
*/
add_action( 'woocommerce_after_checkout_validation', 'blacklist_billing_email', 999, 2 );
function blacklist_billing_email( $data, $errors ) {
   $blacklist = [ 'email1@example.com', 'email2@example.com', 'email3@example.com', ];
   if ( in_array( $data['billing_email'], $blacklist ) ) {
      $errors->add( 'blacklist', __( 'Sorry, we are unable to process your request.', 'wp-snippets' ) );
   }
}