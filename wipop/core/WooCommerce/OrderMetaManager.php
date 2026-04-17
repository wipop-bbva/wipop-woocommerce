<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce;

use WC_Order;
use Wipop\Domain\Charge;
use Wipop\Domain\Transaction;

use function esc_url_raw;
use function trim;
use function update_user_meta;

defined('ABSPATH') || exit;

final class OrderMetaManager
{
	public const META_TRANSACTION_ID = '_wipop_transaction_id';
	public const META_GATEWAY_ORDER_ID = '_wipop_gateway_order_id';
	public const META_TRANSACTION_ID_LOOKUP = '_wipop_transaction_id_lookup';
	public const META_GATEWAY_ORDER_ID_LOOKUP = '_wipop_gateway_order_id_lookup';
	public const META_RECURRING_PARENT_ORDER_ID = '_wipop_recurring_parent_order_id';

	public static function sync(WC_Order $order, Transaction $transaction): void
	{
		$transactionId = $transaction->id ?? '';
		if ($transactionId !== '') {
			$order->set_transaction_id($transactionId);
			$order->update_meta_data(self::META_TRANSACTION_ID, $transactionId);
			self::addTransactionIdLookup($order, $transactionId);
		}

		if (!empty($transaction->orderId)) {
			$order->update_meta_data(self::META_GATEWAY_ORDER_ID, $transaction->orderId);
			self::addGatewayOrderIdLookup($order, $transaction->orderId);
		}

		if ($transaction->status !== null) {
			$order->update_meta_data('_wipop_payment_status', $transaction->status->value);
		}

		if (!empty($transaction->customerId)) {
			$order->update_meta_data('_wipop_customer_id', $transaction->customerId);

			$userId = $order->get_user_id();
			if ($userId > 0) {
				update_user_meta($userId, '_wipop_customer_id', $transaction->customerId);
			}
		}

		if (!empty($transaction->method)) {
			$order->update_meta_data('_wipop_payment_method', $transaction->method);
		}

		if (!empty($transaction->transactionType)) {
			$order->update_meta_data('_wipop_transaction_type', $transaction->transactionType);
		}

		if ($transaction instanceof Charge) {
			$paymentMethod = $transaction->paymentMethod;

			if ($paymentMethod !== null) {
				if (!empty($paymentMethod->url)) {
					$order->update_meta_data('_wipop_payment_url', esc_url_raw($paymentMethod->url));
				}

				if ($paymentMethod->type !== null) {
					$order->update_meta_data('_wipop_payment_flow', $paymentMethod->type->value);
				}
			}
		}

		PaymentMethodHelper::syncOrderPaymentMethod($order, $transaction->method ?? null);
	}

	public static function addGatewayOrderIdLookup(WC_Order $order, string $orderId): void
	{
		self::addLookupMeta($order, self::META_GATEWAY_ORDER_ID_LOOKUP, $orderId);
	}

	public static function addTransactionIdLookup(WC_Order $order, string $transactionId): void
	{
		self::addLookupMeta($order, self::META_TRANSACTION_ID_LOOKUP, $transactionId);
	}

	private static function addLookupMeta(WC_Order $order, string $key, string $value): void
	{
		$value = trim($value);
		if ($value === '') {
			return;
		}

		$order->add_meta_data($key, $value, false);
	}
}
