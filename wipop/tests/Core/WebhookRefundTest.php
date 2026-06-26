<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;
use WipopWC\Core\Webhook;
use WipopWC\Core\WooCommerce\OrderMetaManager;

require_once dirname(__DIR__, 2) . '/core/webhook.php';

/**
 * @covers \WipopWC\Core\Webhook
 *
 * @internal
 */
final class WebhookRefundTest extends TestCase
{
	public function testRefundWebhookLookupUsesOriginalChargeFromPayload(): void
	{
		$transaction = $this->refundTransaction();
		$payload = [
			'charge' => [
				'id' => 'trOriginalCharge',
				'order_id' => '0184ORIGINAL',
			],
		];

		$candidates = $this->invokeWebhookMethod('lookupCandidates', $transaction, $payload);

		$this->assertContains([
			'key' => OrderMetaManager::META_GATEWAY_ORDER_ID,
			'value' => '0184ORIGINAL',
		], $candidates);
		$this->assertContains([
			'key' => OrderMetaManager::META_TRANSACTION_ID,
			'value' => 'trOriginalCharge',
		], $candidates);
	}

	public function testRefundWebhookStoresRefundLookupWithoutOverwritingPrimaryChargeMeta(): void
	{
		$order = $this->orderDouble();
		$order->update_meta_data(OrderMetaManager::META_TRANSACTION_ID, 'trOriginalCharge');
		$order->update_meta_data(OrderMetaManager::META_GATEWAY_ORDER_ID, '0184ORIGINAL');

		$this->invokeWebhookMethod('syncOrderMeta', $order, $this->refundTransaction(), []);

		$this->assertSame('trOriginalCharge', $order->get_meta(OrderMetaManager::META_TRANSACTION_ID));
		$this->assertSame('0184ORIGINAL', $order->get_meta(OrderMetaManager::META_GATEWAY_ORDER_ID));
		$this->assertContains('trRefundWebhook', $order->meta[OrderMetaManager::META_TRANSACTION_ID_LOOKUP] ?? []);
		$this->assertContains('2947refund', $order->meta[OrderMetaManager::META_GATEWAY_ORDER_ID_LOOKUP] ?? []);
		$this->assertSame('trRefundWebhook', $order->get_meta('_wipop_refund_transaction_id'));
		$this->assertSame('2947refund', $order->get_meta('_wipop_refund_gateway_order_id'));
		$this->assertSame(TransactionStatus::COMPLETED->value, $order->get_meta('_wipop_refund_status'));
		$this->assertTrue($order->saved);
	}

	private function invokeWebhookMethod(string $method, mixed ...$args): mixed
	{
		$reflection = new ReflectionMethod(Webhook::class, $method);
		$reflection->setAccessible(true);

		return $reflection->invoke(null, ...$args);
	}

	private function refundTransaction(): Transaction
	{
		$transaction = new Transaction();
		$transaction->id = 'trRefundWebhook';
		$transaction->orderId = '2947refund';
		$transaction->amount = 1.0;
		$transaction->currency = 'EUR';
		$transaction->status = TransactionStatus::COMPLETED;
		$transaction->transactionType = 'REFUND';

		return $transaction;
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
