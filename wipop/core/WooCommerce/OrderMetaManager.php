<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce;

use WC_Order;
use Wipop\Domain\Charge;
use Wipop\Domain\Transaction;

use function esc_url_raw;

defined('ABSPATH') || exit;

final class OrderMetaManager
{
	public const META_TRANSACTION_ID = '_wipop_transaction_id';

	public static function sync(WC_Order $order, Transaction $transaction): void
	{
		$transactionId = $transaction->id ?? '';
		if ($transactionId !== '') {
			$order->set_transaction_id($transactionId);
			$order->update_meta_data(self::META_TRANSACTION_ID, $transactionId);
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
}
