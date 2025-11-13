<?php

declare(strict_types=1);

namespace Wipop\Core\WooCommerce;

use WC_Data_Exception;
use WC_Order;
use Wipop\Core\Logger;
use Wipop\Gateways\Bizum\Gateway as BizumGateway;
use Wipop\Gateways\Card\Gateway as CardGateway;
use Wipop\Gateways\Googlepay\Gateway as GooglePayGateway;

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

		self::applyPaymentData(
			$order,
			$data['id'],
			__($data['title'], 'wipop')
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
				'Unable to set WooCommerce payment method title: ' . $exception->getMessage(),
				'warning'
			);
		}
	}
}
