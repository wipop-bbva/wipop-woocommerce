<?php

declare(strict_types=1);

$wipop_autoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($wipop_autoload)) {
	require_once $wipop_autoload;
}

if (!defined('WIPOP_PLUGIN_FILE')) {
	define('WIPOP_PLUGIN_FILE', __DIR__ . '/wipop.php');
}

if (!defined('WIPOP_PLUGIN_PATH')) {
	define('WIPOP_PLUGIN_PATH', __DIR__ . '/');
}

$wipop_wordpress_stubs = __DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
if (is_readable($wipop_wordpress_stubs)) {
	require_once $wipop_wordpress_stubs;
}

$wipop_woocommerce_stubs = __DIR__ . '/vendor/php-stubs/woocommerce-stubs/woocommerce-stubs.php';
if (is_readable($wipop_woocommerce_stubs)) {
	require_once $wipop_woocommerce_stubs;
}
