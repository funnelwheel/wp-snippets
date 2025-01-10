<?php
/**
 * Plugin Name: WooCommerce Country Based Pricing
 * Plugin URI:  https://upnrunn.com/tips-tricks/set-up-country-specific-discounts-in-woocommerce/
 * Description: Change product prices in WooCommerce based on the user's country using geolocation.
 * Version:     1.0
 * Author:      Your Name
 * Author URI:  https://upnrunn.com
 * License:     GPLv3 or later
 * Text Domain: wc-country-based-pricing
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WC_Country_Based_Pricing
 */
class WC_Country_Based_Pricing {

    public function __construct() {
        // Hook into WooCommerce price filters
        add_filter( 'woocommerce_product_get_price', [ $this, 'adjust_price_based_on_country' ], 999, 2 );
        add_filter( 'woocommerce_product_get_regular_price', [ $this, 'adjust_price_based_on_country' ], 999, 2 );

        // Ensure geolocation works correctly
        add_action( 'init', [ $this, 'ensure_geolocation_enabled' ] );
    }

    /**
     * Adjust product price based on the user's country.
     *
     * @param float $price   Original product price.
     * @param object $product WooCommerce product object.
     *
     * @return float Adjusted product price.
     */
    public function adjust_price_based_on_country( $price, $product ) {
	    // Get the user's country
	    $country = $this->get_user_country(); // should return IN/US/UK etc, but please do the Geo Location setup correctly. 

	    $country = 'IN'; // For the time being, we are setting it up as India. 

	    // Define country-specific discounts
	    $country_discounts = [
	        'US' => 5,  // Discount amount for United States
	        'IN' => 3,  // Discount amount for India
	        'UK' => 7,  // Discount amount for United Kingdom
	    ];

	    // Check if the country exists in the discounts array
	    if ( array_key_exists( $country, $country_discounts ) ) {
	        $discount = $country_discounts[ $country ];
	        $price -= $discount; // Apply the discount
	        $price = max( 0, $price ); // Ensure the price does not go below 0
	    }

	    return $price; // Return the adjusted price
	}


    /**
     * Get the user's country using WooCommerce Geolocation.
     *
     * @return string User's country code (e.g., 'US', 'IN').
     */
    private function get_user_country() {
        if ( class_exists( 'WC_Geolocation' ) ) {
            $location = WC_Geolocation::geolocate_ip();
            return $location['country'] ?? '';
        }

        return '';
    }

    /**
     * Ensure geolocation is enabled in WooCommerce settings.
     */
    public function ensure_geolocation_enabled() {
        $options = get_option( 'woocommerce_default_customer_address' );

        if ( $options !== 'geolocation' && $options !== 'geolocation_ajax' ) {
            update_option( 'woocommerce_default_customer_address', 'geolocation_ajax' );
        }
    }
}

// Initialize the plugin
new WC_Country_Based_Pricing();
