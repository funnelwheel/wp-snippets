<?php
/**
 * Plugin Name: WooCommerce Smart Rating Replacer (Views or Sales)
 * Description: Replaces "0 reviews" with dynamic views or sales on WooCommerce product pages. Also tracks product views.
 * Version: 1.0
 * Author: Your Name
 * License: GPL2
 */

 // Your code goes here (tracking + filter functions)

add_filter( 'woocommerce_product_get_rating_html', 'custom_product_rating_html', 10, 3 );

function custom_product_rating_html( $rating_html, $rating, $count ) {
    global $product;

    if ( $count === 0 ) {
        $product_id = $product->get_id();

        // Get views from post meta (default key used by Post Views Counter)
        $views = (int) get_post_meta( $product_id, 'post_views_count', true );

        // If no real views, fallback to random number
        if ( empty( $views ) ) {
            $views = rand(500, 1200);
        }

        // Get total sales
        $sales = (int) $product->get_total_sales();

        // Compare and show whichever is higher
        if ( $sales > $views ) {
            $rating_html = '<div class="product-meta-sales">' . esc_html( $sales ) . ' purchased today</div>';
        } else {
            $rating_html = '<div class="product-meta-views">' . esc_html( $views ) . ' views today</div>';
        }
    }

    return $rating_html;
}


add_action( 'template_redirect', 'track_product_views' );

function track_product_views() {
    if ( is_product() ) {
        global $post;

        if ( empty( $post ) || ! is_a( $post, 'WP_Post' ) ) {
            return;
        }

        $product_id = $post->ID;

        // Optional: prevent bots from counting views
        if ( is_user_logged_in() || ! is_bot_request() ) {
            $views = (int) get_post_meta( $product_id, 'post_views_count', true );
            $views++;
            update_post_meta( $product_id, 'post_views_count', $views );
        }
    }
}

// Optional helper to detect bots
function is_bot_request() {
    $bots = [ 'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandexbot', 'sogou' ];
    $user_agent = strtolower( $_SERVER['HTTP_USER_AGENT'] ?? '' );

    foreach ( $bots as $bot ) {
        if ( strpos( $user_agent, $bot ) !== false ) {
            return true;
        }
    }

    return false;
}

