<?php
/**
 * Plugin Name: FunnelWheel Order Edits
 * Plugin URI:  https://github.com/funnelwheel/
 * Description: Allows customers and guests to edit billing and shipping addresses for processing orders within 30 minutes. Recalculates shipping and handles extra payment if needed.
 * Version:     1.4.1
 * Author:      Kishores
 * Author URI:  https://kishoresahoo.wordpress.com/
 * Text Domain: fw-order-edits
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 * Requires PHP: 7.4
 */

namespace FunnelWheel\OrderEdits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WC_Order;
use WC_Order_Item_Product;
use WC_Product_Simple;
use WC_Shipping_Zones;

class OrderEdits {

    public function __construct() {
        add_filter( 'woocommerce_valid_order_statuses_for_cancel', [ $this, 'allow_cancel_paid_orders' ], 10, 2 );
        add_filter( 'woocommerce_my_account_my_orders_actions', [ $this, 'add_edit_order_action' ], 10, 2 );
        add_action( 'woocommerce_order_details_after_customer_details', [ $this, 'display_edit_form' ] );
        add_action( 'template_redirect', [ $this, 'save_addresses_and_recalculate' ] );
        add_filter( 'woocommerce_order_item_visible', [ $this, 'hide_shipping_adjustment_product' ], 10, 2 );
        add_action( 'woocommerce_order_status_cancelled', [ $this, 'cancel_child_orders' ], 10, 1 );
    }

    public function allow_cancel_paid_orders( $statuses, $order ) {
        if ( ! $order instanceof WC_Order ) return $statuses;

        if ( $order->get_meta( '_parent_order_id' ) ) {
            return array_diff( $statuses, [ 'processing', 'pending', 'on-hold' ] );
        }

        if ( $order->has_status( 'processing' ) && ( time() - $order->get_date_created()->getTimestamp() ) <= 30 * 60 ) {
            $statuses[] = 'processing';
        }

        return $statuses;
    }

    public function add_edit_order_action( $actions, $order ) {
        if ( ! $order instanceof WC_Order ) return $actions;
        if ( ! $order->has_status( 'processing' ) ) return $actions;
        if ( $order->get_meta( '_parent_order_id' ) ) return $actions;
        if ( ( time() - $order->get_date_created()->getTimestamp() ) > 30 * 60 ) return $actions;
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
            'name' => __( 'Edit Order', 'fw-order-edits' ),
        ];

