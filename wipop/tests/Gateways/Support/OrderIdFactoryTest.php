<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wipop\Gateways\Support\OrderIdFactory;

require_once dirname(__DIR__, 3) . '/gateways/Support/OrderIdFactory.php';

/**
 * @covers \Wipop\Gateways\Support\OrderIdFactory
 *
 * @internal
 */
final class OrderIdFactoryTest extends TestCase
{
	public function testReturnsStoredOrderId(): void
	{
		$order = $this->createMock(WC_Order::class);
		$order->expects($this->once())
			->method('get_meta')
			->with('_wipop_order_id', true)
			->willReturn('1234ABCDEFGH')
		;
		$order->expects($this->never())->method('update_meta_data');
		$order->expects($this->never())->method('save_meta_data');

		$result = OrderIdFactory::fromOrder($order);

		$this->assertSame('1234ABCDEFGH', $result->value());
	}

	public function testGeneratesDeterministicOrderId(): void
	{
		$orderKey = 'wc_order_XYZ123';
		$order = $this->createMock(WC_Order::class);
		$order->expects($this->once())
			->method('get_meta')
			->with('_wipop_order_id', true)
			->willReturn('')
		;
		$order->expects($this->once())
			->method('get_id')
			->willReturn(12345)
		;
		$order->expects($this->once())
			->method('get_order_key')
			->willReturn($orderKey)
		;

		$expectedPrefix = str_pad((string) (12345 % 10000), 4, '0', STR_PAD_LEFT);
		$expectedSuffix = substr(strtoupper(hash('crc32b', $expectedPrefix . '|' . $orderKey)), 0, 8);
		$expectedOrderId = $expectedPrefix . $expectedSuffix;

		$order->expects($this->once())
			->method('update_meta_data')
			->with('_wipop_order_id', $expectedOrderId)
		;
		$order->expects($this->once())->method('save_meta_data');

		$result = OrderIdFactory::fromOrder($order);

		$this->assertSame($expectedOrderId, $result->value());
	}
}
