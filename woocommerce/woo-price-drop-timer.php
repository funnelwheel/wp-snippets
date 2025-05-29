<?php
/**
 * Plugin Name: WooCommerce Price Drop Timer
 * Description: Adds a temporary price drop (like flash sale) with timer, no database price update needed.
 * Version: 1.0.0
 * Author: Kishores
 */

if (!defined('ABSPATH')) exit;

// 1. Add Metabox to Product Edit Page
add_action('add_meta_boxes', function () {
    add_meta_box('wc_price_drop_timer', 'Price Drop Timer', function ($post) {
        $enabled  = get_post_meta($post->ID, '_pd_enabled', true);
        $discount = get_post_meta($post->ID, '_pd_discount', true);
        $duration = get_post_meta($post->ID, '_pd_duration', true);

        echo '<label><input type="checkbox" name="pd_enabled" value="1" ' . checked($enabled, '1', false) . '> Enable Price Drop</label><br><br>';
        echo '<label>Discount (%):<br><input type="number" name="pd_discount" value="' . esc_attr($discount) . '" min="1" max="100" style="width:100%"></label><br><br>';
        echo '<label>Duration (minutes):<br><input type="number" name="pd_duration" value="' . esc_attr($duration) . '" min="1" style="width:100%"></label>';
        wp_nonce_field('pd_save_meta', 'pd_nonce');
    }, 'product', 'side');
});

// 2. Save Meta Fields
add_action('save_post_product', function ($post_id) {
    if (!isset($_POST['pd_nonce']) || !wp_verify_nonce($_POST['pd_nonce'], 'pd_save_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $enabled  = isset($_POST['pd_enabled']) ? '1' : '0';
    $discount = isset($_POST['pd_discount']) ? intval($_POST['pd_discount']) : 0;
    $duration = isset($_POST['pd_duration']) ? intval($_POST['pd_duration']) : 0;

    update_post_meta($post_id, '_pd_enabled', $enabled);
    update_post_meta($post_id, '_pd_discount', $discount);
    update_post_meta($post_id, '_pd_duration', $duration);

    if ($enabled === '1' && $discount > 0 && $duration > 0) {
        update_post_meta($post_id, '_pd_start_time', time());
    } else {
        delete_post_meta($post_id, '_pd_start_time');
    }
});

// 3. Apply Dynamic Discount to Price
add_filter('woocommerce_product_get_price', 'pd_apply_dynamic_discount', 20, 2);
add_filter('woocommerce_product_get_sale_price', 'pd_apply_dynamic_discount', 20, 2);

function pd_apply_dynamic_discount($price, $product) {
    if (is_admin() || !$product instanceof WC_Product_Simple) return $price;

    pd_maybe_cleanup_expired_price_drop($product);

    $id       = $product->get_id();
    $enabled  = get_post_meta($id, '_pd_enabled', true);
    $discount = floatval(get_post_meta($id, '_pd_discount', true));
    $duration = intval(get_post_meta($id, '_pd_duration', true));
    $start    = intval(get_post_meta($id, '_pd_start_time', true));

    if ($enabled !== '1' || $discount <= 0 || $duration <= 0 || $start <= 0) return $price;

    $now = time();
    $end = $start + ($duration * 60);

    if ($now >= $start && $now <= $end) {
        $regular = floatval($product->get_regular_price());
        if ($regular <= 0) return $price;

        $discounted = $regular * (1 - ($discount / 100));
        return round($discounted, 2);
    }

    return $price;
}

// 4. Show Timer on Product Page
add_action('woocommerce_single_product_summary', 'pd_show_timer_on_product_page', 25);

function pd_show_timer_on_product_page() {
    global $product;
    if (!$product || !$product instanceof WC_Product_Simple) return;

    $id       = $product->get_id();
    $enabled  = get_post_meta($id, '_pd_enabled', true);
    $discount = floatval(get_post_meta($id, '_pd_discount', true));
    $duration = intval(get_post_meta($id, '_pd_duration', true));
    $start    = intval(get_post_meta($id, '_pd_start_time', true));

    if ($enabled === '1' && $discount > 0 && $duration > 0 && $start > 0) {
        $end = $start + ($duration * 60);
        if (time() < $end) {
            echo '<div id="pd-timer" data-end="' . esc_attr($end) . '" style="margin-top:20px;padding:10px;background:#f1f8e9;border-left:4px solid #689f38;font-weight:bold;"></div>';
            echo '<script>
            document.addEventListener("DOMContentLoaded",function(){
                const el = document.getElementById("pd-timer");
                const end = parseInt(el.dataset.end)*1000;
                function tick(){
                    const now = Date.now();
                    const left = end - now;
                    if(left <= 0){
                        el.textContent = "Price drop ended.";
                        return;
                    }
                    const m = Math.floor(left/60000),
                          s = Math.floor((left%60000)/1000);
                    el.textContent = "🔥 Limited time offer! Ends in " + m + "m " + s + "s";
                    setTimeout(tick, 1000);
                }
                tick();
            });
            </script>';
        }
    }
}

// 5. Clean up expired price drops
function pd_maybe_cleanup_expired_price_drop($product) {
    $id       = $product->get_id();
    $enabled  = get_post_meta($id, '_pd_enabled', true);
    $duration = intval(get_post_meta($id, '_pd_duration', true));
    $start    = intval(get_post_meta($id, '_pd_start_time', true));

    if ($enabled === '1' && $duration > 0 && $start > 0) {
        $now = time();
        $end = $start + ($duration * 60);
        if ($now > $end) {
            // Timer expired, clean up
            update_post_meta($id, '_pd_enabled', '0'); // Uncheck checkbox
            delete_post_meta($id, '_pd_start_time');   // Optional cleanup
        }
    }
}
