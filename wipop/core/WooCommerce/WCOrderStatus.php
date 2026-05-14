<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Mirror of WooCommerce order statuses so we can reference them as constants.
 * WooCommerce exposes the same constants via Automattic\WooCommerce\Utilities\OrderInternalStatus.
 */
final class WCOrderStatus
{
	/**
	 * The order is pending payment.
	 */
	public const PENDING = 'pending';

	/**
	 * The order is processing.
	 */
	public const PROCESSING = 'processing';

	/**
	 * The order is on hold.
	 */
	public const ON_HOLD = 'on-hold';

	/**
	 * The order is completed.
	 */
	public const COMPLETED = 'completed';

	/**
	 * The order is cancelled.
	 */
	public const CANCELLED = 'cancelled';

	/**
	 * The order is refunded.
	 */
	public const REFUNDED = 'refunded';

	/**
	 * The order is failed.
	 */
	public const FAILED = 'failed';
}
