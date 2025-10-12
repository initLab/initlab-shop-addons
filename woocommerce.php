<?php
/**
 * Change number of products that are displayed per page (shop page)
 */
add_filter('loop_shop_per_page', 'initlab_shop_loop_shop_per_page', 20);

function initlab_shop_loop_shop_per_page($cols) {
	// $cols contains the current number of products per page based on the value stored on Options -> Reading
	// Return the number of products you wanna show per page.
	$cols = 50;
	return $cols;
}

/**
 * Disable backorder emails (we're fully aware the products are on backorder)
 */
add_action('woocommerce_email', 'initlab_shop_disable_product_on_backorder_email');

function initlab_shop_disable_product_on_backorder_email($email_class) {
	remove_action('woocommerce_product_on_backorder_notification', array($email_class, 'backorder'));
}

/**
 * Show the products in random order
 */
add_filter('woocommerce_default_catalog_orderby', 'initlab_shop_woocommerce_default_catalog_orderby');

function initlab_shop_woocommerce_default_catalog_orderby($default_orderby) {
	return 'rand';
}
