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

add_action('admin_enqueue_scripts', 'init_lab_shop_admin_script');

function init_lab_shop_admin_script($hook_suffix) {
	if (isset($_GET['action']) && $_GET['action'] === 'init_lab_shop_cash_register_payment') {
	        wp_enqueue_script(
	            'init-lab-shop-payment',
	            plugin_dir_url(__FILE__) . 'assets/js/payment.js',
	            ['jquery'], // dependencies
	            '1.0',
	            true // load in footer
	        );

	        if (!isset($_GET['method'], $_GET['order_id'])) {
			wp_die('Missing parameters');
		}

		$method = sanitize_text_field(wp_unslash($_GET['method']));
		$order_id = absint(wp_unslash($_GET['order_id']));

		if (!check_admin_referer('init_lab_shop_cash_register_payment_' . $method . '_' . $order_id)) {
			wp_die('Wrong nonce');
		}

		$order = wc_get_order($order_id);

		if (!$order->has_status([OrderStatus::PROCESSING])) {
			wp_redirect(admin_url('admin.php?page=wc-orders'));
			exit;
		}

		$order->update_status('completed', '', true);

		wp_add_inline_script('init-lab-shop-payment', 'window.initLabShopOrder = ' . json_encode([
			'id' => $order->get_id(),
			'payment_method' => $method,
			'total' => $order->get_total(),
			'items' => array_filter(array_map(function($item) {
				$product = $item->get_product();
				$name = $product->get_name();

				if (str_contains($name, 'OpenFest') || str_contains($name, 'Mystery Box')) {
					$department = 1; // OpenFest
				}
				elseif (str_contains($name, 'init Lab')) {
					$department = 2; // init Lab
				}
				elseif (str_contains($name, 'VarnaLab')) {
					$department = 3; // VarnaLab
				}
				else {
					return false;
				}

				return [
					'department' => $department,
					'name' => $name,
					'price' => $product->get_price(),
					'sku' => $product->get_sku(),
					'quantity' => $item->get_quantity(),
				];
			}, array_values($order->get_items()))),
			'success_url' => wp_nonce_url(admin_url(
				'admin.php?page=wc-orders'
			), 'woocommerce-mark-order-status'),
		]), 'before');
	}

    // Load WooCommerce admin pages
    $screen = get_current_screen();

    // Make sure we have a valid screen object
    if (!$screen || strpos($screen->id, 'woocommerce') === false) {
        return;
    }

    // Example 1: Only run on the WooCommerce Orders page
    if ($screen->id === 'woocommerce_page_wc-orders') {
        wp_enqueue_script(
            'init-lab-shop-admin',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            ['jquery'], // dependencies
            '1.0',
            true // load in footer
        );

        // Optional: pass PHP data to JS
        wp_localize_script('init-lab-shop-admin', 'initLabShopVars', [
            'txtConfirmPaymentCash' => __('Please confirm CASH payment', 'initlab-shop-addons'),
        ]);
    }
}

add_filter('woocommerce_admin_order_preview_actions', 'initlab_shop_woocommerce_admin_order_preview_actions', 10, 2);

function initlab_shop_woocommerce_admin_order_preview_actions($actions, $order) {
	if ($order->has_status([OrderStatus::PROCESSING])) {
		unset($actions['status']['actions']['complete']);

		$actions['status']['actions'] = [
			'pay-cash' => [
				'url' => wp_nonce_url(
					admin_url('admin.php?action=init_lab_shop_cash_register_payment&method=cash&order_id=' . $order->get_id()),
					'init_lab_shop_cash_register_payment_cash_' . $order->get_id()
				),
				'name' => __('Pay in CASH', 'initlab-shop-addons'),
				'title' => __('Pay the order in cash', 'initlab-shop-addons'),
				'action' => 'pay-cash',
			],
			'pay-card' => [
				'url' => wp_nonce_url(
					admin_url('admin.php?action=init_lab_shop_cash_register_payment&method=card&order_id=' . $order->get_id()),
					'init_lab_shop_cash_register_payment_card_' . $order->get_id()
				),
				'name' => __('Pay by CARD', 'initlab-shop-addons'),
				'title' => __('Pay the order by card', 'initlab-shop-addons'),
				'action' => 'pay-card',
			],
		];
	}

	return $actions;
}

add_action('admin_init', 'init_lab_shop_cash_register_page_action');

function init_lab_shop_cash_register_page_action() {
    if (isset($_GET['action']) && $_GET['action'] === 'init_lab_shop_cash_register_payment') {

        // Permission check
	if (!current_user_can('edit_shop_orders')) {
            wp_die('Access denied');
        }

        // Load admin header
        require_once ABSPATH . 'wp-admin/admin-header.php';

        // Page content
        echo '<div class="wrap">';
        echo '<h1>', __('Cash register payment', 'initlab-shop-addons'), '</h1>';
        echo '<p>', __('Connecting to cash register...'), '</p>';
        echo '<p id="conn-err" hidden>', __('Error connecting to cash register'), ': </p>';
        echo '<p id="conn-success" hidden>', __('Connected to cash register'), '</p>';
        echo '<p id="print-start" hidden>', __('Receipt sent to cash register, please wait...'), '</p>';
        echo '<p id="print-err" hidden>', __('Error printing'), ': </p><p id="print-again" hidden><button id="try-again">Print again</button></p>';
        echo '<p id="print-success" hidden>', __('Successful printing'), '</p>';
        echo '</div>';

        // Load admin footer
        require_once ABSPATH . 'wp-admin/admin-footer.php';

        exit;
    }
}
