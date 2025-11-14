<?php

declare(strict_types=1);

namespace WipopWC\Gateways\Support;

use InvalidArgumentException;
use WC_Order;
use Wipop\Utils\OrderId;

use function abs;
use function hash;
use function is_string;
use function str_pad;
use function strtoupper;
use function substr;
use function uniqid;

/**
 * Helper responsible for generating Wipop-compliant order IDs.
 */
final class OrderIdFactory
{
	private const META_KEY = '_wipop_order_id';

	public static function fromOrder(WC_Order $order): OrderId
	{
		$stored = $order->get_meta(self::META_KEY, true);

		if (is_string($stored) && $stored !== '') {
			try {
				return OrderId::fromString($stored);
			} catch (InvalidArgumentException $exception) {
				// Ignore invalid persisted value and build a new one.
			}
		}

		$orderId = self::buildOrderId($order);

		$order->update_meta_data(self::META_KEY, $orderId);
		$order->save_meta_data();

		return OrderId::fromString($orderId);
	}

	private static function buildOrderId(WC_Order $order): string
	{
		// Wipop order prefix is 4 digits
		$numericId = abs((int) $order->get_id());
		$prefix = str_pad((string) ($numericId % 10000), 4, '0', STR_PAD_LEFT);

		$orderKey = $order->get_order_key();
		if (strlen($orderKey) < 1) {
			$orderKey = uniqid('wipop', true);
		}

		// Wipop order suffix is 8 alpahnumeric characters
		$hash = strtoupper(hash('crc32b', $prefix . '|' . $orderKey));
		$suffix = substr($hash, 0, 8);

		return $prefix . $suffix;
	}
}
