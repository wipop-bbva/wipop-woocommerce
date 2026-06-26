<?php

declare(strict_types=1);

namespace WipopWC\Core;

use JsonException;
use Throwable;
use WC_Order;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;
use Wipop\Serializer\Hydrator;
use WipopWC\Core\Exception\WebhookException;
use WipopWC\Core\WooCommerce\ManualCaptureManager;
use WipopWC\Core\WooCommerce\OrderMetaManager;
use WipopWC\Core\WooCommerce\RecurringPayments;
use WipopWC\Core\WooCommerce\StatusHelper;
use WipopWC\Core\WooCommerce\TokenManager;
use WipopWC\Core\WooCommerce\WCOrderStatus;

use function __;
use function add_action;
use function array_key_exists;
use function esc_html;
use function esc_html__;
use function file_get_contents;
use function header;
use function is_array;
use function json_decode;
use function sanitize_text_field;
use function sprintf;
use function status_header;
use function strtolower;
use function strtoupper;
use function trim;
use function wc_get_order;
use function wc_get_orders;
use function wc_price;
use function wp_unslash;

defined('ABSPATH') || exit;

/**
 * Webhook handler for Wipop payment gateways.
 * https://woocommercesite.com/?wc-api=wipop_bbva
 */
class Webhook
{
	private const TRANSACTION_TYPE_REFUND = 'REFUND';
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
			$payload = self::decodePayload($body);
			$settings = WebhookAuth::getSettings();
			$isAuthenticated = self::authorizeRequest($settings);

			if (self::isVerificationEvent($payload)) {
				self::handleVerificationPayload($payload);
				self::respond(200, 'OK');

				return;
			}

			$transaction = self::hydrateTransaction($payload);

			Logger::log('Webhook received', 'info', [
				'transaction_id' => $transaction->id,
				'order_id' => $transaction->orderId,
				'status' => $transaction->status?->value,
			]);

			$order = self::locateOrder($transaction);

			if (!$order instanceof WC_Order) {
				throw new WebhookException(
					esc_html__('Could not find the order in the ecommerce.', 'wipop'),
					404
				);
			}

			self::applyTransactionToOrder($order, $transaction, $payload);
			Logger::log('Order updated via webhook', 'info', [
				'transaction_id' => $transaction->id,
				'order_id' => $transaction->orderId,
				'wc_order_id' => $order->get_id(),
				'status' => $transaction->status?->value,
			]);

			WebhookAuth::markConnectedIfPending($isAuthenticated);
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

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function authorizeRequest(array $settings): bool
	{
		$state = WebhookAuth::state($settings);
		$authorizationStatus = WebhookAuth::authorizationStatus($settings);

		if ($authorizationStatus === WebhookAuth::AUTH_INVALID_BASIC) {
			throw new WebhookException(esc_html__('Invalid webhook authentication.', 'wipop'), 401);
		}

		if ($authorizationStatus === WebhookAuth::AUTH_VALID_BASIC) {
			return true;
		}

		if (WebhookAuth::isAuthorizationRequired($state)) {
			throw new WebhookException(esc_html__('Webhook authentication is required.', 'wipop'), 401);
		}

		return false;
	}

