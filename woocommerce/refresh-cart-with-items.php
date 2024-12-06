<?php
/**
* Snippet Name:	WooCommerce Cart Refresh with Cache Busting
*/

/**
 * Refresh the WooCommerce cart by removing and re-adding items.
 */
function refresh_cart_with_items() {
    // Get the current cart items
    $cart_items = WC()->cart->get_cart();
    $cart_data = array();

    // Store the current items and their quantities
    foreach ($cart_items as $cart_item_key => $cart_item) {
        $cart_data[] = array(
            'product_id' => $cart_item['product_id'],
            'quantity' => $cart_item['quantity'],
        );
    }

    // Empty the cart
    WC()->cart->empty_cart();

    // Re-add the items back to the cart with their quantities
    foreach ($cart_data as $item) {
        WC()->cart->add_to_cart($item['product_id'], $item['quantity']);
    }

    // Recalculate totals
    WC()->cart->calculate_totals();
}

/**
 * AJAX callback to refresh the cart
 */
function refresh_cart_ajax_callback() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        wp_send_json_error( array( 'message' => 'WooCommerce is not active.' ) );
        return;
    }

    // Refresh the cart by removing and re-adding items
    refresh_cart_with_items();
  
    // Clear cache after cart refresh
    // clear_cache_after_cart_refresh(); use this one to clear cache if needed
  

    // Get updated cart total and item count
    $cart_total = WC()->cart->get_cart_total();
    $item_count = WC()->cart->get_cart_contents_count();

    // Send updated cart data along with a cache-busting timestamp
    wp_send_json_success( array(
        'message'   => 'Cart refreshed.',
        'cart_total' => $cart_total,
        'item_count' => $item_count,
        'timestamp' => time()  // Cache-busting timestamp
    ));
}
add_action('wp_ajax_refresh_cart_action', 'refresh_cart_ajax_callback');
add_action('wp_ajax_nopriv_refresh_cart_action', 'refresh_cart_ajax_callback');



/**
 * Automatically refresh the cart on page load using AJAX
 */
function auto_refresh_cart_on_page_load() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Function to refresh the cart via AJAX
            function refreshCart() {
                $.ajax({
                    url: '<?php echo admin_url( 'admin-ajax.php' ); ?>?random=' + new Date().getTime(),  // Add timestamp to prevent cache
                    type: 'POST',
                    data: {
                        action: 'refresh_cart_action',  // Custom AJAX action
                    },
                    success: function(response) {
                        if (response.success) {
                            // console.log('Cart refreshed successfully!');
							              // console.log(response.data.cart_total);
                            // Cache-busting: Update the page timestamp or trigger a refresh
                            $('body').data('cart-refreshed-at', response.data.timestamp);

                            // Optionally, you can force reload certain elements, e.g., cart fragments
                            // WooCommerce has its own cart fragments system to update the mini cart and other elements dynamically
                            if (typeof wc_cart_fragments_params !== 'undefined') {
                                $.get(wc_cart_fragments_params.ajax_url, { action: 'woocommerce_get_refreshed_fragments' }, function(fragments) {
                                    $.each(fragments, function(key, value) {
                                        $(key).replaceWith(value);
                                    });
                                });
                            }

							              // Disable the cart widget opening (important for avoiding auto open)
        					          // $('body').off('added_to_cart');  // This prevents the cart widget from opening
                        } else {
                            console.log('Error refreshing cart');
                        }
                    },
                    error: function() {
                        console.log('AJAX error occurred');
                    }
         });
}
	refreshCart();

	});
    </script>
    <?php
}
add_action('wp_footer', 'auto_refresh_cart_on_page_load'); // Add this inline script to the footer
