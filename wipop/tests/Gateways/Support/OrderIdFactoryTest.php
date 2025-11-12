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
		$order = new WC_Order();
		$order->update_meta_data('_wipop_order_id', '1234ABCDEFGH');

		$result = OrderIdFactory::fromOrder($order);

		$this->assertSame('1234ABCDEFGH', $result->value());
		$this->assertSame('1234ABCDEFGH', $order->get_meta('_wipop_order_id', true));
	}

	public function testGeneratesDeterministicOrderId(): void
	{
		$order = new WC_Order();
		$order->set_id(12345);
		$orderKey = 'wc_order_XYZ123';
		$order->set_order_key($orderKey);

		$expectedPrefix = str_pad((string) (12345 % 10000), 4, '0', STR_PAD_LEFT);
		$expectedSuffix = substr(strtoupper(hash('crc32b', $expectedPrefix . '|' . $orderKey)), 0, 8);
		$expectedOrderId = $expectedPrefix . $expectedSuffix;

		$result = OrderIdFactory::fromOrder($order);

		$this->assertSame($expectedOrderId, $result->value());
		$this->assertSame($expectedOrderId, $order->get_meta('_wipop_order_id', true));
	}
}
