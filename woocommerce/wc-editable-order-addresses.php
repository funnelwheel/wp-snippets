<?php
/**
 * Plugin Name: WooCommerce Editable Order Addresses
 * Plugin URI:  https://github.com/funnelwheel/
 * Description: Allows customers and guests to edit billing and shipping addresses for processing orders within 30 minutes.
 * Version:     1.0.0
 * Author:      Kishores
 * Author URI:  https://kishoresahoo.wordpress.com/
 * Text Domain: wc-editable-order-addresses
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Allow canceling paid orders for Processing orders within 30 minutes
 */
add_filter( 'woocommerce_valid_order_statuses_for_cancel', 'wc_allow_cancel_paid_orders_all_users', 10, 2 );
function wc_allow_cancel_paid_orders_all_users( $statuses, $order ) {
    if ( ! $order instanceof WC_Order ) return $statuses;

    if ( ! $order->has_status( 'processing' ) ) return $statuses;

    if ( ( time() - $order->get_date_created()->getTimestamp() ) <= 30 * 60 ) {
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

    // Default URL
    $url = $order->get_view_order_url();

    // For guests, include order key in URL
    if ( ! is_user_logged_in() ) {
        $url = add_query_arg( 'key', $order->get_order_key(), wc_get_endpoint_url( 'view-order', $order->get_id(), wc_get_page_permalink( 'myaccount' ) ) );
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

    // Guest verification: only allow if correct order key
    if ( ! is_user_logged_in() ) {
        $order_key = $_GET['key'] ?? '';
        if ( $order_key !== $order->get_order_key() ) return;
    }

    ?>
    <h3><?php esc_html_e( 'Edit Order Addresses', 'woocommerce' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'wc_edit_order_addresses', 'wc_edit_order_nonce' ); ?>
        <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">

        <h4><?php esc_html_e( 'Billing Address', 'woocommerce' ); ?></h4>
        <?php
        $billing_fields = WC()->countries->get_address_fields( $order->get_billing_country(), 'billing_' );
        foreach ( $billing_fields as $key => $field ) {
            woocommerce_form_field( $key, $field, $order->{"get_$key"}() );
        }
        ?>

        <h4><?php esc_html_e( 'Shipping Address', 'woocommerce' ); ?></h4>
        <?php
        $shipping_fields = WC()->countries->get_address_fields( $order->get_shipping_country(), 'shipping_' );
        foreach ( $shipping_fields as $key => $field ) {
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
 * Save billing + shipping addresses
 */
add_action( 'template_redirect', 'wc_save_order_addresses' );
function wc_save_order_addresses() {

    if ( ! isset( $_POST['wc_save_order_addresses'] ) ) return;

    if ( ! isset( $_POST['wc_edit_order_nonce'] ) || ! wp_verify_nonce( $_POST['wc_edit_order_nonce'], 'wc_edit_order_addresses' ) ) return;

    $order_id = absint( $_POST['order_id'] );
    $order = wc_get_order( $order_id );
    if ( ! $order || ! $order->has_status( 'processing' ) ) return;

    // Guest verification: only allow if correct order key
    if ( ! is_user_logged_in() ) {
        $order_key = $_GET['key'] ?? '';
        if ( $order_key !== $order->get_order_key() ) return;
    }

    if ( ( time() - $order->get_date_created()->getTimestamp() ) > 30 * 60 ) {
        wc_add_notice( __( 'You can no longer edit this order.', 'woocommerce' ), 'error' );
        wp_safe_redirect( wc_get_account_endpoint_url('orders') );
        exit;
    }

    // Billing
    $billing_fields = [
        'first_name' => sanitize_text_field( $_POST['billing_first_name'] ?? '' ),
        'last_name'  => sanitize_text_field( $_POST['billing_last_name'] ?? '' ),
        'company'    => sanitize_text_field( $_POST['billing_company'] ?? '' ),
        'address_1'  => sanitize_text_field( $_POST['billing_address_1'] ?? '' ),
        'address_2'  => sanitize_text_field( $_POST['billing_address_2'] ?? '' ),
        'city'       => sanitize_text_field( $_POST['billing_city'] ?? '' ),
        'state'      => sanitize_text_field( $_POST['billing_state'] ?? '' ),
        'postcode'   => sanitize_text_field( $_POST['billing_postcode'] ?? '' ),
        'country'    => sanitize_text_field( $_POST['billing_country'] ?? '' ),
        'email'      => sanitize_email( $_POST['billing_email'] ?? '' ),
        'phone'      => sanitize_text_field( $_POST['billing_phone'] ?? '' ),
    ];

    // Shipping
    $shipping_fields = [
        'first_name' => sanitize_text_field( $_POST['shipping_first_name'] ?? '' ),
        'last_name'  => sanitize_text_field( $_POST['shipping_last_name'] ?? '' ),
        'company'    => sanitize_text_field( $_POST['shipping_company'] ?? '' ),
        'address_1'  => sanitize_text_field( $_POST['shipping_address_1'] ?? '' ),
        'address_2'  => sanitize_text_field( $_POST['shipping_address_2'] ?? '' ),
        'city'       => sanitize_text_field( $_POST['shipping_city'] ?? '' ),
        'state'      => sanitize_text_field( $_POST['shipping_state'] ?? '' ),
        'postcode'   => sanitize_text_field( $_POST['shipping_postcode'] ?? '' ),
        'country'    => sanitize_text_field( $_POST['shipping_country'] ?? '' ),
    ];

    // Save using WooCommerce CRUD
    if ( method_exists( $order, 'set_address' ) ) {
        $order->set_address( $billing_fields, 'billing' );
        $order->set_address( $shipping_fields, 'shipping' );
        $order->save();
        $order->add_order_note( __( 'Customer updated billing & shipping addresses.', 'woocommerce' ) );

        // Ensure session is started before adding notice
        if ( function_exists('wc') && wc()->session ) {
            wc_add_notice( __( 'Order addresses updated successfully.', 'woocommerce' ), 'success' );
        }
    }

    // Redirect back safely
    $redirect_url = wp_get_referer() ?: wc_get_account_endpoint_url('orders');
    wp_safe_redirect( $redirect_url );
    exit;
}
