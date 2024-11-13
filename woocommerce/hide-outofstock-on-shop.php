<?php
/**
* Snippet Name:	Hide OutOfStock items on Shop page when sorting (popularity/price) applied.
*/
add_action('pre_option_woocommerce_hide_out_of_stock_items','hide_out_of_stock_on_shop');
function hide_out_of_stock_on_shop() {
    if( is_admin() ) { // do nothing in WordPress admin
		return $option;
	}
	if ( is_shop() ) { // add page where you want to hide
		if ( isset($_GET['orderby']) && $_GET['orderby'] == 'popularity') { // for popularity
			$option = 'yes';
			return $option;
		}
		if ( isset($_GET['orderby']) && $_GET['orderby'] == 'price') { // for price
			$option = 'yes';
			return $option;
		}
		// if ( isset($_GET['orderby']) && $_GET['orderby'] == 'date') { // for date/latest
		//	$option = 'yes';
		//	return $option;
		// }
	}
	return $option;
}
