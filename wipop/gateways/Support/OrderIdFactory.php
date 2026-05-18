<?php

declare(strict_types=1);

namespace WipopWC\Gateways\Support;

use WC_Order;
use Wipop\Domain\Value\OrderId;
use WipopWC\Core\WooCommerce\OrderMetaManager;

use function abs;
use function hash;
use function max;
use function random_int;
use function sprintf;
use function str_pad;
use function strlen;
use function strtoupper;
use function substr;
use function uniqid;

defined('ABSPATH') || exit;

/**
 * Helper responsible for generating Wipop-compliant order IDs.
 */
final class OrderIdFactory
{
	private const META_KEY = '_wipop_order_id';
	private const RANDOM_ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	private const RANDOM_SUFFIX_LENGTH = 8;

	public static function fromOrder(WC_Order $order): OrderId
	{
		$orderId = self::buildAttemptOrderId($order);

		$order->update_meta_data(self::META_KEY, $orderId);
		OrderMetaManager::addGatewayOrderIdLookup($order, $orderId);
		$order->save_meta_data();

		return OrderId::fromString($orderId);
	}

	public static function forRecurring(WC_Order $order, string $period, int $sequence): OrderId
	{
		$orderKey = $order->get_order_key();

		$seed = sprintf(
			'%s|reccurrent|%s|%d',
			$orderKey,
			strtoupper($period),
			max(1, $sequence)
		);

		return OrderId::fromString(self::buildOrderId($order, $seed));
	}

	private static function buildAttemptOrderId(WC_Order $order): string
	{
		return self::buildPrefix($order) . self::randomSuffix();
	}

	private static function buildOrderId(WC_Order $order, ?string $seed = null): string
	{
		$prefix = self::buildPrefix($order);

		$orderKey = $seed ?? $order->get_order_key();
		if (strlen($orderKey) < 1) {
			$orderKey = uniqid('wipop', true);
		}

		// Wipop order suffix is 8 alpahnumeric characters
		$hash = strtoupper(hash('crc32b', $prefix . '|' . $orderKey));
		$suffix = substr($hash, 0, 8);

		return $prefix . $suffix;
	}

	private static function buildPrefix(WC_Order $order): string
	{
		// Wipop order prefix is 4 digits.
		$numericId = abs((int) $order->get_id());

		return str_pad((string) ($numericId % 10000), 4, '0', STR_PAD_LEFT);
	}

	private static function randomSuffix(): string
	{
		$suffix = '';
		$maxIndex = strlen(self::RANDOM_ALPHABET) - 1;

		for ($index = 0; $index < self::RANDOM_SUFFIX_LENGTH; ++$index) {
			$suffix .= self::RANDOM_ALPHABET[random_int(0, $maxIndex)];
		}

		return $suffix;
	}
}
