<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_readable($autoload)) {
	require_once $autoload;
}

$wordpressStubs = __DIR__ . '/../vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
if (is_readable($wordpressStubs)) {
	require_once $wordpressStubs;
}

$woocommerceStubs = __DIR__ . '/../vendor/php-stubs/woocommerce-stubs/woocommerce-stubs.php';
if (is_readable($woocommerceStubs)) {
	require_once $woocommerceStubs;
}
