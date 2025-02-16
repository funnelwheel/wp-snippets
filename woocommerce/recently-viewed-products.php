<?php
/**
 * Plugin Name: Recently Viewed Products
 * Description: A plugin to display recently viewed products using WooCommerce's native query.
 * Version: 1.0.0
 * Author: Kishores
 * License: GPL3
 */

// Ensure WooCommerce is active
if ( ! class_exists( 'WooCommerce' ) ) {
    exit; // Exit if WooCommerce is not active
}

/**
 * Shortcode to display recently viewed products with support for dynamic attributes.
 * Usage: Once the plugin is activated, you can use the shortcode [recently_viewed_products] in any post, page, or widget.
 * e.g.: [recently_viewed_products per_page="6" orderby="price" order="asc" category="clothing" columns="3"]
 *
 */
add_shortcode( 'recently_viewed_products', 'wc_recently_viewed_shortcode' );

function wc_recently_viewed_shortcode( $atts ) {
    // Set default attributes
    $atts = shortcode_atts( array(
        'per_page'   => 8,       // Default to 8 products per page
        'orderby'    => 'date',  // Default sorting by date
        'order'      => 'desc',  // Default order is descending
        'category'   => '',      // Default category is none
        'columns'    => 4,       // Default columns is 4
    ), $atts, 'recently_viewed_products' );

    // Get the recently viewed products from the cookie
    $viewed_products = ! empty( $_COOKIE['woocommerce_recently_viewed'] ) ? (array) explode( '|', wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) : array();
    
    // Limit the number of products to 'per_page' (default: 8)
    $viewed_products = array_slice($viewed_products, 0, $atts['per_page']);
    
    // If there are no recently viewed products, return nothing
    if ( empty( $viewed_products ) ) return;
    
    // Title for the section
    $title = '<h3 class="product-section-title container-width product-section-title-related pt-half pb-half uppercase">You Recently Viewed</h3>';
    
    // Prepare the product query arguments based on the attributes
    $args = array(
        'limit'      => $atts['per_page'],  // Limit the number of products
        'orderby'    => $atts['orderby'],   // Sorting order
        'order'      => $atts['order'],     // Sorting direction (asc, desc)
        'post__in'   => $viewed_products,   // Limit to recently viewed products
        'columns'    => $atts['columns'],   // Number of columns for product display
    );

    // Add category filter if specified
    if ( ! empty( $atts['category'] ) ) {
        $args['category'] = $atts['category'];
    }

    // Create the WC_Product_Query
    $query = new WC_Product_Query( $args );

    // If no products are found, return nothing
    if ( ! $query->get_products() ) {
        return '';
    }

    // Start the loop to display the products
    $output = $title . '<ul class="products columns-' . esc_attr( $atts['columns'] ) . '">';

    foreach ( $query->get_products() as $product ) {
        // Start the product HTML output
        $output .= '<li ' . wc_product_class( '', $product ) . '>';
        $output .= '<a href="' . get_permalink( $product->get_id() ) . '">';
        $output .= $product->get_image();
        $output .= '<h2 class="woocommerce-loop-product__title">' . $product->get_name() . '</h2>';
        $output .= '</a>';
        $output .= '</li>';
    }

    // End the list
    $output .= '</ul>';

    return $output;
}

/**
 * Track the viewed products and store them in a cookie
 */
function wc_custom_track_product_view() {
    if ( ! is_singular( 'product' ) ) {
        return;
    }

    global $post;

    // Get the list of viewed products from the cookie
    if ( empty( $_COOKIE['woocommerce_recently_viewed'] ) ) {
        $viewed_products = array();
    } else {
        $viewed_products = (array) explode( '|', $_COOKIE['woocommerce_recently_viewed'] );
    }

    // Add the current product ID to the viewed products list if not already present
    if ( ! in_array( $post->ID, $viewed_products ) ) {
        $viewed_products[] = $post->ID;
    }

    // Limit the number of viewed products to 15
    if ( sizeof( $viewed_products ) > 15 ) {
        array_shift( $viewed_products );  // Remove the oldest product if more than 15
    }

    // Store the viewed products in a cookie
    wc_setcookie( 'woocommerce_recently_viewed', implode( '|', $viewed_products ) );
}

add_action( 'template_redirect', 'wc_custom_track_product_view', 20 );
