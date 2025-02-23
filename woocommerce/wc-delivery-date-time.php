<?php
/**
 * Plugin Name: WooCommerce Delivery Date & Time
 * Plugin URI: https://upnrunn.com
 * Description: Adds delivery date and time selection to the WooCommerce checkout page with dynamic time slots based on the selected delivery date.
 * Version: 1.0.1
 * Author: Kishores
 * Author URI: https://profiles.wordpress.org/kishores
 * Text Domain: wc-delivery-date-time
 * Domain Path: /languages
 * WC tested up to: 6.9
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Hook into WooCommerce to add delivery date and time fields before or after the billing address.
add_action( 'woocommerce_after_checkout_billing_form', 'wc_add_delivery_date_and_time_fields' );

// Add delivery date and time fields to the checkout page
function wc_add_delivery_date_and_time_fields( $checkout ) {
    echo '<div id="delivery_date_time_fields"><h3>' . __('Delivery Date & Time') . '</h3>';

    // Delivery Date field (Calendar)
    woocommerce_form_field( 'delivery_date', array(
        'type'          => 'text', // Use text for calendar date picker
        'class'         => array('woocommerce-select', 'delivery_date', 'form-row-wide'),
        'label'         => __('Choose Delivery Date'),
        'placeholder'   => __('Select a date'),
        'custom_attributes' => array(
            'id' => 'delivery_date', // Add custom ID for JS initialization
            'readonly' => 'readonly', // Make field readonly to trigger calendar
        ),
    ), $checkout->get_value( 'delivery_date' ));

    // Delivery Time field (Dropdown)
    woocommerce_form_field( 'delivery_time', array(
        'type'          => 'select',
        'class'         => array('woocommerce-select', 'delivery_time', 'form-row-wide'),
        'label'         => __('Choose Delivery Time'),
        'options'       => array(
            '' => __('Select a time'),
        ),
    ), $checkout->get_value( 'delivery_time' ));

    echo '</div>';
}

// Validate the delivery date and time fields
add_action('woocommerce_checkout_process', 'wc_validate_delivery_date_time_fields');

function wc_validate_delivery_date_time_fields() {
    if ( empty( $_POST['delivery_date'] ) )
        wc_add_notice( __( 'Please select a delivery date.' ), 'error' );
    if ( empty( $_POST['delivery_time'] ) )
        wc_add_notice( __( 'Please select a delivery time.' ), 'error' );

    // Enforce buffer time of 2 hours between order and selected delivery time
    $order_time = current_time('timestamp'); // Get current time (order time)
    $delivery_time = strtotime($_POST['delivery_time']); // Get the selected delivery time

    // Check if the delivery time is less than 2 hours from order time
    if ($delivery_time - $order_time < 2 * 60 * 60) { // 2 hours in seconds
        wc_add_notice(__('Delivery time must be at least 2 hours from the order time.'), 'error');
    }
}

// Save delivery date and time to the order meta
add_action( 'woocommerce_checkout_update_order_meta', 'wc_save_delivery_date_time' );

function wc_save_delivery_date_time( $order_id ) {
    if ( ! empty( $_POST['delivery_date'] ) ) {
        update_post_meta( $order_id, '_delivery_date', sanitize_text_field( $_POST['delivery_date'] ) );
    }
    if ( ! empty( $_POST['delivery_time'] ) ) {
        update_post_meta( $order_id, '_delivery_time', sanitize_text_field( $_POST['delivery_time'] ) );
    }
}

// Display delivery date and time on the admin order page
add_action( 'woocommerce_admin_order_data_after_billing_address', 'wc_display_delivery_date_time_in_admin', 10, 1 );

function wc_display_delivery_date_time_in_admin( $order ) {
    $delivery_date = get_post_meta( $order->get_id(), '_delivery_date', true );
    $delivery_time = get_post_meta( $order->get_id(), '_delivery_time', true );

    if ( $delivery_date ) {
        echo '<p><strong>' . __('Delivery Date') . ':</strong> ' . esc_html( $delivery_date ) . '</p>';
    }
    if ( $delivery_time ) {
        echo '<p><strong>' . __('Delivery Time') . ':</strong> ' . esc_html( $delivery_time ) . '</p>';
    }
}

// Display delivery date and time on the thank-you page
add_action( 'woocommerce_thankyou', 'wc_display_delivery_date_time_on_thank_you', 20 );

function wc_display_delivery_date_time_on_thank_you( $order_id ) {
    $order = wc_get_order( $order_id );
    $delivery_date = get_post_meta( $order_id, '_delivery_date', true );
    $delivery_time = get_post_meta( $order_id, '_delivery_time', true );

    if ( $delivery_date ) {
        echo '<p><strong>' . __('Your Delivery Date') . ':</strong> ' . esc_html( $delivery_date ) . '</p>';
    }
    if ( $delivery_time ) {
        echo '<p><strong>' . __('Your Delivery Time') . ':</strong> ' . esc_html( $delivery_time ) . '</p>';
    }
}

// Enqueue JavaScript to initialize the date picker and update time slots dynamically
add_action('wp_enqueue_scripts', 'wc_enqueue_date_picker_script');

function wc_enqueue_date_picker_script() {
    // Ensure jQuery and jQuery UI datepicker are loaded
    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_style( 'jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css' );

    // Pass localized data to JS (time slots saved in the settings)
    $time_slots_data = array(
        'sunday'    => get_option( 'delivery_time_slot_sunday', '' ),
        'monday'    => get_option( 'delivery_time_slot_monday', '' ),
        'tuesday'   => get_option( 'delivery_time_slot_tuesday', '' ),
        'wednesday' => get_option( 'delivery_time_slot_wednesday', '' ),
        'thursday'  => get_option( 'delivery_time_slot_thursday', '' ),
        'friday'    => get_option( 'delivery_time_slot_friday', '' ),
        'saturday'  => get_option( 'delivery_time_slot_saturday', '' ),
    );


    wp_localize_script( 'jquery-ui-datepicker', 'delivery_time_slots', $time_slots_data );

    // Initialize the calendar date picker and update the time slots dynamically
    wp_add_inline_script( 'jquery-ui-datepicker', "
        jQuery(document).ready(function($) {
            console.log('Delivery Time Slots:', delivery_time_slots); // Log to verify the data

            // Initialize date picker
            $('#delivery_date').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0,
                onSelect: function(dateText) {
                    console.log('Selected Date:', dateText); // Log the selected date
                    // When the date is selected, update time slots
                    updateTimeSlots(dateText);
                }
            });

            function updateTimeSlots(selectedDate) {
                console.log('Updating time slots for selected date:', selectedDate); // Log the selected date in the function
                
                // Clear existing options
                var timeSelect = $('#delivery_time');
                timeSelect.empty();
                console.log('Cleared existing options in delivery time dropdown');

                // Add placeholder option
                timeSelect.append('<option value=\"\">' + 'Select a time' + '</option>');
                console.log('Added placeholder option');

                // Get the day of the week (0-6, where 0 = Sunday)
                var dayOfWeek = new Date(selectedDate).getDay();
                var daysOfWeek = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

                console.log('Selected Day:', daysOfWeek[dayOfWeek]);

                // Get the time slots for the selected day from the localized data
                var availableTimes = delivery_time_slots[daysOfWeek[dayOfWeek]] ? delivery_time_slots[daysOfWeek[dayOfWeek]].split(',') : [];

                console.log('Raw Available Times:', availableTimes);

                // Get the current time and check against the selected time slots
                var currentTime = new Date();
                var twoHoursLater = new Date(currentTime.getTime() + 2 * 60 * 60 * 1000); // 2 hours later

                // Populate the time slots dropdown
                availableTimes.forEach(function(time) {
                    console.log('Raw Time:', time);
                    var trimmedTime = time.trim(); // Ensure there are no leading or trailing spaces
                    console.log('Trimmed Time:', trimmedTime);

                    if (trimmedTime) {
                        // Create a Date object from the selected date and the current time slot
                        var dateParts = selectedDate.split('-'); // Split the date string
                        var timeParts = trimmedTime.split(' - '); // Split the time range
                        
                        // Parse the start time (timeParts[0])
                        var startTimeParts = timeParts[0].split(':');
                        var startHour = parseInt(startTimeParts[0]);
                        var startMinute = parseInt(startTimeParts[1].slice(0, 2));
                        var startAMPM = timeParts[0].slice(-2).toLowerCase(); // Get AM/PM

                        if (startAMPM === 'pm' && startHour < 12) startHour += 12;
                        if (startAMPM === 'am' && startHour === 12) startHour = 0;

                        var slotTime = new Date(dateParts[0], dateParts[1] - 1, dateParts[2], startHour, startMinute);

                        console.log('Slot Time:', slotTime);

                        // Only add the slot if it is at least 2 hours from the current time
                        if (slotTime > twoHoursLater) {
                            console.log('Adding Time Slot:', trimmedTime);
                            timeSelect.append('<option value=\"' + trimmedTime + '\">' + trimmedTime + '</option>');
                        } else {
                            console.log('Skipping Time Slot (too soon):', trimmedTime);
                        }
                    }
                });
            }
        });
    ");
}

// Enqueue custom CSS to style the delivery time field
add_action('wp_enqueue_scripts', 'wc_enqueue_custom_styles');

function wc_enqueue_custom_styles() {
    wp_add_inline_style( 'woocommerce-general', "
        #delivery_time {
            height: auto !important; /* Allow the height to adjust */
            padding: 10px !important; /* Add padding to make the dropdown look consistent */
            font-size: 16px !important; /* Adjust the font size */
            color: inherit;
            border: 1px solid !important; /* Same border as other select fields */
            border-radius: 4px !important; /* Rounded corners for consistency */
            background-color: #fff !important; /* White background for consistency */
            box-sizing: border-box !important; /* Ensure padding is accounted for in total width/height */
        }
    ");
}

