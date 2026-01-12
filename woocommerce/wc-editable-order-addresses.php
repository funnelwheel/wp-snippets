<?php
/**
 * Plugin Name: WooCommerce Editable Order Addresses
 * Plugin URI:  https://github.com/funnelwheel/
 * Description: Allows customers and guests to edit billing and shipping addresses for processing orders within 30 minutes. Recalculates shipping and handles extra payment if needed.
 * Version:     1.2.0
 * Author:      Kishores
 * Author URI:  https://kishoresahoo.wordpress.com/
 * Text Domain: wc-editable-order-addresses
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Allow canceling paid orders for Processing orders within 30 minutes
 */
add_filter( 'woocommerce_valid_order_statuses_for_cancel', 'wc_allow_cancel_paid_orders_all_users', 10, 2 );
function wc_allow_cancel_paid_orders_all_users( $statuses, $order ) {
    if ( ! $order instanceof WC_Order ) return $statuses;

    if ( $order->has_status( 'processing' ) && ( time() - $order->get_date_created()->getTimestamp() ) <= 30 * 60 ) {
        $statuses[] = 'processing';
    }

    return $statuses;
}

/**
 * Add "Edit Order" button to My Orders table
 */
add_filter( 'woocommerce_my_account_my_orders_actions', 'wc_add_edit_order_action', 10, 2 );
function wc_add_edit_order_action( $actions, $order ) {
    if ( ! $order instanceof WC_Order ) return $actions;
    if ( ! $order->has_status( 'processing' ) ) return $actions;
    if ( ( time() - $order->get_date_created()->getTimestamp() ) > 30 * 60 ) return $actions;

    $url = $order->get_view_order_url();

    if ( ! is_user_logged_in() ) {
        $url = add_query_arg(
            'key',
            $order->get_order_key(),
            wc_get_endpoint_url( 'view-order', $order->get_id(), wc_get_page_permalink( 'myaccount' ) )
        );
    }

    $actions['edit_order'] = [
        'url'  => $url,
        'name' => __( 'Edit Order', 'woocommerce' ),
    ];

    return $actions;
}

/**
 * Display combined billing + shipping form on View Order page
 */
add_action( 'woocommerce_order_details_after_customer_details', 'wc_edit_order_addresses_form' );
function wc_edit_order_addresses_form( $order ) {
    if ( ! $order instanceof WC_Order ) return;
    if ( ! $order->has_status( 'processing' ) ) return;
    if ( ( time() - $order->get_date_created()->getTimestamp() ) > 30 * 60 ) return;
    if ( ! is_user_logged_in() && ( $_GET['key'] ?? '' ) !== $order->get_order_key() ) return;
    ?>
    <h3><?php esc_html_e( 'Edit Order Addresses', 'woocommerce' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'wc_edit_order_addresses', 'wc_edit_order_nonce' ); ?>
        <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">

        <h4><?php esc_html_e( 'Billing Address', 'woocommerce' ); ?></h4>
        <?php
        foreach ( WC()->countries->get_address_fields( $order->get_billing_country(), 'billing_' ) as $key => $field ) {
            woocommerce_form_field( $key, $field, $order->{"get_$key"}() );
        }
        ?>

        <h4><?php esc_html_e( 'Shipping Address', 'woocommerce' ); ?></h4>
        <?php
        foreach ( WC()->countries->get_address_fields( $order->get_shipping_country(), 'shipping_' ) as $key => $field ) {
            woocommerce_form_field( $key, $field, $order->{"get_$key"}() );
        }
        ?>

        <button type="submit" name="wc_save_order_addresses" class="button">
            <?php esc_html_e( 'Save Order', 'woocommerce' ); ?>
        </button>
    </form>
    <?php
}

