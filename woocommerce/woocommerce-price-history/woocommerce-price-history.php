<?php
/**
 * Plugin Name: WooCommerce Price History Tracker
 * Description: Track and display price change history for WooCommerce products, including variations.
 * Version: 1.3.0
 * Author: Your Name
 * License: GPLv2 or later
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_Price_History_Tracker {

    public function __construct() {
        add_action( 'save_post_product', [ $this, 'track_price_changes_on_save' ], 20 );
        add_action( 'woocommerce_save_product_variation', [ $this, 'track_variation_price_changes' ], 20, 2 );
        add_action( 'add_meta_boxes', [ $this, 'register_price_history_meta_box' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_chart_script' ] );
    }

    public function enqueue_chart_script( $hook ) {
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }

        // Enqueue Chart.js (you could check if WooCommerce already loads it, or load a CDN)
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            '4.4.0',
            true
        );
    }


    /**
     * Track price changes for simple or parent product.
     */
    public function track_price_changes_on_save( $post_id ) {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;

        $product = wc_get_product( $post_id );
        if ( ! $product ) return;

        if ( $product->is_type( 'simple' ) ) {
            $this->track_price_changes( $product );
        }
    }

    /**
     * Track variation price changes (triggered per variation save).
     */
    public function track_variation_price_changes( $variation_id, $i ) {
        $variation = wc_get_product( $variation_id );
        if ( ! $variation ) return;

        $this->track_price_changes( $variation );
    }

    /**
     * Track price changes for a given product/variation.
     */
    public function track_price_changes( $product ) {
        if ( ! $product instanceof WC_Product ) return;

        $post_id     = $product->get_id();
        $new_regular = $product->get_regular_price();
        $new_sale    = $product->get_sale_price();

        $history = get_post_meta( $post_id, '_price_history', true );
        if ( ! is_array( $history ) ) {
            $history = [];
        }

        $last_entry = end( $history );

        if (
            ! $last_entry ||
            $last_entry['regular_price'] !== $new_regular ||
            $last_entry['sale_price'] !== $new_sale
        ) {
            $history[] = [
                'time'          => current_time( 'mysql' ),
                'regular_price' => $new_regular,
                'sale_price'    => $new_sale,
            ];
            update_post_meta( $post_id, '_price_history', $history );
        }
    }

    /**
     * Register meta box for product screen.
     */
    public function register_price_history_meta_box() {
        add_meta_box(
            'wc_price_history',
            __( 'Price Change History', 'woocommerce-price-history' ),
            [ $this, 'render_price_history_meta_box' ],
            'product',
            'normal',
            'default'
        );
    }

    /**
     * Render the price history box.
     */
    public function render_price_history_meta_box( $post ) {
        $product = wc_get_product( $post->ID );
        if ( ! $product ) return;

        echo '<style>.price-history-table {margin-bottom:20px}</style>';

        // Simple product history
        if ( $product->is_type( 'simple' ) ) {
            echo '<h4>' . esc_html__( 'Simple Product Price History', 'woocommerce-price-history' ) . '</h4>';
            $this->render_table( $post->ID );
        }

        // Variable product with variations
        if ( $product->is_type( 'variable' ) ) {
            echo '<h4>' . esc_html__( 'Parent Product Price History', 'woocommerce-price-history' ) . '</h4>';
            $this->render_table( $post->ID );

            $variations = $product->get_children();
            if ( empty( $variations ) ) {
                echo '<p>' . esc_html__( 'No variations found.', 'woocommerce-price-history' ) . '</p>';
                return;
            }

            foreach ( $variations as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( ! $variation ) continue;

                $history = get_post_meta( $variation_id, '_price_history', true );
                if ( empty( $history ) || ! is_array( $history ) ) {
                    continue; // ❌ Skip rendering if no history
                }

                echo '<h4>' . sprintf(
                    esc_html__( 'Variation #%d - %s', 'woocommerce-price-history' ),
                    $variation_id,
                    wc_get_formatted_variation( $variation, true, false, true )
                ) . '</h4>';

                $this->render_table( $variation_id );
            }


        }
    }

    /**
     * Render a price history table.
     */
    private function render_table( $post_id ) {
        $history = get_post_meta( $post_id, '_price_history', true );

        if ( empty( $history ) || ! is_array( $history ) ) {
            echo '<p>' . esc_html__( 'No price changes recorded yet.', 'woocommerce-price-history' ) . '</p>';
            return;
        }

        echo '<table class="widefat fixed striped price-history-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Date', 'woocommerce-price-history' ) . '</th>';
        echo '<th>' . esc_html__( 'Regular Price', 'woocommerce-price-history' ) . '</th>';
        echo '<th>' . esc_html__( 'Sale Price', 'woocommerce-price-history' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( array_reverse( $history ) as $entry ) {
            echo '<tr>';
            echo '<td>' . esc_html( date( 'Y-m-d H:i', strtotime( $entry['time'] ) ) ) . '</td>';
            echo '<td>' . wc_price( $entry['regular_price'] ) . '</td>';
            echo '<td>' . ( $entry['sale_price'] !== '' ? wc_price( $entry['sale_price'] ) : '-' ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        // Add chart canvas
        $chart_id = 'chart_' . esc_attr( $post_id );
        echo '<canvas id="' . $chart_id . '" height="200" style="margin-top:20px;"></canvas>';

        // Prepare data for JS
        $dates = [];
        $regular_prices = [];
        $sale_prices = [];

        foreach ( $history as $entry ) {
            $dates[]          = esc_js( date( 'Y-m-d', strtotime( $entry['time'] ) ) );
            $regular_prices[] = floatval( $entry['regular_price'] );
            $sale_prices[]    = $entry['sale_price'] !== '' ? floatval( $entry['sale_price'] ) : null;
        }

        echo '<script>
        document.addEventListener("DOMContentLoaded", function () {
            const ctx = document.getElementById("' . $chart_id . '").getContext("2d");

            new Chart(ctx, {
                type: "line",
                data: {
                    labels: ' . json_encode( $dates ) . ',
                    datasets: [
                        {
                            label: "Regular Price",
                            data: ' . json_encode( $regular_prices ) . ',
                            borderColor: "rgba(54, 162, 235, 1)",
                            fill: false,
                            tension: 0.2
                        },
                        {
                            label: "Sale Price",
                            data: ' . json_encode( $sale_prices ) . ',
                            borderColor: "rgba(255, 99, 132, 1)",
                            fill: false,
                            tension: 0.2
                        }
                    ]
                },
                options: {
                    plugins: {
                        legend: { position: "top" }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value, index, ticks) {
                                    return "₹" + value;
                                }
                            }
                        }
                    }
                }
            });
        });
        </script>';

            }
}

new WC_Price_History_Tracker();
