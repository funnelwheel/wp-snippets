<?php
/**
* Snippet Name:	Fetch Product Sales Count
* Pass the product ID 
* Use like this sales_count(123); // will return the sales count
*/
function sales_count($product_id) {
    $product = wc_get_product( $product_id );
    $sales_count = $product->get_total_sales();
    
    // echo 'This product has been sold ' . $sales_count . ' times.';
    return $sales_count;
}
