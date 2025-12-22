<?php
/**
 * Plugin Name: WC Custom RFQ Form
 * Description: Multi-product RFQ form with search by name/SKU, product preview, editable rows, front-end validation, and WooCommerce order creation.
 * Version: 0.0.1
 */

if (!defined('ABSPATH')) exit;

/**
 * Ensure WooCommerce
 */
add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) return;
});

/**
 * Register RFQ order status
 */
add_action('init', function () {
    register_post_status('wc-rfq', [
        'label' => 'RFQ Submitted',
        'public' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'exclude_from_search' => false,
        'label_count' => _n_noop('RFQ Submitted <span class="count">(%s)</span>', 'RFQ Submitted <span class="count">(%s)</span>'),
    ]);
});

add_filter('wc_order_statuses', function ($statuses) {
    $new_statuses = [];
    foreach ($statuses as $key => $label) {
        $new_statuses[$key] = $label;
        if ($key === 'wc-pending') {
            $new_statuses['wc-rfq'] = 'RFQ Submitted';
        }
    }
    return $new_statuses;
});

/**
 * Shortcode to render RFQ form
 */
add_shortcode('wc_custom_rfq', 'wc_custom_rfq_form');
function wc_custom_rfq_form() {
    if (!class_exists('WooCommerce')) return 'WooCommerce is required.';

    $messages = wc_custom_rfq_handle_submit();

    // Prefill logged-in user
    $user = wp_get_current_user();
    $prefill = [
        'full_name' => trim($user->first_name . ' ' . $user->last_name),
        'email' => $user->user_email,
    ];

    // Fetch all published products
    $products = wc_get_products(['limit' => -1, 'status' => 'publish']);

    // Use posted products or default first row
    $posted_products = $_POST['products'] ?? [['name'=>'','sku'=>'','qty'=>1]];

    ob_start();
    ?>
    <form method="post" class="woocommerce-form" id="rfq-form" novalidate>

        <?php
        // Display messages
        foreach ($messages as $m) {
            $class = $m['type'] === 'error' ? 'woocommerce-error' : 'woocommerce-message';
            echo '<div class="' . esc_attr($class) . '">' . esc_html($m['text']) . '</div>';
        }

        // Customer fields
        $fields = [
            'full_name' => ['type' => 'text', 'label' => 'Full Name', 'required' => true, 'class' => ['form-row-wide']],
            'email' => ['type' => 'email', 'label' => 'Email', 'required' => true, 'class' => ['form-row-wide']],
            'subject' => ['type' => 'text', 'label' => 'Subject', 'required' => true, 'class' => ['form-row-wide']],
            'message' => ['type' => 'textarea', 'label' => 'Message', 'required' => true, 'class' => ['form-row-wide']],
        ];

        foreach ($fields as $key => $field) {
            woocommerce_form_field($key, $field, $_POST[$key] ?? $prefill[$key] ?? '');
        }
        ?>

        <h3>Products</h3>
        <table id="rfq-products-table" style="width:100%">
            <thead>
                <tr>
                    <th>Product name</th>
                    <th>Qty</th>
                    <th>Preview</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($posted_products as $i => $p):
                $name = esc_attr($p['name'] ?? '');
                $sku = esc_attr($p['sku'] ?? '');
                $qty = esc_attr($p['qty'] ?? 1);
                $pid = $sku ? wc_get_product_id_by_sku($sku) : 0;
                $img_url = $pid ? wp_get_attachment_image_url(get_post_thumbnail_id($pid),'thumbnail') : wc_placeholder_img_src('thumbnail');
                $price_html = $pid ? wc_price(wc_get_product($pid)->get_price()) : '';
            ?>
            <tr class="rfq-row">
                <td>
                    <input type="text" list="rfq_product_list" class="rfq-product-input" value="<?php echo $name.($sku ? ' | '.$sku : ''); ?>" required>
                    <input type="hidden" name="products[<?php echo $i; ?>][sku]" class="rfq-product-sku" value="<?php echo $sku; ?>">
                    <input type="hidden" name="products[<?php echo $i; ?>][name]" class="rfq-product-name" value="<?php echo $name; ?>">
                </td>
                <td>
                    <input type="number" name="products[<?php echo $i; ?>][qty]" value="<?php echo $qty; ?>" min="1" required>
                </td>
                <td class="rfq-preview">
                    <div class="d-flex gap-2 align-items-center">
                        <div class="flex-shrink-0" style="width: 90px; height: 90px;">
                            <div class="bg-light rounded-3 overflow-hidden w-100 h-100">
                                <img src="<?php echo esc_url($img_url); ?>" width="100%" height="100%" style="object-fit:cover;">
                            </div>
                        </div>
                        <div>
                            <div class="sku"><?php echo $sku ? 'SKU: '.$sku : ''; ?></div>
                            <div class="price"><?php echo $price_html; ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <button type="button wp-element-button" class="remove-row">✕</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="button wp-element-button" id="add-row">Add Product</button>

        <datalist id="rfq_product_list">
            <?php foreach ($products as $p):
                $label = $p->get_name();
                if ($p->get_sku()) $label .= ' | ' . $p->get_sku();
                $img = wp_get_attachment_image_url($p->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail');
                $price_html = wc_price($p->get_price());
            ?>
            <option value="<?php echo esc_attr($label); ?>"
                    data-img="<?php echo esc_url($img); ?>"
                    data-price-html="<?php echo esc_attr($price_html); ?>">
            </option>
            <?php endforeach; ?>
        </datalist>

        <?php wp_nonce_field('wc_custom_rfq', 'wc_custom_rfq_nonce'); ?>
        <button type="submit" class="button wp-element-button">Submit RFQ</button>
    </form>

    <script>
    (function($){
        let idx = <?php echo count($posted_products); ?>;

        function updatePreview(row){
            let val = row.find('.rfq-product-input').val();
            let parts = val.split('|');
            let name = parts[0].trim();
            let sku = parts[1] ? parts[1].trim() : '';

            row.find('.rfq-product-name').val(name);
            row.find('.rfq-product-sku').val(sku);

            // Match by SKU first, fallback to name
            let option = $('#rfq_product_list option').filter(function(){
                let optParts = this.value.split('|');
                let optName = optParts[0].trim();
                let optSku = optParts[1] ? optParts[1].trim() : '';
                return sku ? optSku === sku : optName === name;
            }).first();

            if(option.length){
                let img = option.data('img') || '<?php echo wc_placeholder_img_src(); ?>';
                let priceHtml = option.data('price-html');
                row.find('.rfq-preview img').attr('src', img);
                row.find('.rfq-preview .sku').text(sku ? 'SKU: '+sku : '');
                row.find('.rfq-preview .price').html(priceHtml);
            } else {
                row.find('.rfq-preview img').attr('src', '<?php echo wc_placeholder_img_src(); ?>');
                row.find('.rfq-preview .sku,.rfq-preview .price').text('');
            }
        }

        function bindRow(row){
            row.find('.rfq-product-input').on('input change blur', function(){
                updatePreview(row);
            });
        }

        // Initialize existing rows
        $('.rfq-row').each(function(){
            bindRow($(this));
            updatePreview($(this));
        });

        $('#add-row').click(function(){
            let newRow = $('.rfq-row:first').clone();
            newRow.find('.rfq-product-input').val('');
            newRow.find('input[name$="[qty]"]').val(1);
            newRow.find('img').attr('src','<?php echo wc_placeholder_img_src(); ?>');
            newRow.find('.sku,.price').text('');
            newRow.find('.rfq-product-name').attr('name','products['+idx+'][name]');
            newRow.find('.rfq-product-sku').attr('name','products['+idx+'][sku]');
            newRow.find('input[name$="[qty]"]').attr('name','products['+idx+'][qty]');
            bindRow(newRow);
            $('#rfq-products-table tbody').append(newRow);
            idx++;
        });

        $(document).on('click','.remove-row',function(){
            if($('#rfq-products-table tbody tr').length>1) $(this).closest('tr').remove();
        });

        // Only clear form on successful submission
        $(document).ready(function(){
            if($('.woocommerce-message').length && $('.woocommerce-message').text().includes('RFQ submitted successfully')){
                $('#rfq-form')[0].reset();
                $('.rfq-preview img').attr('src','<?php echo wc_placeholder_img_src(); ?>');
                $('.rfq-preview .sku,.rfq-preview .price').text('');
            }
        });

    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Handle form submission
 */
function wc_custom_rfq_handle_submit(){
    if(empty($_POST['wc_custom_rfq_nonce']) || !wp_verify_nonce($_POST['wc_custom_rfq_nonce'],'wc_custom_rfq')) return [];

    $msgs = [];
    $errors = [];

    $name = sanitize_text_field($_POST['full_name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $subject = sanitize_text_field($_POST['subject'] ?? '');
    $message = sanitize_textarea_field($_POST['message'] ?? '');

    if(!$name) $errors[] = 'Full Name is required.';
    if(!$email || !is_email($email)) $errors[] = 'Valid Email is required.';
    if(!$subject) $errors[] = 'Subject is required.';
    if(!$message) $errors[] = 'Message is required.';

    $products = $_POST['products'] ?? [];
    if(!$products) $errors[] = 'At least one product is required.';

    $product_data = [];
    foreach($products as $i=>$p){
        $prod_name = sanitize_text_field($p['name'] ?? '');
        $prod_sku = sanitize_text_field($p['sku'] ?? '');
        $qty = intval($p['qty'] ?? 0);

        if(!$prod_name || !$qty) $errors[] = "Product and quantity required for row ".($i+1);

        $product_data[] = ['name'=>$prod_name,'sku'=>$prod_sku,'qty'=>$qty];
    }

    if($errors){
        foreach($errors as $e) $msgs[]=['type'=>'error','text'=>$e];
        return $msgs;
    }

    // Map customer
    $user = get_user_by('email',$email);
    $customer_id = $user ? $user->ID : 0;

    $order = wc_create_order(['customer_id'=>$customer_id]);
    $order->set_address(['first_name'=>$name,'email'=>$email],'billing');

    foreach($product_data as $p){
        $pid = $p['sku'] ? wc_get_product_id_by_sku($p['sku']) : 0;
        if($pid){
            $order->add_product(wc_get_product($pid), $p['qty']);
        } else {
            $item = new WC_Order_Item_Product();
            $item->set_name($p['name']);
            $item->set_quantity($p['qty']);
            $order->add_item($item);
        }
    }

    $order->add_order_note("RFQ Subject: $subject\nMessage: $message");
    $order->update_status('wc-rfq','RFQ submitted via form');
    $order->calculate_totals();
    $order->save();

    $msgs[]=['type'=>'success','text'=>'RFQ submitted successfully!'];

    return $msgs;
}
