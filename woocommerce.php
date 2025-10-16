<?php
use Automattic\WooCommerce\Enums\OrderStatus;

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

add_filter('woocommerce_admin_order_preview_actions', 'initlab_shop_woocommerce_admin_order_preview_actions', 10, 2);

function initlab_shop_woocommerce_admin_order_preview_actions($actions, $order) {
	if ($order->has_status([OrderStatus::PROCESSING]) && $order->get_payment_method() === 'cod') {
		unset($actions['status']['actions']['complete']);

		$actions['status']['actions'] = [
			'pay-cash' => [
				'url' => wp_nonce_url(admin_url('admin-ajax.php?action=init_lab_shop_cash_register_payment&method=cash&order_id=' . $order->get_id()), 'init_lab_shop_cash_register_payment'),
				'name' => __('Pay in CASH', 'initlab-shop-addons'),
				'title' => __('Pay the order in cash', 'initlab-shop-addons'),
				'action' => 'pay-cash',
			],
			'pay-card' => [
				'url' => wp_nonce_url(admin_url('admin-ajax.php?action=init_lab_shop_cash_register_payment&method=card&order_id=' . $order->get_id()), 'init_lab_shop_cash_register_payment'),
				'name' => __('Pay by CARD', 'initlab-shop-addons'),
				'title' => __('Pay the order by card', 'initlab-shop-addons'),
				'action' => 'pay-card',
			],
		];
	}

	return $actions;
}

// TODO admin action init_lab_shop_cash_register_payment
