<?php
/**
 * Plugin Name: WooCommerce Editable Order Addresses
 * Plugin URI:  https://github.com/funnelwheel/
 * Description: Allows customers and guests to edit billing and shipping addresses for processing orders within 30 minutes. Recalculates shipping and handles extra payment if needed.
 * Version:     1.3.1
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

    // Do not allow canceling sub (extra payment) orders
    if ( $order->get_meta( '_parent_order_id' ) ) {
        return array_diff( $statuses, array( 'processing', 'pending', 'on-hold' ) );
    }

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

    // Do not allow editing sub (extra payment) orders
    if ( $order->get_meta( '_parent_order_id' ) ) {
        return $actions;
    }


    // 30-minute edit limit
    if ( ( time() - $order->get_date_created()->getTimestamp() ) > 30 * 60 ) return $actions;

    // Hide if order was already edited once
    if ( $order->get_meta( '_has_edited_addresses' ) ) return $actions;

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

    // Do not show edit form for sub (extra payment) orders
    if ( $order->get_meta( '_parent_order_id' ) ) {
        return;
    }


    $edit_deadline = $order->get_date_created()->getTimestamp() + 30 * 60;

    // Time expired
    if ( time() > $edit_deadline ) {
        echo '<p>' . esc_html__( 'The 30-minute editing window has expired.', 'woocommerce' ) . '</p>';
        return;
    }

    // Already edited
    if ( $order->get_meta( '_has_edited_addresses' ) ) {
        echo '<p>' . esc_html__( 'You have already edited this order. Editing is disabled.', 'woocommerce' ) . '</p>';
        return;
    }

    // Guest key check
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

/**
 * Handle address edits & shipping recalculation
 */
add_action( 'template_redirect', 'wc_save_order_addresses_and_recalculate' );
function wc_save_order_addresses_and_recalculate() {

    if ( ! isset( $_POST['wc_save_order_addresses'] ) ) return;
    if ( ! isset( $_POST['wc_edit_order_nonce'] ) || ! wp_verify_nonce( $_POST['wc_edit_order_nonce'], 'wc_edit_order_addresses' ) ) return;

    $order_id = absint( $_POST['order_id'] );
    $order    = wc_get_order( $order_id );

    if ( ! $order || ! $order->has_status( 'processing' ) ) return;
    if ( ! is_user_logged_in() && ( $_GET['key'] ?? '' ) !== $order->get_order_key() ) return;

    // Block editing of sub (extra payment) orders
    if ( $order->get_meta( '_parent_order_id' ) ) {
        wc_add_notice( __( 'This order cannot be edited.', 'woocommerce' ), 'error' );
        wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
        exit;
    }


    // 30-minute edit limit
    if ( ( time() - $order->get_date_created()->getTimestamp() ) > 30 * 60 ) {
        wc_add_notice( __( 'You can no longer edit this order.', 'woocommerce' ), 'error' );
        wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
        exit;
    }

    // Only allow one-time edit
    if ( $order->get_meta( '_has_edited_addresses' ) ) {
        wc_add_notice( __( 'You have already edited this order once. Further edits are not allowed.', 'woocommerce' ), 'error' );
        wp_safe_redirect( wp_get_referer() ?: $order->get_view_order_url() );
        exit;
    }

    // Calculate old shipping total
    $old_shipping = 0;
    foreach ( $order->get_items( 'shipping' ) as $item ) {
        $old_shipping += $item->get_total();
    }

    // Update shipping address
    $order->set_address( array(
        'first_name' => sanitize_text_field( $_POST['shipping_first_name'] ?? '' ),
        'last_name'  => sanitize_text_field( $_POST['shipping_last_name'] ?? '' ),
        'address_1'  => sanitize_text_field( $_POST['shipping_address_1'] ?? '' ),
        'address_2'  => sanitize_text_field( $_POST['shipping_address_2'] ?? '' ),
        'city'       => sanitize_text_field( $_POST['shipping_city'] ?? '' ),
        'state'      => sanitize_text_field( $_POST['shipping_state'] ?? '' ),
        'postcode'   => sanitize_text_field( $_POST['shipping_postcode'] ?? '' ),
        'country'    => sanitize_text_field( $_POST['shipping_country'] ?? '' ),
    ), 'shipping' );

    // Determine shipping zone & new shipping total
    $zone    = WC_Shipping_Zones::get_zone_matching_package(array(
        'destination' => array(
            'country'  => $order->get_shipping_country(),
            'state'    => $order->get_shipping_state(),
            'postcode' => $order->get_shipping_postcode(),
        ),
    ));

    $methods = $zone ? $zone->get_shipping_methods( true ) : array();
    $new_shipping_total = 0;

    if ( ! empty( $methods ) ) {
        foreach ( $methods as $method ) {
            if ( 'yes' === $method->enabled ) {
                $new_shipping_total += (float) $method->cost;
                break;
            }
        }
    }

    $extra = max( 0, $new_shipping_total - $old_shipping );

    // Mark order as edited
    $order->update_meta_data( '_has_edited_addresses', true );
    $order->save();

    $payment_method = $order->get_payment_method();

    if ( $extra <= 0 ) {
        wc_add_notice( __( 'Shipping updated successfully. No extra payment required.', 'woocommerce' ), 'success' );
        wp_safe_redirect( wp_get_referer() ?: $order->get_view_order_url() );
        exit;
    }

    if ( $payment_method === 'cod' ) {
        $order->add_order_note(
            sprintf( __( 'Shipping increased by %s. Amount will be collected on delivery.', 'woocommerce' ), wc_price( $extra ) )
        );
        wc_add_notice(
            sprintf( __( 'Shipping increased by %s. Payable on delivery.', 'woocommerce' ), wc_price( $extra ) ),
            'notice'
        );
        wp_safe_redirect( wp_get_referer() ?: $order->get_view_order_url() );
        exit;
    }

    if ( in_array( $payment_method, array( 'bacs', 'stripe', 'paypal' ), true ) ) {
        wc_create_extra_shipping_order( $order, $extra );
    }
}

