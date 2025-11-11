<?php

declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($autoload)) {
	require_once $autoload;
}

if (!defined('WIPOP_PLUGIN_FILE')) {
	define('WIPOP_PLUGIN_FILE', __DIR__ . '/wipop.php');
}

if (!defined('WIPOP_PLUGIN_PATH')) {
	define('WIPOP_PLUGIN_PATH', __DIR__ . '/');
}

$wordpressStubs = __DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
if (is_readable($wordpressStubs)) {
	require_once $wordpressStubs;
}

$woocommerceStubs = __DIR__ . '/vendor/php-stubs/woocommerce-stubs/woocommerce-stubs.php';
if (is_readable($woocommerceStubs)) {
	require_once $woocommerceStubs;
}
