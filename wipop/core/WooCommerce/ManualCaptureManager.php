<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce;

use WC_Order;
use Wipop\Charge\CaptureParams;
use Wipop\Charge\ReversalParams;
use Wipop\Domain\Charge;
use Wipop\Domain\Transaction;
use Wipop\Domain\TransactionStatus;
use WipopWC\Core\Api\ClientFactory;
use WipopWC\Core\Api\SdkCaller;
use WipopWC\Core\Exception\ApiCallException;
use WipopWC\Core\Exception\ClientConfigurationException;
use WipopWC\Core\Logger;

use function __;
use function add_action;
use function add_filter;
use function get_option;
use function sprintf;
use function strtoupper;
use function wc_add_notice;
use function wc_price;

final class ManualCaptureManager
{
	public const META_ORDER_CAPTURE_ENABLED = '_wipop_order_capture';
	public const META_ORDER_CAPTURE_STATUS = '_wipop_manual_capture_status';

	public const STATUS_AUTHORIZED = 'authorized';
	public const STATUS_CAPTURED = 'captured';
	public const STATUS_REVERSED = 'reversed';

	public const OPTION_CAPTURE_MODE = 'manual_capture_mode';
	public const CAPTURE_MODE_AUTO = 'auto';
	public const CAPTURE_MODE_MANUAL = 'manual';

	private const SETTINGS_OPTION_NAME = 'wipop_settings';
	private const ORDER_ACTION_HOOK_PREFIX = 'woocommerce_order_action_';
	private const ORDER_ACTION_CAPTURE = 'wipop_manual_capture';
	private const ORDER_ACTION_REVERSAL = 'wipop_manual_reversal';
	private const META_ENABLED_VALUE_YES = 'yes';
	private const META_ENABLED_VALUE_NO = 'no';
	private const TRANSACTION_TYPE_CAPTURE = 'CAPTURE';
	private const TRANSACTION_TYPE_REVERSAL = 'REVERSAL';
	private const REVERSAL_REASON_DEFAULT = 'PRE_REVERSAL';
	private const SDK_OPERATION_CAPTURE = 'charge.capture';
	private const SDK_OPERATION_REVERSAL = 'charge.reversal';

	public static function init(): void
	{
		add_filter('woocommerce_order_actions', [__CLASS__, 'registerOrderActions'], 10, 2);
		add_action(self::ORDER_ACTION_HOOK_PREFIX . self::ORDER_ACTION_CAPTURE, [__CLASS__, 'handleCaptureAction']);
		add_action(self::ORDER_ACTION_HOOK_PREFIX . self::ORDER_ACTION_REVERSAL, [__CLASS__, 'handleReversalAction']);
	}

	public static function isSiteManualCaptureEnabled(): bool
	{
		$settings = (array) get_option(self::SETTINGS_OPTION_NAME, []);
		$mode = (string) ($settings[self::OPTION_CAPTURE_MODE] ?? self::CAPTURE_MODE_AUTO);

		return $mode === self::CAPTURE_MODE_MANUAL;
	}

	public static function registerOrderActions(array $actions, WC_Order $order): array
	{
		if (!self::isAwaitingCapture($order)) {
			return $actions;
		}

		$actions[self::ORDER_ACTION_CAPTURE] = __('Capturar preautorización con Wipop', 'wipop');
		$actions[self::ORDER_ACTION_REVERSAL] = __('Anular preautorización con Wipop', 'wipop');

		return $actions;
	}

	public static function handleCaptureAction(WC_Order $order): void
	{
		if (!self::isAwaitingCapture($order)) {
			return;
		}

		$transactionId = self::resolveTransactionId($order);

		if ($transactionId === '') {
			wc_add_notice(__('No encontramos la transacción en Wipop.', 'wipop'), 'error');
			Logger::log(
				sprintf(
					'Wipop capture failed: order %s not found',
					$order->get_id(),
				),
				'error'
			);

			return;
		}

		try {
			$client = ClientFactory::create();
			$params = (new CaptureParams())->amount((float) $order->get_total());

			$charge = SdkCaller::call(
				self::SDK_OPERATION_CAPTURE,
				static fn () => $client->chargeOperation()->capture($transactionId, $params)
			);
		} catch (ApiCallException | ClientConfigurationException $exception) {
			wc_add_notice(__('No hemos podido capturar la preautorización en Wipop', 'wipop'), 'error');
			Logger::log(
				sprintf(
					'Wipop capture failed for order %s: %s',
					$order->get_id(),
					$exception->getMessage()
				),
				'error'
			);

			return;
		}

		self::applyChargeMeta($order, $charge);
		self::setStatus($order, self::STATUS_CAPTURED);

		$captureTransactionId = $charge->id ?? $transactionId;
		$order->payment_complete($captureTransactionId);

		$formattedAmount = wc_price($order->get_total(), ['currency' => $order->get_currency()]);

		$order->add_order_note(sprintf(
			__('Wipop: Capturada preautorización de %1$s. Transacción: %2$s.', 'wipop'),
			$formattedAmount,
			$captureTransactionId
		));
	}

