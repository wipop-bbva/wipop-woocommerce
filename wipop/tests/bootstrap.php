<?php

declare(strict_types=1);

namespace WipopWC\Core {
	/*
	 * WordPress stubs intentionally have empty function bodies. These namespaced
	 * test shims let WebhookAuth exercise header normalization while production
	 * continues to resolve the unqualified calls to WordPress globals.
	 */
	function wp_unslash(mixed $value): mixed
	{
		if (is_array($value)) {
			return array_map(__FUNCTION__, $value);
		}

		return is_string($value) ? stripslashes($value) : $value;
	}

	function sanitize_text_field(mixed $value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		return trim(strip_tags((string) $value));
	}
}

namespace {
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
}