	private static function ensurePostRequest(): void
	{
		$method = isset($_SERVER['REQUEST_METHOD'])
			? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))
			: 'GET';

		if (strtoupper($method) !== 'POST') {
			throw new WebhookException(esc_html__('Method not allowed', 'wipop'), 405);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function decodePayload(string $payload): array
	{
		try {
			$data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new WebhookException(
				esc_html__('Could not decode the body payload', 'wipop'),
				400,
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Chained exceptions are not rendered output.
				$exception
			);
		}

		if (!is_array($data)) {
			throw new WebhookException(
				esc_html__('Invalid body.', 'wipop'),
				400
			);
		}

		return $data;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function isVerificationEvent(array $payload): bool
	{
		return strtolower((string) ($payload['type'] ?? '')) === 'verification';
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function handleVerificationPayload(array $payload): void
	{
		$eventId = trim((string) ($payload['id'] ?? ''));
			$verificationCode = trim((string) ($payload['verification_code'] ?? ''));

		if ($eventId === '' || $verificationCode === '') {
			throw new WebhookException(esc_html__('Invalid verification payload.', 'wipop'), 400);
		}

		$updated = WebhookAuth::markVerificationEvent($eventId, $verificationCode);
		Logger::log('Webhook verification event received', 'info', [
			'event_id' => $eventId,
			'updated' => $updated,
		]);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function hydrateTransaction(array $data): Transaction
	{
		try {
			/** @var Transaction $transaction */
			$transaction = self::hydrator()->hydrate(Transaction::class, $data);
		} catch (Throwable $exception) {
			throw new WebhookException(
				esc_html__('Payload does not match the expected model.', 'wipop'),
				400,
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Chained exceptions are not rendered output.
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
				'key' => OrderMetaManager::META_GATEWAY_ORDER_ID,
				'value' => $transaction->orderId,
			];
			$candidates[] = [
				'key' => OrderMetaManager::META_GATEWAY_ORDER_ID_LOOKUP,
				'value' => $transaction->orderId,
			];
		}

		if (!empty($transaction->id)) {
			$candidates[] = [
				'key' => OrderMetaManager::META_TRANSACTION_ID,
				'value' => $transaction->id,
			];
			$candidates[] = [
				'key' => OrderMetaManager::META_TRANSACTION_ID_LOOKUP,
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
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Webhook fallback lookup is constrained to one order by internal Wipop metadata.
				'meta_query' => [
					[
						'key' => $candidate['key'],
						'value' => $candidate['value'],
					],
				],
			];
			$orders = wc_get_orders($args);

			if (empty($orders)) {
				continue;
			}

			$firstOrder = $orders[0] ?? null;

			if (!$firstOrder instanceof WC_Order) {
				continue;
			}

			return $firstOrder;
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function applyTransactionToOrder(WC_Order $order, Transaction $transaction, array $payload): void
	{
		$parentId = (int) $order->get_meta(OrderMetaManager::META_RECURRING_PARENT_ORDER_ID, true);
		if ($parentId <= 0) {
			if (RecurringPayments::maybeHandleRecurringWebhook($order, $transaction)) {
				return;
			}

			self::syncOrderMeta($order, $transaction, $payload);
			self::syncOrderStatus($order, $transaction);

			return;
		}

		$parentOrder = wc_get_order($parentId);

		if ($parentOrder instanceof WC_Order) {
			RecurringPayments::maybeHandleRecurringWebhookFromRenewalOrder($parentOrder, $order, $transaction);
		}

		self::syncOrderMeta($order, $transaction, $payload);
		self::syncOrderStatus($order, $transaction);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function syncOrderMeta(WC_Order $order, Transaction $transaction, array $payload): void
	{
		OrderMetaManager::sync($order, $transaction);

		$useCof = self::resolveUseCofFromPayload($payload);
		if ($useCof !== null) {
			$order->update_meta_data('_wipop_use_cof', $useCof ? 'yes' : 'no');
		}

		$card = $transaction->card;

		if ($card !== null) {
			if (!empty($card->id) && $useCof === true) {
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

		ManualCaptureManager::trySyncFromWebhook($order, $transaction);

		$order->save();

		if ($useCof === true && $card !== null && !empty($card->id)) {
			TokenManager::tryStoreCardToken($order, $card);
		}
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function resolveUseCofFromPayload(array $payload): ?bool
	{
		if (array_key_exists('use_cof', $payload)) {
			return (bool) $payload['use_cof'];
		}

		if (array_key_exists('useCof', $payload)) {
			return (bool) $payload['useCof'];
		}

		return null;
	}

	private static function syncOrderStatus(WC_Order $order, Transaction $transaction): void
	{
		$transactionType = strtoupper($transaction->transactionType ?? '');

		if ($transactionType === self::TRANSACTION_TYPE_REFUND) {
			$order->update_status(WCOrderStatus::REFUNDED);
			self::addRefundNote($order, $transaction);

			return;
		}

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
			case TransactionStatus::CANCELLED:
				$order->update_status(WCOrderStatus::CANCELLED, $statusDescription);

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

	private static function addRefundNote(WC_Order $order, Transaction $transaction): void
	{
		$amount = $transaction->amount ?? 0.0;
		$currency = $transaction->currency ?? $order->get_currency();

			$formattedAmount = wc_price($amount, ['currency' => $currency]);

			$note = sprintf(
				// translators: 1: refund amount, 2: Wipop transaction ID.
				__('Wipop: Reembolso de %1$s. Transacción: %2$s.', 'wipop'),
				$formattedAmount,
				$transaction->id ?? __('desconocida', 'wipop')
			);

		if (!empty($transaction->errorMessage)) {
			$note .= ' ' . $transaction->errorMessage;
		}

		$order->add_order_note($note);
	}

	private static function respond(int $statusCode, string $message): void
	{
		if ($statusCode === 401) {
			header('WWW-Authenticate: Basic realm="Wipop webhook"');
		}

		status_header($statusCode);
		echo esc_html($message);
		exit;
	}
}
