<?php

declare(strict_types=1);

namespace Wipop\Core\WooCommerce;

/**
 * Mirror of WooCommerce order statuses so we can reference them as constants.
 * WooCommerce exposes the same constants via Automattic\WooCommerce\Utilities\OrderInternalStatus.
 */
final class WCOrderStatus
{
	public const PENDING = 'wc-pending';
	public const ON_HOLD = 'wc-on-hold';
	public const FAILED = 'wc-failed';

	private function __construct()
	{
	}
}
