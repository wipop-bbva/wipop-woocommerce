<?php

declare(strict_types=1);

namespace WipopWC\Gateways\Support;

use Throwable;
use WC_Order;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use Wipop\Charge\ChargeMethod;
use Wipop\Charge\RefundParams;
use Wipop\Domain\Charge;
use Wipop\Domain\TransactionStatus;
use Wipop\Utils\ProductType;
use WipopWC\Core\Api\ClientFactory;
use WipopWC\Core\Api\SdkCaller;
use WipopWC\Core\Exception\ApiCallException;
use WipopWC\Core\Exception\ClientConfigurationException;
use WipopWC\Core\Logger;
use WipopWC\Core\WooCommerce\ManualCaptureManager;
use WipopWC\Core\WooCommerce\OrderMetaManager;
use WipopWC\Core\WooCommerce\StatusHelper;
use WipopWC\Core\WooCommerce\WCOrderStatus;
use WipopWC\Gateways\Card\Gateway as CardGateway;
use WP_Error;

use function __;
use function in_array;
use function is_object;
use function method_exists;
use function sanitize_text_field;
use function sprintf;
use function WC;
use function wc_add_notice;
use function wc_clean;
use function wc_get_order;
use function wc_maybe_reduce_stock_levels;
use function wc_price;

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

		$selectedToken = null;
		if ($method === ChargeMethod::CARD) {
			$selectedToken = $this->resolveSelectedPaymentToken($order);
		}

		$captureImmediately = $this->shouldCaptureImmediately($method);

		try {
			$client = ClientFactory::create();
			$params = ChargeRequestFactory::build(
				$order,
				$method,
				$this->get_return_url($order),
				$captureImmediately
			);
			$customerId = ChargeRequestFactory::resolveWipopCustomerId($order, (int) $order->get_user_id());

			$savePaymentMethod = $this->shouldSavePaymentMethod($order, $method, $selectedToken);
			$useCof = false;

			if ($savePaymentMethod) {
				$params->useCof(true);
				$useCof = true;
			}

			if ($selectedToken instanceof WC_Payment_Token_CC) {
				$params
					->sourceId($selectedToken->get_token())
					->useCof(true)
					->productType(ProductType::PAYMENT_GATEWAY)
					->originChannel('CHECKOUT')
				;
				$useCof = true;

				Logger::log(sprintf(
					'Usamos token COF en pedido %s',
					$order->get_id()
				));
			}

			if ($method === ChargeMethod::CARD) {
				$order->update_meta_data('_wipop_save_payment_method', $savePaymentMethod ? 'yes' : 'no');
				$order->update_meta_data('_wipop_use_cof', $useCof ? 'yes' : 'no');
				$order->save();
			}

			$charge = SdkCaller::call(
				'charge.create',
				static fn () => $client->chargeOperation()->create($params, $customerId)
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

			wc_add_notice(__('No se pudo iniciar el pago con Wipop.', 'wipop'), 'error');

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

		$this->syncOrderWithCharge($order, $charge, !$captureImmediately);

		return $this->buildProcessPaymentResponse($order, $charge);
	}

	/**
	 * @param int|string $orderId
	 *
	 * @return true|WP_Error
	 */
	protected function processGatewayRefund($orderId, ?float $amount, string $reason = '')
	{
		$order = wc_get_order($orderId);

		if (!$order instanceof WC_Order) {
			Logger::log('Unable to load order for Wipop refund: ' . $orderId, 'error');

			return new WP_Error(
				'wipop_refund_invalid_order',
				__('No se pudo procesar el reembolso porque el pedido es inválido.', 'wipop')
			);
		}

		if ($amount === null || $amount <= 0) {
			return new WP_Error(
				'wipop_refund_invalid_amount',
				__('El importe del reembolso tiene que ser mayor que cero.', 'wipop')
			);
		}

		$transactionId = $this->resolveRefundTransactionId($order);
		if ($transactionId === '') {
			Logger::log(
				sprintf('Cannot refund order %s: missing Wipop transaction id.', $order->get_id()),
				'error'
			);

			return new WP_Error(
				'wipop_refund_transaction_missing',
				__('No se encontró la transacción de Wipop para este pedido.', 'wipop')
			);
		}

		try {
			$client = ClientFactory::create();
			$params = (new RefundParams())->amount((float) $amount);
			$charge = SdkCaller::call(
				'charge.refund',
				static fn () => $client->chargeOperation()->refund($transactionId, $params)
			);
		} catch (ApiCallException | ClientConfigurationException $exception) {
			Logger::log(
				sprintf(
					'Wipop refund failed for order %s: %s',
					$order->get_id(),
					$exception->getMessage()
				),
				'error'
			);

			return new WP_Error(
				'wipop_refund_failed',
				__('No se pudo completar el reembolso. Error al comunicarse con Wipop', 'wipop')
			);
		} catch (Throwable $throwable) {
			Logger::log(
				sprintf(
					'Unexpected Wipop refund error for order %s: %s',
					$order->get_id(),
					$throwable->getMessage()
				),
				'error',
				['exception' => $throwable]
			);

			return new WP_Error(
				'wipop_refund_failed',
				__('No se pudo completar el reembolso con Wipop. Inténtalo de nuevo.', 'wipop')
			);
		}

		$refundTransactionId = $charge->refund->id ?? $charge->id ?? null;

		$formattedAmount = wc_price($amount, [
			'currency' => $order->get_currency(),
		]);

		$note = sprintf(
			__('Reembolso Wipop completado por %1$s. Transacción: %2$s.', 'wipop'),
			$formattedAmount,
			$refundTransactionId ?? __('desconocida', 'wipop')
		);

		$sanitizedReason = sanitize_text_field($reason);
		if ($sanitizedReason !== '') {
			$note .= ' ' . sprintf(__('Motivo: %s', 'wipop'), $sanitizedReason);
		}

		$order->add_order_note($note);

		return true;
	}

	private function syncOrderWithCharge(WC_Order $order, Charge $charge, bool $requiresManualCapture): void
	{
		OrderMetaManager::sync($order, $charge);

		if ($requiresManualCapture) {
			ManualCaptureManager::markAuthorized($order);
		} else {
			ManualCaptureManager::disable($order);
		}

		$order->save();

		$statusValue = $charge->status->value ?? TransactionStatus::CHARGE_PENDING->value;
		$statusDescription = StatusHelper::format(
			$statusValue,
			__('Pago iniciado con Wipop. Esperando confirmación.', 'wipop')
		);

		$statusToApply = $requiresManualCapture ? WCOrderStatus::ON_HOLD : WCOrderStatus::PENDING;
		$order->update_status($statusToApply, $statusDescription);

		// Avoid double stock reduction when WooCommerce has already reduced stock on order creation.
		wc_maybe_reduce_stock_levels($order->get_id());

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

		$transactionId = $charge->id ?? '';
		$order->add_order_note(sprintf(
			__('Transacción Wipop creada. Transacción: %s', 'wipop'),
			$transactionId !== '' ? $transactionId : __('sin ID', 'wipop')
		));

		if ($requiresManualCapture) {
			$order->add_order_note(__('Pago preautorizado con Wipop. Captura o anula la autorización desde las acciones del pedido.', 'wipop'));
		}
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

	private function resolveRefundTransactionId(WC_Order $order): string
	{
		$transactionId = (string) $order->get_meta(OrderMetaManager::META_TRANSACTION_ID, true);

		if ($transactionId !== '') {
			return $transactionId;
		}

		return $order->get_transaction_id();
	}

	private function resolveSelectedPaymentToken(WC_Order $order): ?WC_Payment_Token_CC
	{
		$gatewayId = CardGateway::ID;

		$fieldName = 'wc-' . $gatewayId . '-payment-token';
		if (empty($_POST[$fieldName])) {
			return null;
		}

		$raw = wc_clean($_POST[$fieldName]);

		$tokenId = (int) $raw;
		if ($tokenId <= 0) {
			return null;
		}

		$token = WC_Payment_Tokens::get($tokenId);
		if (!$token instanceof WC_Payment_Token_CC) {
			return null;
		}

		$userId = $order->get_user_id();
		if ($userId <= 0 || $token->get_user_id() !== $userId) {
			return null;
		}

		return $token;
	}

	private function shouldCaptureImmediately(string $method): bool
	{
		if ($method !== ChargeMethod::CARD) {
			return true;
		}

		return !ManualCaptureManager::isSiteManualCaptureEnabled();
	}

	private function shouldSavePaymentMethod(
		WC_Order $order,
		string $method,
		?WC_Payment_Token_CC $selectedToken
	): bool {
		if ($method !== ChargeMethod::CARD || $selectedToken instanceof WC_Payment_Token_CC) {
			return false;
		}

		if ($order->get_user_id() <= 0) {
			return false;
		}

		$fieldName = 'wc-' . CardGateway::ID . '-new-payment-method';
		if (empty($_POST[$fieldName])) {
			return false;
		}

		$raw = wc_clean($_POST[$fieldName]);

		return in_array($raw, ['1', 'true', 'yes', 'on'], true);
	}
}
