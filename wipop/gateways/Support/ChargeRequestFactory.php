<?php

declare(strict_types=1);

namespace WipopWC\Gateways\Support;

use WC_Order;
use Wipop\Domain\Input\Address;
use Wipop\Domain\Input\Customer;
use Wipop\Domain\Language;
use Wipop\Domain\ProductType;
use Wipop\Domain\Value\OrderId;
use Wipop\Domain\Value\Terminal;
use Wipop\Operations\Charge\Params\CreateChargeParams;
use WipopWC\Core\Api\ClientFactory;

use function __;
use function array_filter;
use function esc_url_raw;
use function get_bloginfo;
use function get_locale;
use function get_user_meta;
use function implode;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;

defined('ABSPATH') || exit;

/**
 * Factory that builds CreateChargeParams instances from WooCommerce orders.
 */
final class ChargeRequestFactory
{
	public static function build(
		WC_Order $order,
		string $method,
		string $redirectUrl,
		bool $captureImmediately,
		?OrderId $customOrderId = null
	): CreateChargeParams {
		$params = (new CreateChargeParams())
			->method($method)
			->amount((float) $order->get_total())
			->currency($order->get_currency())
			->description(self::buildDescription($order))
			->redirectUrl(esc_url_raw($redirectUrl))
			->orderId($customOrderId ?? OrderIdFactory::fromOrder($order))
			->productType(ProductType::PAYMENT_GATEWAY)
			->originChannel('CHECKOUT')
			->language(self::resolveLanguage())
			->terminal(new Terminal(ClientFactory::getTerminalId()))
			->capture($captureImmediately)
		;

		$customer = self::buildCustomer($order);
		if ($customer !== null) {
			$params->customer($customer);
		}

		return $params;
	}

	public static function resolveWipopCustomerId(WC_Order $order, int $userId, ?WC_Order $fallbackOrder = null): ?string
	{
		$fromUserMeta = $userId > 0 ? (string) get_user_meta($userId, '_wipop_customer_id', true) : '';
		if ($fromUserMeta !== '') {
			return $fromUserMeta;
		}

		$fromOrderMeta = (string) $order->get_meta('_wipop_customer_id', true);
		if ($fromOrderMeta !== '') {
			return $fromOrderMeta;
		}

		if ($fallbackOrder instanceof WC_Order) {
			$fromFallbackOrderMeta = (string) $fallbackOrder->get_meta('_wipop_customer_id', true);
			if ($fromFallbackOrderMeta !== '') {
				return $fromFallbackOrderMeta;
			}
		}

		return null;
	}

	public static function normalizeLanguage(string $locale): string
	{
		$normalizedLocale = strtolower(str_replace('_', '-', trim($locale)));
		if ($normalizedLocale === '') {
			return Language::SPANISH;
		}

		return match (true) {
			str_starts_with($normalizedLocale, 'ca') => 'ca-ES',
			str_starts_with($normalizedLocale, 'eu') => 'eu-ES',
			str_starts_with($normalizedLocale, 'gl') => 'gl-ES',
			str_starts_with($normalizedLocale, 'en') => 'en-GB',
			str_starts_with($normalizedLocale, 'es') => Language::SPANISH,
			default => Language::SPANISH,
		};
	}

	private static function buildDescription(WC_Order $order): string
	{
		return sprintf(
			// translators: 1: WooCommerce order number, 2: site name.
			__('Pedido #%1$s en %2$s', 'wipop'),
			$order->get_order_number(),
			get_bloginfo('name')
		);
	}

	private static function buildCustomer(WC_Order $order): ?Customer
	{
		$email = trim((string) $order->get_billing_email());
		if ($email === '') {
			return null;
		}

		$firstName = trim((string) $order->get_billing_first_name());
		$lastName = trim((string) $order->get_billing_last_name());

		if ($firstName === '') {
			$firstName = __('Cliente', 'wipop');
		}

		if ($lastName === '') {
			$lastName = __('WooCommerce', 'wipop');
		}

		$userId = (int) $order->get_user_id();
		$wipopCustomerId = self::resolveWipopCustomerId($order, $userId);

		return new Customer(
			$firstName,
			$lastName,
			$email,
			$wipopCustomerId,
			self::resolveExternalCustomerId($order, $userId, $email),
			self::resolvePhone($order),
			self::buildAddress($order)
		);
	}

	private static function resolveExternalCustomerId(WC_Order $order, int $userId, string $email): ?string
	{
		if ($userId > 0) {
			return (string) $userId;
		}

		// Guesst checkout
		return $email !== '' ? $email : null;
	}

	private static function resolvePhone(WC_Order $order): ?string
	{
		$phone = trim($order->get_billing_phone());

		return $phone === '' ? null : $phone;
	}

	private static function buildAddress(WC_Order $order): ?Address
	{
		$addressLine = trim(implode(' ', array_filter([
			$order->get_billing_address_1(),
			$order->get_billing_address_2(),
		])));

		$postcode = trim($order->get_billing_postcode());
		$city = trim($order->get_billing_city());
		$state = trim($order->get_billing_state());
		$country = trim($order->get_billing_country());

		if ($addressLine === '' && $postcode === '' && $city === '' && $country === '') {
			return null;
		}

		return new Address(
			$addressLine,
			$postcode,
			$city,
			$state,
			$country
		);
	}

	private static function resolveLanguage(): string
	{
		return self::normalizeLanguage(get_locale());
	}
}
