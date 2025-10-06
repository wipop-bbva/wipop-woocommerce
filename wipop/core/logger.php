<?php

declare(strict_types=1);

namespace Wipop\Core;

defined('ABSPATH') || exit;

class Logger
{
	public static function log(string $message, string $level = 'info'): void
	{
		if (function_exists('wc_get_logger')) {
			$logger = wc_get_logger();
			$logger->log($level, $message, ['source' => 'wipop']);
		}
	}
}
