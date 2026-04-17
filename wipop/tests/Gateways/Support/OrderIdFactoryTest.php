<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WipopWC\Core\WooCommerce\OrderMetaManager;
use WipopWC\Gateways\Support\OrderIdFactory;

require_once dirname(__DIR__, 3) . '/gateways/Support/OrderIdFactory.php';

/**
 * @covers \WipopWC\Gateways\Support\OrderIdFactory
 *
 * @internal
 */
final class OrderIdFactoryTest extends TestCase
{
	public function testGeneratesNewOrderIdForEachAttemptAndStoresLookup(): void
	{
		$order = $this->createMock(WC_Order::class);
		$lookup = [];
		$latestOrderId = null;

		$order->method('get_id')->willReturn(12345);

			$order->method('update_meta_data')
				->willReturnCallback(static function (string $key, mixed $value) use (&$latestOrderId): void {
					if ($key === '_wipop_order_id') {
						$latestOrderId = $value;
					}
				})
			;

			$order->method('add_meta_data')
				->willReturnCallback(static function (string $key, mixed $value) use (&$lookup): void {
					if ($key === OrderMetaManager::META_GATEWAY_ORDER_ID_LOOKUP) {
						$lookup[] = $value;
					}
				})
			;

		$order->expects($this->exactly(2))->method('save_meta_data');

		$first = OrderIdFactory::fromOrder($order)->value();
		$second = OrderIdFactory::fromOrder($order)->value();

		$this->assertMatchesRegularExpression('/^2345[0-9A-Z]{8}$/', $first);
		$this->assertMatchesRegularExpression('/^2345[0-9A-Z]{8}$/', $second);
		$this->assertNotSame($first, $second);
		$this->assertSame($second, $latestOrderId);
		$this->assertSame([$first, $second], $lookup);
	}

	public function testGeneratesDeterministicRecurringOrderId(): void
	{
		$orderKey = 'wc_order_XYZ123';
			$order = $this->createMock(WC_Order::class);
			$order->expects($this->once())
				->method('get_id')
				->willReturn(12345)
			;
			$order->expects($this->once())
				->method('get_order_key')
				->willReturn($orderKey)
			;

		$period = 'monthly';
		$sequence = 3;
		$seed = sprintf('%s|reccurrent|%s|%d', $orderKey, strtoupper($period), $sequence);
		$expectedPrefix = str_pad((string) (12345 % 10000), 4, '0', STR_PAD_LEFT);
		$expectedSuffix = substr(strtoupper(hash('crc32b', $expectedPrefix . '|' . $seed)), 0, 8);
		$expectedOrderId = $expectedPrefix . $expectedSuffix;

		$order->expects($this->never())->method('update_meta_data');
		$order->expects($this->never())->method('save_meta_data');

		$result = OrderIdFactory::forRecurring($order, $period, $sequence);

		$this->assertSame($expectedOrderId, $result->value());
	}
}
