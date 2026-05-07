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
	 * @var array<string, array{id: string, title: string}>
	 */
	private const METHODS = [
		'CARD' => [
			'id' => CardGateway::ID,
			'title' => 'Card',
		],
		'BIZUM' => [
			'id' => BizumGateway::ID,
			'title' => 'Bizum',
		],
		'GOOGLE_PAY' => [
			'id' => GooglePayGateway::ID,
			'title' => 'Google Pay',
		],
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

		$data = self::METHODS[$key];

		// Translate payment method title based on method type
		$translatedTitle = match ($key) {
			'CARD' => __('Card', 'wipop'),
			'BIZUM' => __('Bizum', 'wipop'),
			'GOOGLE_PAY' => __('Google Pay', 'wipop'),
			default => $data['title'],
		};

		self::applyPaymentData(
			$order,
			$data['id'],
			$translatedTitle
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
