<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WipopWC\Core\WooCommerce\OrderMetaManager;

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__, 3) . '/');
}

require_once dirname(__DIR__, 3) . '/core/WooCommerce/OrderMetaManager.php';

/**
 * @covers \WipopWC\Core\WooCommerce\OrderMetaManager
 *
 * @internal
 */
final class OrderMetaManagerTest extends TestCase
{
	public function testLookupHelpersStoreTrimmedRepeatableMeta(): void
	{
		$order = $this->createMock(WC_Order::class);
		$calls = [];

		$order->expects($this->exactly(2))
			->method('add_meta_data')
			->willReturnCallback(static function (string $key, mixed $value, bool $unique) use (&$calls): void {
				$calls[] = [$key, $value, $unique];
			})
		;

		OrderMetaManager::addGatewayOrderIdLookup($order, ' order-1 ');
		OrderMetaManager::addTransactionIdLookup($order, ' tx-1 ');

		$this->assertSame([
			[OrderMetaManager::META_GATEWAY_ORDER_ID_LOOKUP, 'order-1', false],
			[OrderMetaManager::META_TRANSACTION_ID_LOOKUP, 'tx-1', false],
		], $calls);
	}

	public function testAddTransactionIdLookupSkipsBlankValues(): void
	{
		$order = $this->createMock(WC_Order::class);

		$order->expects($this->never())->method('get_meta');
		$order->expects($this->never())->method('add_meta_data');

		OrderMetaManager::addTransactionIdLookup($order, ' ');
	}
}
