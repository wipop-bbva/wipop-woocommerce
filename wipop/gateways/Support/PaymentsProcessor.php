<?php

declare(strict_types=1);

namespace Wipop\Gateways\Support;

use Throwable;
use WC_Order;
use Wipop\Core\Api\ClientFactory;
use Wipop\Core\Api\SdkCaller;
use Wipop\Core\Exception\ApiCallException;
use Wipop\Core\Exception\ClientConfigurationException;
use Wipop\Core\Logger;
use Wipop\Core\WooCommerce\WCOrderStatus;
use Wipop\Domain\Charge;

use function __;
use function esc_url_raw;
use function function_exists;
use function is_object;
use function method_exists;
use function sprintf;
use function WC;
use function wc_add_notice;
use function wc_get_order;
use function wc_reduce_stock_levels;

trait PaymentsProcessor
{
	/**
	 * @param int|string $orderId
	 *
	 * @return array<string, string>
	 */
	protected function processGatewayPayment($orderId, string $method): array
	{
		$order = wc_get_order($orderId);

		if (!$order instanceof WC_Order) {
			Logger::log('Unable to load order for Wipop payment: ' . $orderId, 'error');
			wc_add_notice(__('No se pudo iniciar el pago: el pedido es inválido.', 'wipop'), 'error');

			return ['result' => 'failure'];
		}

		try {
			$client = ClientFactory::create();
			$params = ChargeRequestFactory::build(
				$order,
				$method,
				$this->get_return_url($order)
			);

			$charge = SdkCaller::call(
				'charge.create',
				static fn () => $client->chargeOperation()->create($params)
			);
		} catch (ApiCallException | ClientConfigurationException $exception) {
			Logger::log(
				sprintf(
					'Wipop charge creation failed for order %s: %s',
					$order->get_id(),
					$exception->getMessage()
				),
				'error'
			);

			wc_add_notice($exception->getMessage(), 'error');

			return ['result' => 'failure'];
		} catch (Throwable $throwable) {
			Logger::log(
				sprintf(
					'Unexpected Wipop error for order %s: %s',
					$order->get_id(),
					$throwable->getMessage()
				),
				'error',
				['exception' => $throwable]
			);

			wc_add_notice(__('No se pudo iniciar el pago con Wipop. Inténtalo de nuevo.', 'wipop'), 'error');

			return ['result' => 'failure'];
		}

		Logger::log(
			sprintf(
				'Wipop charge %s created for order %s',
				$charge->id ?? 'unknown',
				$order->get_id()
			),
			'info'
		);

		$this->syncOrderWithCharge($order, $charge);

		return $this->buildProcessPaymentResponse($order, $charge);
	}

	private function syncOrderWithCharge(WC_Order $order, Charge $charge): void
	{
		$transactionId = $charge->id ?? '';
		if ($transactionId !== '') {
			$order->set_transaction_id($transactionId);
			$order->update_meta_data('_wipop_transaction_id', $transactionId);
		}

		$paymentMethod = $charge->paymentMethod;
		if ($paymentMethod !== null) {
			if (!empty($paymentMethod->url)) {
				$order->update_meta_data('_wipop_payment_url', esc_url_raw($paymentMethod->url));
			}

			if ($paymentMethod->type !== null) {
				$order->update_meta_data('_wipop_payment_flow', $paymentMethod->type->value);
			}
		}

		if (!empty($charge->orderId)) {
			$order->update_meta_data('_wipop_gateway_order_id', $charge->orderId);
		}

		if (!empty($charge->method)) {
			$order->update_meta_data('_wipop_payment_method', $charge->method);
		}

		if ($charge->status !== null) {
			$order->update_meta_data('_wipop_payment_status', $charge->status->value);
		}

		$order->save();

		$order->update_status(
			WCOrderStatus::PENDING,
			__('Pago iniciado con Wipop. Esperando confirmación.', 'wipop')
		);

		wc_reduce_stock_levels($order);

		if (function_exists('WC')) {
			/** @var mixed $woocommerce */
			$woocommerce = WC();

			if (is_object($woocommerce) && isset($woocommerce->cart)) {
				$cart = $woocommerce->cart;
				if (is_object($cart) && method_exists($cart, 'empty_cart')) {
					$cart->empty_cart();
				}
			}
		}

		$order->add_order_note(sprintf(
			__('Transacción Wipop creada. Transacción: %s', 'wipop'),
			$transactionId !== '' ? $transactionId : __('sin ID', 'wipop')
		));
	}

	/**
	 * @return array<string, string>
	 */
	private function buildProcessPaymentResponse(WC_Order $order, Charge $charge): array
	{
		$redirectUrl = $charge->paymentMethod !== null ? $charge->paymentMethod->url : null;

		if ($redirectUrl === null || $redirectUrl === '') {
			$redirectUrl = $this->get_return_url($order);
		}

		return [
			'result' => 'success',
			'redirect' => $redirectUrl,
		];
	}
}
