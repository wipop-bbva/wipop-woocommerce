<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce;

use WC_Data_Exception;
use WC_Order;
use WipopWC\Core\Logger;
use WipopWC\Gateways\Bizum\Gateway as BizumGateway;
use WipopWC\Gateways\Card\Gateway as CardGateway;
use WipopWC\Gateways\Googlepay\Gateway as GooglePayGateway;

use function __;
use function strtoupper;

defined('ABSPATH') || exit;

final class PaymentMethodHelper
{
	/**
	 * @var array<string, string>
	 */
	private const METHODS = [
		'CARD' => CardGateway::ID,
		'BIZUM' => BizumGateway::ID,
		'GOOGLE_PAY' => GooglePayGateway::ID,
	];

	/**
	 * Synchronize WooCommerce payment method fields with Wipop data.
	 */
	public static function syncOrderPaymentMethod(WC_Order $order, ?string $method): void
	{
		if ($method === null || $method === '') {
			return;
		}

		$key = strtoupper($method);

		if (!isset(self::METHODS[$key])) {
			return;
		}

		$title = match ($key) {
			'CARD' => __('Card', 'wipop'),
			'BIZUM' => __('Bizum', 'wipop'),
			'GOOGLE_PAY' => __('Google Pay', 'wipop'),
		};

		self::applyPaymentData(
			$order,
			self::METHODS[$key],
			$title
		);
	}

	private static function applyPaymentData(WC_Order $order, string $gatewayId, string $title): void
	{
		try {
			$order->set_payment_method($gatewayId);
		} catch (WC_Data_Exception $exception) {
			Logger::log(
				'No pudimos actualizar la información de método de pago: ' . $exception->getMessage(),
				'warning'
			);
		}

		try {
			$order->set_payment_method_title($title);
		} catch (WC_Data_Exception $exception) {
			Logger::log(
				'No pudimos actualizar el tíulo del método de pago: ' . $exception->getMessage(),
				'warning'
			);
		}
	}
}
