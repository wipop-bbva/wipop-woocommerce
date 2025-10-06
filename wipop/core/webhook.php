<?php

declare(strict_types=1);

namespace Wipop\Core;

defined('ABSPATH') || exit;

/**
 * Webhook handler for Wipop payment gateways.
 * https://woocommercesite.com/?wc-api=wipop_bbva
 */
class Webhook
{
	public static function init(): void
	{
		add_action('woocommerce_api_wipop_bbva', [__CLASS__, 'handle']);
	}

	public static function handle(): void
	{
		$body = file_get_contents('php://input');
		if (function_exists('wc_get_logger')) {
			$logger = wc_get_logger();
			$logger->info('BBVA POST: ' . $body, ['source' => 'wipop_webhook']);
		}

		status_header(200);
		echo 'OK';
		exit;
	}
}