/**
 * Create extra order for online payment shipping adjustment
 */
function wc_create_extra_shipping_order( $order, $extra ) {

    $extra_product_id = get_option( '_wc_shipping_adjustment_product_id' );
    $extra_product    = $extra_product_id ? wc_get_product( $extra_product_id ) : false;

    if ( ! $extra_product ) {
        $extra_product = new WC_Product_Simple();
        $extra_product->set_name( __( 'Shipping Adjustment', 'woocommerce' ) );
        $extra_product->set_regular_price( 0 );
        $extra_product->set_catalog_visibility( 'hidden' );
        $extra_product->set_virtual( true );
        $extra_product->set_sold_individually( true );
        $extra_product->set_manage_stock( false );
        $extra_product->set_purchasable( false );
        $extra_product->save();
        update_option( '_wc_shipping_adjustment_product_id', $extra_product->get_id() );
    }

    $extra_order = wc_create_order();

    $item = new WC_Order_Item_Product();
    $item->set_product( $extra_product );
    $item->set_quantity( 1 );
    $item->set_subtotal( $extra );
    $item->set_total( $extra );
    $item->add_meta_data( '_hide_in_order', true );
    $extra_order->add_item( $item );

    $extra_order->set_customer_id( $order->get_customer_id() );
    $extra_order->set_address( $order->get_address( 'billing' ), 'billing' );
    $extra_order->set_address( $order->get_address( 'shipping' ), 'shipping' );
    $extra_order->set_payment_method( $order->get_payment_method() );
    $extra_order->calculate_totals();
    $extra_order->update_meta_data( '_parent_order_id', $order->get_id() );
    $extra_order->save();

    $order->add_order_note(
        sprintf( __( 'Extra payment order #%d created for shipping difference.', 'woocommerce' ), $extra_order->get_id() )
    );
    $order->save();

    $extra_order->add_order_note(
        sprintf( __( 'This order is an extra payment for shipping difference of order #%d.', 'woocommerce' ), $order->get_id() )
    );
    $extra_order->save();

    wc_add_notice(
        sprintf(
            __( 'Shipping increased by %s. <a href="%s">Pay the extra amount</a>.', 'woocommerce' ),
            wc_price( $extra ),
            esc_url( $extra_order->get_checkout_payment_url() )
        ),
        'notice'
    );

    wp_safe_redirect( wp_get_referer() ?: $order->get_view_order_url() );
    exit;
}

/**
 * Hide Shipping Adjustment product in emails & order items
 */
add_filter( 'woocommerce_order_item_visible', 'wc_hide_shipping_adjustment_product', 10, 2 );
function wc_hide_shipping_adjustment_product( $visible, $item ) {
    if ( $item->get_product() && $item->get_product()->get_catalog_visibility() === 'hidden' ) {
        return false;
    }
    return $visible;
}

add_action( 'woocommerce_order_status_cancelled', 'wc_cancel_child_orders_when_parent_cancelled', 10, 1 );
function wc_cancel_child_orders_when_parent_cancelled( $parent_order_id ) {

    // ❌ Skip if cancelled from Admin dashboard
    if ( is_admin() && ! wp_doing_ajax() ) {
        return;
    }

    $parent_order = wc_get_order( $parent_order_id );
    if ( ! $parent_order ) {
        return;
    }

    $child_orders = wc_get_orders( array(
        'limit'      => -1,
        'meta_key'   => '_parent_order_id',
        'meta_value' => $parent_order_id,
        'status'     => array( 'pending', 'on-hold', 'processing' ),
    ) );

    foreach ( $child_orders as $child_order ) {
        $child_order->update_status(
            'cancelled',
            __( 'Cancelled automatically because the customer cancelled the parent order.', 'woocommerce' )
        );
    }
}
