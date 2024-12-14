/**
 * Filter to add extra meta data the post title of Rank Math Breadcrumbs.
 */
add_filter( 'rank_math/frontend/breadcrumb/items', function( $crumbs, $class ) {
	if ( is_singular() ) {
		$title = get_the_title();
		$post_id = get_the_ID();
		$item_size = '';
		// Fetch custom meta field value (e.g., 'item_size')
   	 	$item_size = get_post_meta( $post_id, 'item_size', true );
    	$RM_truncate_breadcrumb_title = $title . " " . $item_size;
    	array_splice($crumbs, count($crumbs) - 1, 1);   
    	$crumbs[][0] = $RM_truncate_breadcrumb_title; 
	}
    return $crumbs;
}, 10, 2);
