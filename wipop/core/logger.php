<?php

declare(strict_types=1);

namespace WipopWC\Core;

defined('ABSPATH') || exit;

class Logger
{
	/**
	 * @param array<string, mixed> $context
	 */
	public static function log(string $message, string $level = 'info', array $context = []): void
	{
		if (!function_exists('wc_get_logger')) {
			return;
		}

		$logger = wc_get_logger();
		$context = array_merge(['source' => 'wipop'], $context);
		$logger->log($level, $message, $context);
	}
}