add_action( 'template_redirect', 'wc_save_order_addresses_and_recalculate' );
function wc_save_order_addresses_and_recalculate() {

    if ( ! isset( $_POST['wc_save_order_addresses'] ) ) return;
    if ( ! isset( $_POST['wc_edit_order_nonce'] ) || ! wp_verify_nonce( $_POST['wc_edit_order_nonce'], 'wc_edit_order_addresses' ) ) return;

    $order_id = absint( $_POST['order_id'] );
    $order    = wc_get_order( $order_id );

    if ( ! $order || ! $order->has_status( [ 'processing', 'pending' ] ) ) return;
    if ( ! is_user_logged_in() && ( $_GET['key'] ?? '' ) !== $order->get_order_key() ) return;

    // 30-minute edit limit
    if ( ( time() - $order->get_date_created()->getTimestamp() ) > 30 * 60 ) {
        wc_add_notice( __( 'You can no longer edit this order.', 'woocommerce' ), 'error' );
        wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
        exit;
    }

    // ✅ Get original paid amount or total at first payment
    $original_paid_total = (float) $order->get_meta( '_original_total_paid' );
    if ( ! $original_paid_total ) {
        // First time edit, store it
        $original_paid_total = (float) $order->get_total();
        $order->update_meta_data( '_original_total_paid', $original_paid_total );
        $order->save();
    }

    /**
     * Update shipping address
     */
    $order->set_address( [
        'first_name' => sanitize_text_field( $_POST['shipping_first_name'] ?? '' ),
        'last_name'  => sanitize_text_field( $_POST['shipping_last_name'] ?? '' ),
        'address_1'  => sanitize_text_field( $_POST['shipping_address_1'] ?? '' ),
        'address_2'  => sanitize_text_field( $_POST['shipping_address_2'] ?? '' ),
        'city'       => sanitize_text_field( $_POST['shipping_city'] ?? '' ),
        'state'      => sanitize_text_field( $_POST['shipping_state'] ?? '' ),
        'postcode'   => sanitize_text_field( $_POST['shipping_postcode'] ?? '' ),
        'country'    => sanitize_text_field( $_POST['shipping_country'] ?? '' ),
    ], 'shipping' );

    // Remove existing shipping
    foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
        $order->remove_item( $item_id );
    }

    // Get correct shipping zone
    $zone = WC_Shipping_Zones::get_zone_matching_package( [
        'destination' => [
            'country'  => $order->get_shipping_country(),
            'state'    => $order->get_shipping_state(),
            'postcode' => $order->get_shipping_postcode(),
        ],
    ] );

    $methods = $zone ? $zone->get_shipping_methods( true ) : [];

    if ( ! empty( $methods ) ) {
        foreach ( $methods as $method ) {
            if ( 'yes' === $method->enabled ) {
                $shipping_item = new WC_Order_Item_Shipping();
                $shipping_item->set_method_title( $method->get_title() );
                $shipping_item->set_method_id( $method->id );
                $shipping_item->set_total( (float) $method->cost );
                $order->add_item( $shipping_item );
                break;
            }
        }
    }

    // Recalculate totals
    $order->calculate_totals( true );
    $order->save();

    // ✅ Extra payment = new total - original paid total
    $new_total = (float) $order->get_total();
    $extra     = max( 0, $new_total - $original_paid_total );

    if ( $extra > 0 ) {
        // Allow extra payment
        $order->update_status( 'pending', __( 'Awaiting extra payment due to shipping change.', 'woocommerce' ) );

        wc_add_notice(
            sprintf(
                __( 'Shipping increased by %s. <a href="%s">Pay the extra amount</a>.', 'woocommerce' ),
                wc_price( $extra ),
                esc_url( $order->get_checkout_payment_url() )
            ),
            'notice'
        );
    } else {
        wc_add_notice( __( 'Shipping updated successfully. No extra payment required.', 'woocommerce' ), 'success' );
    }

    wp_safe_redirect( wp_get_referer() ?: wc_get_account_endpoint_url( 'orders' ) );
    exit;
}
