<?php

declare(strict_types=1);

namespace Wipop\Core\WooCommerce;

use function __;
use function sprintf;

defined('ABSPATH') || exit;

final class StatusHelper
{
	public static function format(?string $statusValue, ?string $detail = null): string
	{
		$status = $statusValue ?? __('desconocido', 'wipop');

		$message = sprintf(__('Estado de Wipop: %s.', 'wipop'), $status);

		if (!empty($detail)) {
			$message .= ' ' . $detail;
		}

		return $message;
	}
}