	public static function handleReversalAction(WC_Order $order): void
	{
		if (!self::isAwaitingCapture($order)) {
			return;
		}

		$transactionId = self::resolveTransactionId($order);

		if ($transactionId === '') {
			wc_add_notice(__('No encontramos la transacción en Wipop', 'wipop'), 'error');
			Logger::log(
				sprintf(
					'Wipop reversal failed: order %s not found',
					$order->get_id(),
				),
				'error'
			);

			return;
		}

		try {
			$client = ClientFactory::create();
			$params = (new ReversalParams())->reason(self::REVERSAL_REASON_DEFAULT);

			$charge = SdkCaller::call(
				self::SDK_OPERATION_REVERSAL,
				static fn () => $client->chargeOperation()->reversal($transactionId, $params)
			);
		} catch (ApiCallException | ClientConfigurationException $exception) {
			wc_add_notice(__('No hemos podido cancelar la preautorización en Wipop.', 'wipop'), 'error');
			Logger::log(
				sprintf(
					'Wipop reversal failed for order %s: %s',
					$order->get_id(),
					$exception->getMessage()
				),
				'error'
			);

			return;
		}

		self::applyChargeMeta($order, $charge);
		self::setStatus($order, self::STATUS_REVERSED);

		$order->update_status(
			WCOrderStatus::CANCELLED,
			__('Preautorización anulada manualmente en Wipop.', 'wipop')
		);

		$order->add_order_note(sprintf(
			__('Wipop: Anulaste la preautorización. Transacción: %s.', 'wipop'),
			$charge->id ?? $transactionId
		));
	}

	public static function markAuthorized(WC_Order $order): void
	{
		$order->update_meta_data(self::META_ORDER_CAPTURE_ENABLED, self::META_ENABLED_VALUE_YES);
		$order->update_meta_data(self::META_ORDER_CAPTURE_STATUS, self::STATUS_AUTHORIZED);
	}

	public static function disable(WC_Order $order): void
	{
		$order->update_meta_data(self::META_ORDER_CAPTURE_ENABLED, self::META_ENABLED_VALUE_NO);
		$order->delete_meta_data(self::META_ORDER_CAPTURE_STATUS);
	}

	public static function setStatus(WC_Order $order, string $status): void
	{
		if (!self::isEnabled($order)) {
			return;
		}

		$order->update_meta_data(self::META_ORDER_CAPTURE_STATUS, $status);
	}

	public static function isAwaitingCapture(WC_Order $order): bool
	{
		if (!self::isEnabled($order)) {
			return false;
		}

		return $order->get_meta(self::META_ORDER_CAPTURE_STATUS, true) === self::STATUS_AUTHORIZED;
	}

	public static function isEnabled(WC_Order $order): bool
	{
		return $order->get_meta(self::META_ORDER_CAPTURE_ENABLED, true) === self::META_ENABLED_VALUE_YES;
	}

	public static function trySyncFromWebhook(WC_Order $order, Transaction $transaction): void
	{
		if (!self::isEnabled($order)) {
			return;
		}

		$type = strtoupper($transaction->transactionType ?? '');

		if ($type === self::TRANSACTION_TYPE_CAPTURE) {
			self::setStatus($order, self::STATUS_CAPTURED);

			return;
		}

		if ($type === self::TRANSACTION_TYPE_REVERSAL) {
			self::setStatus($order, self::STATUS_REVERSED);

			return;
		}

		if ($transaction->status === TransactionStatus::COMPLETED) {
			self::setStatus($order, self::STATUS_CAPTURED);
		}
	}

	private static function applyChargeMeta(WC_Order $order, Charge $charge): void
	{
		OrderMetaManager::sync($order, $charge);
	}

	private static function resolveTransactionId(WC_Order $order): string
	{
		$transactionId = (string) $order->get_meta(OrderMetaManager::META_TRANSACTION_ID, true);

		if ($transactionId !== '') {
			return $transactionId;
		}

		return $order->get_transaction_id();
	}
}
