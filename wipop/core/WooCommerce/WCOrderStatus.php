<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce;

/**
 * Mirror of WooCommerce order statuses so we can reference them as constants.
 * WooCommerce exposes the same constants via Automattic\WooCommerce\Utilities\OrderInternalStatus.
 */
final class WCOrderStatus
{
	/**
	 * The order is pending payment.
	 */
	public const PENDING = 'wc-pending';

	/**
	 * The order is processing.
	 */
	public const PROCESSING = 'wc-processing';

	/**
	 * The order is on hold.
	 */
	public const ON_HOLD = 'wc-on-hold';

	/**
	 * The order is completed.
	 */
	public const COMPLETED = 'wc-completed';

	/**
	 * The order is cancelled.
	 */
	public const CANCELLED = 'wc-cancelled';

	/**
	 * The order is refunded.
	 */
	public const REFUNDED = 'wc-refunded';

	/**
	 * The order is failed.
	 */
	public const FAILED = 'wc-failed';
}
