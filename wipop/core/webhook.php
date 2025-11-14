<?php

declare(strict_types=1);

namespace Wipop\Core;

use JsonException;
use Throwable;
use WC_Order;
use Wipop\Core\Exception\WebhookException;
use Wipop\Core\WooCommerce\PaymentMethodHelper;
use Wipop\Core\WooCommerce\StatusHelper;
use Wipop\Core\WooCommerce\TokenManager;
use Wipop\Core\WooCommerce\WCOrderStatus;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;
use Wipop\Serializer\Hydrator;

use function __;
use function add_action;
use function esc_html;
use function file_get_contents;
use function is_array;
use function json_decode;
use function status_header;
use function strtoupper;
use function wc_get_orders;

defined('ABSPATH') || exit;

/**
 * Webhook handler for Wipop payment gateways.
 * https://woocommercesite.com/?wc-api=wipop_bbva
 */
class Webhook
{
	private static ?Hydrator $hydrator = null;

	public static function init(): void
	{
		add_action('woocommerce_api_wipop_bbva', [__CLASS__, 'handle']);
	}

	public static function handle(): void
	{
		$body = file_get_contents('php://input');
		if ($body === false) {
			$body = '';
		}

		try {
			self::ensurePostRequest();
			$transaction = self::hydrateTransaction($body);

			Logger::log('Webhook received', 'info', [
				'transaction_id' => $transaction->id,
				'order_id' => $transaction->orderId,
				'status' => $transaction->status?->value,
			]);

			$order = self::locateOrder($transaction);

			if (!$order instanceof WC_Order) {
				throw new WebhookException(
					__('Could not find the order in the ecommerce.', 'wipop'),
					404
				);
			}

			self::applyTransactionToOrder($order, $transaction);
			Logger::log('Order updated via webhook', 'info', [
				'transaction_id' => $transaction->id,
				'order_id' => $transaction->orderId,
				'wc_order_id' => $order->get_id(),
				'status' => $transaction->status?->value,
			]);

			self::respond(200, 'OK');
		} catch (WebhookException $exception) {
			Logger::log($exception->getMessage(), 'error', [
				'status_code' => $exception->getStatusCode(),
			]);

			self::respond($exception->getStatusCode(), $exception->getMessage());
		} catch (Throwable $throwable) {
			Logger::log('Unexpected webhook error: ' . $throwable->getMessage(), 'error', [
				'exception' => $throwable,
			]);
			self::respond(500, __('Error processing the webhook.', 'wipop'));
		}
	}

	private static function ensurePostRequest(): void
	{
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

		if (strtoupper($method) !== 'POST') {
			throw new WebhookException(__('Method not allowed', 'wipop'), 405);
		}
	}

	private static function hydrateTransaction(string $payload): Transaction
	{
		try {
			$data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new WebhookException(
				__('Could not decode the body payload', 'wipop'),
				400,
				$exception
			);
		}

		if (!is_array($data)) {
			throw new WebhookException(
				__('Invalid body.', 'wipop'),
				400
			);
		}

		try {
			/** @var Transaction $transaction */
			$transaction = self::hydrator()->hydrate(Transaction::class, $data);
		} catch (Throwable $exception) {
			throw new WebhookException(
				__('Payload does not match the expected model.', 'wipop'),
				400,
				$exception
			);
		}

		return $transaction;
	}

	private static function hydrator(): Hydrator
	{
		if (self::$hydrator === null) {
			self::$hydrator = new Hydrator();
		}

		return self::$hydrator;
	}

	private static function locateOrder(Transaction $transaction): ?WC_Order
	{
		$candidates = [];

		if (!empty($transaction->orderId)) {
			$candidates[] = [
				'key' => '_wipop_gateway_order_id',
				'value' => $transaction->orderId,
			];
		}

		if (!empty($transaction->id)) {
			$candidates[] = [
				'key' => '_wipop_transaction_id',
				'value' => $transaction->id,
			];
		}

		if (empty($candidates)) {
			return null;
		}

		foreach ($candidates as $candidate) {
			$args = [
				'limit' => 1,
				'orderby' => 'date',
				'order' => 'DESC',
				'return' => 'objects',
				'meta_query' => [
					[
						'key' => $candidate['key'],
						'value' => $candidate['value'],
					],
				],
			];

			$orders = wc_get_orders($args);

			if (!empty($orders) && $orders[0] instanceof WC_Order) {
				return $orders[0];
			}
		}

		return null;
	}

	private static function applyTransactionToOrder(WC_Order $order, Transaction $transaction): void
	{
		self::syncOrderMeta($order, $transaction);
		self::syncOrderStatus($order, $transaction);

		PaymentMethodHelper::syncOrderPaymentMethod($order, $transaction->method);
	}

	private static function syncOrderMeta(WC_Order $order, Transaction $transaction): void
	{
		if (!empty($transaction->id)) {
			$order->set_transaction_id($transaction->id);
			$order->update_meta_data('_wipop_transaction_id', $transaction->id);
		}

		if (!empty($transaction->orderId)) {
			$order->update_meta_data('_wipop_gateway_order_id', $transaction->orderId);
		}

		if ($transaction->status !== null) {
			$order->update_meta_data('_wipop_payment_status', $transaction->status->value);
		}

		if (!empty($transaction->method)) {
			$order->update_meta_data('_wipop_payment_method', $transaction->method);
		}

		$card = $transaction->card;

		if ($card !== null) {
			if (!empty($card->id)) {
				$order->update_meta_data('_wipop_card_id', $card->id);
			}

			if (!empty($card->cardNumber ?? $card->number)) {
				$masked = $card->cardNumber ?? $card->number;
				$order->update_meta_data('_wipop_card_masked', $masked);
			}

			if (!empty($card->expirationMonth)) {
				$order->update_meta_data('_wipop_card_exp_month', $card->expirationMonth);
			}

			if (!empty($card->expirationYear)) {
				$order->update_meta_data('_wipop_card_exp_year', $card->expirationYear);
			}
		}

		if (!empty($transaction->errorCode)) {
			$order->update_meta_data('_wipop_error_code', $transaction->errorCode);
		}

		if (!empty($transaction->errorMessage)) {
			$order->update_meta_data('_wipop_error_message', $transaction->errorMessage);
		}

		$order->save();

		TokenManager::tryStoreCardToken($order, $transaction);
	}

	private static function syncOrderStatus(WC_Order $order, Transaction $transaction): void
	{
		$status = $transaction->status;

		if (!$status instanceof TransactionStatus) {
			return;
		}

		$statusDescription = self::statusDescription($transaction);

		switch ($status) {
			case TransactionStatus::COMPLETED:
				$transactionId = $transaction->id ?? '';
				$order->payment_complete($transactionId);

				return;
			case TransactionStatus::FAILED:
			case TransactionStatus::ERROR:
				$order->update_status(WCOrderStatus::FAILED, $statusDescription);

				return;
			case TransactionStatus::IN_PROGRESS:
				$order->update_status(WCOrderStatus::ON_HOLD, $statusDescription);

				return;
			case TransactionStatus::CHARGE_PENDING:
				$order->update_status(WCOrderStatus::PENDING, $statusDescription);

				return;
		}
	}

	private static function statusDescription(Transaction $transaction): string
	{
		return StatusHelper::format(
			$transaction->status?->value,
			$transaction->errorMessage
		);
	}

	private static function respond(int $statusCode, string $message): void
	{
		status_header($statusCode);
		echo esc_html($message);
		exit;
	}
}
