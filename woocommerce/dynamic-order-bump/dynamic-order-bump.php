<?php
/**
 * Plugin Name: Dynamic Order Bump with Conditions
 * Description: Display dynamic order bump products on the checkout page with AJAX updates and complex conditions.
 * Version: 1.0
 * Author: Kishores
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define the interface for product display conditions
interface ProductDisplayCondition {
    public function isSatisfied(): bool;
}

// Cart Total Condition
class CartTotalCondition implements ProductDisplayCondition {
    private $minimumCartTotal;

    public function __construct($minimumCartTotal) {
        $this->minimumCartTotal = $minimumCartTotal;
    }

    public function isSatisfied(): bool {
        $cartTotal = WC()->cart->get_cart_contents_total();
        return $cartTotal >= $this->minimumCartTotal;
    }
}

// Cart Item Count Condition
class CartItemCountCondition implements ProductDisplayCondition {
    private $minimumItems;

    public function __construct($minimumItems) {
        $this->minimumItems = $minimumItems;
    }

    public function isSatisfied(): bool {
        $cartItemCount = WC()->cart->get_cart_contents_count();
        return $cartItemCount >= $this->minimumItems;
    }
}

// Composite Condition
class CompositeCondition implements ProductDisplayCondition {
    private $conditions = [];
    private $logic = 'AND'; // Default to AND logic (can be changed to OR via filter)

    public function setLogic($logic) {
        $this->logic = $logic; // 'AND' or 'OR'
    }

    public function addCondition(ProductDisplayCondition $condition) {
        $this->conditions[] = $condition;
    }

    public function isSatisfied(): bool {
        if ($this->logic === 'OR') {
            // OR Logic: If any condition is satisfied, return true
            foreach ($this->conditions as $condition) {
                if ($condition->isSatisfied()) {
                    return true;
                }
            }
            return false;
        } else {
            // AND Logic: All conditions must be satisfied
            foreach ($this->conditions as $condition) {
                if (!$condition->isSatisfied()) {
                    return false;
                }
            }
            return true;
        }
    }
}

// Condition Provider - dynamically provides the conditions
class ConditionProvider {
    public function getConditions(): array {
        // Allow external code to modify or add new conditions
        $conditions = apply_filters('dynamic_order_bump_conditions', [
            new CartTotalCondition(500), // Default: Cart Total Condition
            new CartItemCountCondition(2), // Default: Cart Item Count Condition
        ]);

        return $conditions;
    }
}

// Main Plugin Class
class DynamicOrderBump {
    public $order_bump_position_hook = 'woocommerce_checkout_before_order_review';  // Default hook position for order bump (can be changed dynamically)

    public function __construct() {
        // Enqueue scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Add the order bump section at the dynamic hook position with a custom priority
        add_action($this->order_bump_position_hook, [$this, 'display_order_bump_section'], 20);
        
        // Handle AJAX for fetching products and adding to cart
        add_action('wp_ajax_get_order_bump_products', [$this, 'get_order_bump_products']);
        add_action('wp_ajax_nopriv_get_order_bump_products', [$this, 'get_order_bump_products']);
        add_action('wp_ajax_add_product_to_cart', [$this, 'add_product_to_cart']);
        add_action('wp_ajax_nopriv_add_product_to_cart', [$this, 'add_product_to_cart']);
    }

    /**
     * Enqueue scripts and pass data to JS.
     */
    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_script('order-bump-script', plugin_dir_url(__FILE__) . 'assets/order-bump.js', ['jquery'], '1.0', true);
            wp_localize_script('order-bump-script', 'orderBumpConfig', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'currencySymbol' => get_woocommerce_currency_symbol(),
            ]);
        }
    }

    /**
     * Display the order bump section in the checkout page.
     */
    public function display_order_bump_section() {
        echo '<div id="order-bump-products">Loading order bump products...</div>';
    }

    /**
     * Get order bump products with dynamic conditions
     */
    public function get_order_bump_products() {
        $compositeCondition = new CompositeCondition();

        // Dynamically add default conditions from the ConditionProvider
        $conditionProvider = new ConditionProvider();
        foreach ($conditionProvider->getConditions() as $condition) {
            $compositeCondition->addCondition($condition);
        }

        // Allow external developers to add custom conditions via a hook
        do_action('add_order_bump_conditions', $compositeCondition);

        // Allow external developers to set AND/OR logic via a hook
        $compositeCondition->setLogic(apply_filters('order_bump_conditions_logic', 'AND'));

        $products = [];

        // Check if all conditions are satisfied
        if ($compositeCondition->isSatisfied()) {
            $product_ids = [187, 36]; // Replace with your dynamic logic to fetch product IDs
            foreach ($product_ids as $id) {
                $product = wc_get_product($id);
                if ($product && $product->is_purchasable() && $product->is_in_stock()) {
                    $products[] = [
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'price' => wc_price($product->get_price()),
                        'image' => wp_get_attachment_image_src($product->get_image_id(), 'thumbnail')[0],
                    ];
                }
            }
        }

        if (!empty($products)) {
            wp_send_json_success($products);
        } else {
            wp_send_json_error(['message' => 'No products meet the conditions.']);
        }
    }

    /**
     * Add product to cart.
     */
    public function add_product_to_cart() {
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

        if ($product_id > 0) {
            $product = wc_get_product($product_id);

            if ($product && $product->is_purchasable() && $product->is_in_stock()) {
                $added = WC()->cart->add_to_cart($product_id, $quantity);

                if ($added) {
                    WC()->cart->calculate_totals();
                    wp_send_json_success(['message' => 'Product added to cart.']);
                } else {
                    wp_send_json_error(['message' => 'Failed to add product to cart. Please try again.']);
                }
            } else {
                wp_send_json_error(['message' => 'This product is not purchasable or is out of stock.']);
            }
        } else {
            wp_send_json_error(['message' => 'Invalid product ID.']);
        }
    }
}

new DynamicOrderBump();

// Custom Condition Example
class UserLoggedInCondition implements ProductDisplayCondition {
    public function isSatisfied(): bool {
        return is_user_logged_in();
    }
}

// Add custom conditions dynamically
add_action('add_order_bump_conditions', function($compositeCondition) {
    $compositeCondition->addCondition(new UserLoggedInCondition());
});

// Set the logic to OR (this could be changed based on admin settings or other conditions)
add_filter('order_bump_conditions_logic', function($logic) {
    return 'OR'; // Can be 'AND' or 'OR'
});

// Add conditions dynamically via hook
add_filter('dynamic_order_bump_conditions', function($conditions) {
    // You can modify or replace default conditions here
    $conditions[] = new UserLoggedInCondition(); // Add UserLoggedInCondition to the array
    return $conditions;
});
