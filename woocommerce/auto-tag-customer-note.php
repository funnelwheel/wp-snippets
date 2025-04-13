<?php
/**
 * Plugin Name: WooCommerce Auto Note Tagger
 * Description: Auto-tags order notes using fuzzy matching (Levenshtein) and stores tags in order meta.
 * Version: 1.0
 * Author: Kishores
 */

if (!defined('ABSPATH')) exit;

add_action('woocommerce_new_order', 'wc_autotagger_process_order', 20, 2);
add_action('woocommerce_process_shop_order_meta', 'wc_autotagger_process_order_from_post', 20, 2);


function wc_autotagger_process_order( $order_id, $order ) {
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
    }

    $note = $order->get_customer_note();
    if ( empty( $note ) ) {
        return;
    }

    $tags = wc_autotagger_detect_tags( $note );

    if ( ! empty( $tags ) ) {
        $order->update_meta_data( 'customer_note_tags', implode( ',', $tags ) );
        $order->save(); // Important: persists changes for HPOS
    }
}


function wc_autotagger_detect_tags($note) {
    $tag_rules = [
        'Urgent' => [
            'keywords' => ['urgent', 'urgnt', 'rush', 'asap', 'immediate', 'now', 'fast'],
            'fuzzy'    => true
        ],
        'Gift' => [
            'keywords' => ['gift', 'gfit', 'gifting', 'gifted', 'giftwrap', 'present'],
            'fuzzy'    => true
        ],
        'Birthday' => [
            'keywords' => ['birthday', 'bday', 'birthdy', 'brithday', 'anniversary', 'b-day'],
            'fuzzy'    => true
        ],
        'International' => [
            'keywords' => ['intl', 'international', 'abroad', 'overseas'],
            'fuzzy'    => false
        ],
        'Delayed' => [
            'keywords' => ['delay', 'delayed', 'late', 'postpone', 'reschedule'],
            'fuzzy'    => true
        ],
        'Thank You' => [
            'keywords' => ['thankyou', 'thanks', 'grateful', 'appreciate'],
            'fuzzy'    => false,
            'only_if_appreciation' => true // custom handling
        ],
    ];

    $threshold = 2;
    $min_score = 1;
    $scores = [];

    // Normalize and tokenize
    $original_note = $note;
    $note = strtolower(remove_accents($note));
    $note = preg_replace('/[^\w\s]/', '', $note); // Remove punctuation
    $words = preg_split('/\s+/', $note);

    foreach ($tag_rules as $tag => $rule) {
        $score = 0;

        foreach ($rule['keywords'] as $keyword) {
            foreach ($words as $word) {
                if (strlen($word) < 3) continue;

                if ($word === $keyword || strpos($word, $keyword) !== false) {
                    $score++;
                    break;
                }

                if (!empty($rule['fuzzy']) && levenshtein($word, $keyword) <= $threshold) {
                    $score++;
                    break;
                }
            }
        }

        // Special handling for Thank You
        if (!empty($rule['only_if_appreciation'])) {
            if ($score > 0 && strlen($original_note) < 100 && preg_match('/^(thank|thanks|grateful|appreciate)/i', trim($original_note))) {
                $scores[$tag] = $score;
            }
        } elseif ($score >= $min_score) {
            $scores[$tag] = $score;
        }
    }

    return array_keys($scores);
}


function wc_autotagger_process_order_from_post($order_id, $post) {
    $order = wc_get_order($order_id);
    if ($order) {
        wc_autotagger_process_order($order_id, $order);
    }
}


add_filter( 'woocommerce_shop_order_list_table_columns', 'add_order_note_tag_column_to_custom_order_table' );
function add_order_note_tag_column_to_custom_order_table( $columns ) {
    $new_columns = [];

    foreach ( $columns as $key => $label ) {
        $new_columns[ $key ] = $label;

        if ( 'order_status' === $key ) {
            $new_columns['customer_note'] = __( 'Customer Note', 'your-textdomain' );
        }
    }

    return $new_columns;
}


add_action( 'woocommerce_shop_order_list_table_custom_column', 'render_order_note_tag_column_content', 10, 2 );
function render_order_note_tag_column_content( $column, $order ) {
    if ( 'customer_note' === $column ) {
        $note = $order->get_customer_note();
        $stored_tags = $order->get_meta( 'customer_note_tags' );

        if ( ! empty( $note ) ) {
            // Try to detect tag in [TAG] or #tag format from note
            if ( preg_match( '/\[(.*?)\]/', $note, $matches ) ) {
                echo '<strong>' . esc_html( $matches[1] ) . '</strong><br>';
            } elseif ( preg_match( '/#(\w+)/', $note, $matches ) ) {
                echo '<strong>' . esc_html( '#' . $matches[1] ) . '</strong><br>';
            } else {
                echo esc_html( wp_trim_words( $note, 5, '...' ) ) . '<br>';
            }
        } else {
            echo '<em>No customer note</em><br>';
        }

        // Display stored tags (detected and saved earlier)
        if ( ! empty( $stored_tags ) ) {
            $tags_array = explode( ',', $stored_tags );
            echo '<div style="margin-top: 5px;">';
            foreach ( $tags_array as $tag ) {
                $color = wc_get_tag_color( $tag ); // 🎨 Use helper for consistent colors

                $filter_url = add_query_arg( array(
                    's'    => urlencode( $tag ),
                    'post_type' => 'shop_order',
                ), admin_url( 'edit.php' ) );


                echo '<a href="' . esc_url( $filter_url ) . '" style="text-decoration: none;">';
                echo '<span style="display:inline-block;background:' . esc_attr( $color['bg'] ) . ';border:1px solid ' . esc_attr( $color['border'] ) . ';border-radius:4px;padding:2px 6px;margin:2px;font-size:11px;color:' . esc_attr( $color['text'] ) . ';">#' . esc_html( $tag ) . '</span>';
                echo '</a>';
            }
            echo '</div>';
        }
    }
}

function wc_get_tag_color( $tag ) {
    $tag_colors = array(
        'urgent'    => array( 'bg' => '#ffe5e5', 'border' => '#ff4d4f', 'text' => '#d9363e' ),
        'international'  => array( 'bg' => '#fff7e6', 'border' => '#faad14', 'text' => '#d48806' ),
        'birthday'       => array( 'bg' => '#e6f7ff', 'border' => '#1890ff', 'text' => '#096dd9' ),
        'gift'  => array( 'bg' => '#f6ffed', 'border' => '#52c41a', 'text' => '#389e0d' ),
    );

    $tag_key = strtolower( trim( $tag ) );
    return $tag_colors[ $tag_key ] ?? array(
        'bg'     => '#f0f0f0',
        'border' => '#ccc',
        'text'   => '#333',
    );
}

