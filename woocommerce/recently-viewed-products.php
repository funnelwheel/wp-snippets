<?php
/**
 * Plugin Name: Recently Viewed Products with Email Notification
 * Description: A plugin to display recently viewed products and send email notifications to users after a specific period.
 * Version: 1.1.0
 * Author: Kishores
 * License: GPL3
 */

// Ensure WooCommerce is active
if ( ! class_exists( 'WooCommerce' ) ) {
    exit; // Exit if WooCommerce is not active
}

/**
 * Shortcode to display recently viewed products with support for dynamic attributes.
 * Usage: [recently_viewed_products per_page="6" orderby="price" order="asc" category="clothing" columns="3"]
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

/**
 * Check if 5 days have passed since the last product view and send an email.
 */
function wc_check_and_send_recently_viewed_email( $user_id, $viewed_products, $viewed_timestamps ) {
    // Calculate the time difference (in seconds) from the last viewed product
    $last_viewed_time = end( $viewed_timestamps );
    $current_time = time();
    $time_difference = $current_time - $last_viewed_time;

    // Check if more than 5 days (432000 seconds) have passed and user has viewed at least 5 products
    if ( $time_difference >= 432000 && sizeof( $viewed_products ) >= 5 ) {
        // Send the email
        wc_send_recently_viewed_email( $user_id, $viewed_products );
    }
}

/**
 * Send an email with the recently viewed products to the user.
 */
function wc_send_recently_viewed_email( $user_id, $viewed_products ) {
    $user_info = get_userdata( $user_id );
    $user_email = $user_info->user_email;

    // Prepare the product details
    $products = wc_get_products( array( 'include' => $viewed_products ) );
    $product_list = '';
    foreach ( $products as $product ) {
        $product_list .= '<li><a href="' . get_permalink( $product->get_id() ) . '">' . $product->get_name() . '</a></li>';
    }

    // Email subject and body
    $subject = 'Your Recently Viewed Products';
    $message = '
        <html>
        <head>
            <title>Your Recently Viewed Products</title>
        </head>
        <body>
            <p>Hi ' . $user_info->first_name . ',</p>
            <p>Here are the products you have recently viewed:</p>
            <ul>' . $product_list . '</ul>
            <p>We hope this helps you find what you are looking for!</p>
        </body>
        </html>
    ';

    // Send the email
    wp_mail( $user_email, $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/**
 * Track the user’s recently viewed products and send an email after 5 days.
 */
function wc_track_and_email_recently_viewed_products() {
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $viewed_products = get_user_meta( $user_id, '_recently_viewed_products', true );
        $viewed_timestamps = get_user_meta( $user_id, '_recently_viewed_product_timestamps', true );

        // Check if it's time to send the email (5 days after the last viewed product)
        wc_check_and_send_recently_viewed_email( $user_id, $viewed_products, $viewed_timestamps );
    }
}

add_action( 'template_redirect', 'wc_track_and_email_recently_viewed_products', 20 );

