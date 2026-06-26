<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;
use WipopWC\Core\WooCommerce\ManualCaptureManager;
use WipopWC\Core\WooCommerce\OrderMetaManager;

require_once dirname(__DIR__, 3) . '/core/WooCommerce/ManualCaptureManager.php';

/**
 * @covers \WipopWC\Core\WooCommerce\ManualCaptureManager
 *
 * @internal
 */
final class ManualCaptureManagerTest extends TestCase
{
	public function testPendingAuthorizationDoesNotExposeCaptureActionsUntilWebhookAuthorizes(): void
	{
		$order = $this->createOrder();

		ManualCaptureManager::markPendingAuthorization($order);

		$this->assertSame('yes', $order->get_meta(ManualCaptureManager::META_ORDER_CAPTURE_ENABLED));
		$this->assertSame(ManualCaptureManager::STATUS_PENDING_AUTHORIZATION, $order->get_meta(ManualCaptureManager::META_ORDER_CAPTURE_STATUS));
		$this->assertFalse(ManualCaptureManager::isAwaitingCapture($order));

		$transaction = new Transaction();
		$transaction->status = TransactionStatus::IN_PROGRESS;

		ManualCaptureManager::trySyncFromWebhook($order, $transaction);

		$this->assertSame(ManualCaptureManager::STATUS_AUTHORIZED, $order->get_meta(ManualCaptureManager::META_ORDER_CAPTURE_STATUS));
		$this->assertTrue(ManualCaptureManager::isAwaitingCapture($order));
	}

	public function testFailedWebhookStatusDoesNotExposeManualCaptureActions(): void
	{
		$order = $this->createOrder();

		ManualCaptureManager::markPendingAuthorization($order);

		$transaction = new Transaction();
		$transaction->status = TransactionStatus::FAILED;

		ManualCaptureManager::trySyncFromWebhook($order, $transaction);

		$this->assertSame(ManualCaptureManager::STATUS_FAILED, $order->get_meta(ManualCaptureManager::META_ORDER_CAPTURE_STATUS));
		$this->assertFalse(ManualCaptureManager::isAwaitingCapture($order));
	}

	public function testCancelledWebhookStatusMarksManualCaptureAsReversed(): void
	{
		$order = $this->createOrder();

		ManualCaptureManager::markAuthorized($order);

		$transaction = new Transaction();
		$transaction->transactionType = 'CHARGE';
		$transaction->status = TransactionStatus::CANCELLED;

		ManualCaptureManager::trySyncFromWebhook($order, $transaction);

		$this->assertSame(ManualCaptureManager::STATUS_REVERSED, $order->get_meta(ManualCaptureManager::META_ORDER_CAPTURE_STATUS));
		$this->assertFalse(ManualCaptureManager::isAwaitingCapture($order));
	}

	public function testDelayedAuthorizationWebhookDoesNotReopenTerminalManualCaptureStatus(): void
	{
		$transaction = new Transaction();
		$transaction->status = TransactionStatus::IN_PROGRESS;

		foreach ([ManualCaptureManager::STATUS_CAPTURED, ManualCaptureManager::STATUS_REVERSED, ManualCaptureManager::STATUS_FAILED] as $terminalStatus) {
			$order = $this->createOrder();

			ManualCaptureManager::markPendingAuthorization($order);
			ManualCaptureManager::setStatus($order, $terminalStatus);

			ManualCaptureManager::trySyncFromWebhook($order, $transaction);

			$this->assertSame($terminalStatus, $order->get_meta(ManualCaptureManager::META_ORDER_CAPTURE_STATUS));
			$this->assertFalse(ManualCaptureManager::isAwaitingCapture($order));
		}
	}

	public function testFailedManualOperationRestoresAuthorizationTransactionIdForRetry(): void
	{
		$order = $this->createOrder();
		$order->set_transaction_id('failed-operation-id');
		$order->update_meta_data(OrderMetaManager::META_TRANSACTION_ID, 'failed-operation-id');

		$restoreTransactionId = new ReflectionMethod(ManualCaptureManager::class, 'restoreTransactionId');
		$restoreTransactionId->invoke(null, $order, 'auth-transaction-id');

		$this->assertSame('auth-transaction-id', $order->get_transaction_id());
		$this->assertSame('auth-transaction-id', $order->get_meta(OrderMetaManager::META_TRANSACTION_ID));
	}

	private function createOrder(): WC_Order
	{
		return new class extends WC_Order {
			/**
			 * @var array<string, mixed>
			 */
			private array $meta = [];
			private string $transactionId = '';

			public function get_meta($key = '', $single = true, $context = 'view'): mixed
			{
				return $this->meta[$key] ?? '';
			}

			public function update_meta_data($key, $value, $meta_id = 0): void
			{
				$this->meta[$key] = $value;
			}

			public function get_transaction_id($context = 'view'): string
			{
				return $this->transactionId;
			}

			public function set_transaction_id($value): void
			{
				$this->transactionId = (string) $value;
			}
		};
	}
}
