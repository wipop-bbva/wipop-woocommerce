<?php

declare(strict_types=1);

namespace WipopWC\Tests\Core\WooCommerce;

use PHPUnit\Framework\TestCase;
use WC_Order;
use Wipop\Domain\Refund;
use Wipop\Domain\TransactionStatus;
use WipopWC\Core\WooCommerce\OrderMetaManager;

require_once dirname(__DIR__, 3) . '/core/WooCommerce/OrderMetaManager.php';

/**
 * @covers \WipopWC\Core\WooCommerce\OrderMetaManager
 *
 * @internal
 */
final class OrderMetaManagerRefundTest extends TestCase
{
	public function testSyncRefundStoresRefundTransactionLookupWithoutGatewayOrderLookup(): void
	{
		$order = $this->orderDouble();
		$refund = new Refund();
		$refund->id = 'trRefundApi';
		$refund->orderId = '2947refund';
		$refund->status = TransactionStatus::COMPLETED;

		$refundTransactionId = OrderMetaManager::syncRefund($order, $refund);

		$this->assertSame('trRefundApi', $refundTransactionId);
		$this->assertContains('trRefundApi', $order->meta[OrderMetaManager::META_TRANSACTION_ID_LOOKUP] ?? []);
		$this->assertArrayNotHasKey(OrderMetaManager::META_GATEWAY_ORDER_ID_LOOKUP, $order->meta);
		$this->assertSame('trRefundApi', $order->get_meta(OrderMetaManager::META_REFUND_TRANSACTION_ID));
		$this->assertSame('2947refund', $order->get_meta(OrderMetaManager::META_REFUND_GATEWAY_ORDER_ID));
		$this->assertSame(TransactionStatus::COMPLETED->value, $order->get_meta(OrderMetaManager::META_REFUND_STATUS));
		$this->assertTrue($order->saved);
	}

	/**
	 * @return object{meta: array<string, list<mixed>>, saved: bool}&WC_Order
	 */
	private function orderDouble(): WC_Order
	{
		return new class extends WC_Order {
			/**
			 * @var array<string, list<mixed>>
			 */
			public array $meta = [];
			public bool $saved = false;

			public function get_meta($key = '', $single = true, $context = 'view')
			{
				if ($key === '') {
					return $this->meta;
				}

				$values = $this->meta[(string) $key] ?? [];

				if (!$single) {
					return $values;
				}

				return $values[0] ?? '';
			}

			public function add_meta_data($key, $value, $unique = false)
			{
				if ($unique) {
					$this->meta[(string) $key] = [$value];

					return;
				}

				$this->meta[(string) $key][] = $value;
			}

			public function update_meta_data($key, $value, $meta_id = 0)
			{
				$this->meta[(string) $key] = [$value];
			}

			public function save()
			{
				$this->saved = true;

				return 123;
			}
		};
	}
}
