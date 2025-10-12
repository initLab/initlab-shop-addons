<?php
function initlab_shop_load_plugin_textdomain() {
    load_plugin_textdomain('initlab-shop-addons', false, basename(__DIR__) . '/languages');
}

add_action('plugins_loaded', 'initlab_shop_load_plugin_textdomain');
