<?php
/**
* Snippet Name:	Remove Downloads Tab @ My Account Page
*/ 
add_filter( 'woocommerce_account_menu_items', 'remove_downloads_tab_my_account', 9999 );
 
function remove_downloads_tab_my_account( $items ) {
    $downloads = ! empty( WC()->customer ) ? WC()->customer->get_downloadable_products() : false;
    $has_downloads = (bool) $downloads;
    if ( ! $has_downloads ) unset( $items['downloads'] );
    return $items;
}