// Hook to add the settings page in the WordPress admin menu
add_action('admin_menu', 'wc_delivery_time_slots_settings_page');
function wc_delivery_time_slots_settings_page() {
    add_options_page(
        'Delivery Time Slots',  // Title for the settings page
        'Delivery Time Slots',  // Menu item label
        'manage_options',       // Capability required to access the page
        'delivery-time-slots',  // Menu slug
        'wc_delivery_time_slots_page' // Callback function to render the page
    );
}

// Callback function to render the settings page
function wc_delivery_time_slots_page() {
    ?>
    <div class="wrap">
        <h2><?php echo esc_html( 'Delivery Time Slots Configuration' ); ?></h2>
        <form method="post" action="options.php">
            <?php
            // Output settings fields and sections
            settings_fields( 'wc_delivery_time_slots_group' );
            do_settings_sections( 'delivery-time-slots' );
            ?>
            <table class="form-table">
                <?php 
                // Days of the week
                $days_of_week = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                foreach($days_of_week as $day) {
                    $option_name = 'delivery_time_slot_' . strtolower($day);
                    $time_slots = get_option($option_name, '');
                    ?>
                    <tr valign="top">
                        <th scope="row"><?php echo esc_html( $day . ' Time Slots' ); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr( $option_name ); ?>" value="<?php echo esc_attr( $time_slots ); ?>" />
                            <p class="description"><?php echo esc_html( 'Enter time slots separated by commas, e.g., "10:00 AM - 12:00 PM, 1:00 PM - 3:00 PM"'); ?></p>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register the settings
add_action('admin_init', 'wc_register_delivery_time_slots_settings');
function wc_register_delivery_time_slots_settings() {
    $days_of_week = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    foreach($days_of_week as $day) {
        $option_name = 'delivery_time_slot_' . strtolower($day);
        register_setting('wc_delivery_time_slots_group', $option_name);
    }
}
