<?php
/**
* Snippet Name:	Fetch Product Sales Count
* Don't forget to add pass the product id
*/
function sales_count($product_id) {
    // $product_id = 123; // Pass that id
    $product = wc_get_product( $product_id );
    $sales_count = $product->get_total_sales();
    
    echo 'This product has been sold ' . $sales_count . ' times.';
}