        return $actions;
    }

    public function display_edit_form( $order ) {
        if ( ! $order instanceof WC_Order ) return;
        if ( ! $order->has_status( 'processing' ) ) return;
        if ( $order->get_meta( '_parent_order_id' ) ) return;

        $deadline = $order->get_date_created()->getTimestamp() + 30 * 60;
        if ( time() > $deadline ) {
            echo '<p>' . esc_html__( 'The 30-minute editing window has expired.', 'fw-order-edits' ) . '</p>';
            return;
        }

        if ( $order->get_meta( '_has_edited_addresses' ) ) {
            echo '<p>' . esc_html__( 'You have already edited this order. Editing is disabled.', 'fw-order-edits' ) . '</p>';
            return;
        }

        if ( ! is_user_logged_in() && ( $_GET['key'] ?? '' ) !== $order->get_order_key() ) return;

        ?>
        <h3><?php esc_html_e( 'Edit Order Addresses', 'fw-order-edits' ); ?></h3>
        <form method="post">
            <?php wp_nonce_field( 'fw_edit_order_addresses', 'fw_edit_order_nonce' ); ?>
            <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">

            <h4><?php esc_html_e( 'Billing Address', 'fw-order-edits' ); ?></h4>
            <?php
            foreach ( WC()->countries->get_address_fields( $order->get_billing_country(), 'billing_' ) as $key => $field ) {
                woocommerce_form_field( $key, $field, $order->{"get_$key"}() );
            }
            ?>

            <h4><?php esc_html_e( 'Shipping Address', 'fw-order-edits' ); ?></h4>
            <?php
            foreach ( WC()->countries->get_address_fields( $order->get_shipping_country(), 'shipping_' ) as $key => $field ) {
                woocommerce_form_field( $key, $field, $order->{"get_$key"}() );
            }
            ?>

            <button type="submit" name="fw_save_order_addresses" class="button">
                <?php esc_html_e( 'Save Order', 'fw-order-edits' ); ?>
            </button>
        </form>
        <?php
    }

    public function save_addresses_and_recalculate() {
        if ( ! isset( $_POST['fw_save_order_addresses'] ) ) return;
        if ( ! isset( $_POST['fw_edit_order_nonce'] ) || ! wp_verify_nonce( $_POST['fw_edit_order_nonce'], 'fw_edit_order_addresses' ) ) return;

        $order_id = absint( $_POST['order_id'] );
        $order    = wc_get_order( $order_id );

        if ( ! $order || ! $order->has_status( 'processing' ) ) return;
        if ( ! is_user_logged_in() && ( $_GET['key'] ?? '' ) !== $order->get_order_key() ) return;
        if ( $order->get_meta( '_parent_order_id' ) ) {
            wc_add_notice( __( 'This order cannot be edited.', 'fw-order-edits' ), 'error' );
            wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
            exit;
        }

        if ( ( time() - $order->get_date_created()->getTimestamp() ) > 30 * 60 ) {
            wc_add_notice( __( 'You can no longer edit this order.', 'fw-order-edits' ), 'error' );
            wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
            exit;
        }

        if ( $order->get_meta( '_has_edited_addresses' ) ) {
            wc_add_notice( __( 'You have already edited this order once. Further edits are not allowed.', 'fw-order-edits' ), 'error' );
            wp_safe_redirect( wp_get_referer() ?: $order->get_view_order_url() );
            exit;
        }

        // Save billing
        $billing_fields = [ 'first_name','last_name','company','address_1','address_2','city','state','postcode','country','email','phone' ];
        $billing_data = [];
        foreach ( $billing_fields as $field ) {
            $billing_data[ $field ] = sanitize_text_field( $_POST["billing_$field"] ?? '' );
        }
        $order->set_address( $billing_data, 'billing' );

        // Save shipping
        $shipping_fields = [ 'first_name','last_name','company','address_1','address_2','city','state','postcode','country' ];
        $shipping_data = [];
        foreach ( $shipping_fields as $field ) {
            $shipping_data[ $field ] = sanitize_text_field( $_POST["shipping_$field"] ?? '' );
        }
        $order->set_address( $shipping_data, 'shipping' );

        // Recalculate shipping
        $old_shipping = 0;
        foreach ( $order->get_items( 'shipping' ) as $item ) {
            $old_shipping += $item->get_total();
        }

        $zone = WC_Shipping_Zones::get_zone_matching_package([
            'destination' => [
                'country'  => $order->get_shipping_country(),
                'state'    => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
            ],
        ]);

        $methods = $zone ? $zone->get_shipping_methods( true ) : [];
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

        $order->update_meta_data( '_has_edited_addresses', true );
        $order->save();

        $payment_method = $order->get_payment_method();

        if ( $extra <= 0 ) {
            wc_add_notice( __( 'Shipping updated successfully. No extra payment required.', 'fw-order-edits' ), 'success' );
            wp_safe_redirect( wp_get_referer() ?: $order->get_view_order_url() );
            exit;
        }

        if ( $payment_method === 'cod' ) {
            $order->add_order_note( sprintf( __( 'Shipping increased by %s. Amount will be collected on delivery.', 'fw-order-edits' ), wc_price( $extra ) ) );
            wc_add_notice( sprintf( __( 'Shipping increased by %s. Payable on delivery.', 'fw-order-edits' ), wc_price( $extra ) ), 'notice' );
            wp_safe_redirect( wp_get_referer() ?: $order->get_view_order_url() );
            exit;
        }

        if ( in_array( $payment_method, [ 'bacs','stripe','paypal' ], true ) ) {
            $this->create_extra_shipping_order( $order, $extra );
        }
    }

    private function create_extra_shipping_order( $order, $extra ) {
        $extra_product_id = get_option( '_fw_shipping_adjustment_product_id' );
        $extra_product = $extra_product_id ? wc_get_product( $extra_product_id ) : false;

        if ( ! $extra_product ) {
            $extra_product = new WC_Product_Simple();
            $extra_product->set_name( __( 'Shipping Adjustment', 'fw-order-edits' ) );
            $extra_product->set_regular_price( 0 );
            $extra_product->set_catalog_visibility( 'hidden' );
            $extra_product->set_virtual( true );
            $extra_product->set_sold_individually( true );
            $extra_product->set_manage_stock( false );
            // ❌ Removed set_purchasable() to avoid fatal error
            $extra_product->save();
            update_option( '_fw_shipping_adjustment_product_id', $extra_product->get_id() );
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

        $order->add_order_note( sprintf( __( 'Extra payment order #%d created for shipping difference.', 'fw-order-edits' ), $extra_order->get_id() ) );
        $order->save();

        $extra_order->add_order_note( sprintf( __( 'This order is an extra payment for shipping difference of order #%d.', 'fw-order-edits' ), $order->get_id() ) );
        $extra_order->save();

        wc_add_notice(
            sprintf(
                __( 'Shipping increased by %s. <a href="%s">Pay the extra amount</a>.', 'fw-order-edits' ),
                wc_price( $extra ),
                esc_url( $extra_order->get_checkout_payment_url() )
            ),
            'notice'
        );

        wp_safe_redirect( wp_get_referer() ?: $order->get_view_order_url() );
        exit;
    }

    public function hide_shipping_adjustment_product( $visible, $item ) {
        if ( $item->get_product() && $item->get_product()->get_catalog_visibility() === 'hidden' ) {
            return false;
        }
        return $visible;
    }

    public function cancel_child_orders( $parent_order_id ) {
        if ( is_admin() && ! wp_doing_ajax() ) return;

        $parent_order = wc_get_order( $parent_order_id );
        if ( ! $parent_order ) return;

        $child_orders = wc_get_orders([
            'limit'      => -1,
            'meta_key'   => '_parent_order_id',
            'meta_value' => $parent_order_id,
            'status'     => [ 'pending', 'on-hold', 'processing' ],
        ]);

        foreach ( $child_orders as $child_order ) {
            $child_order->update_status(
                'cancelled',
                __( 'Cancelled automatically because the customer cancelled the parent order.', 'fw-order-edits' )
            );
        }
    }

}

new OrderEdits();
