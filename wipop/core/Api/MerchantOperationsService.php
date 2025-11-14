<?php

declare(strict_types=1);

namespace WipopWC\Core\Api;

use Wipop\Charge\ChargeMethod;
use Wipop\Utils\ProductType;
use Wipop\Utils\Terminal;

use function array_intersect;
use function array_map;
use function array_values;
use function get_transient;
use function is_array;
use function set_transient;

class MerchantOperationsService
{
	private const TRANSIENT_PREFIX = 'wipop_gateways_';
	private const CACHE_TTL_IN_SECONDS = 1 * 60 * 60;
	private const SUPPORTED_GATEWAYS = [ChargeMethod::CARD, ChargeMethod::BIZUM]; // add GOOGLE_PAY when available

	/**
	 * @return string[]
	 */
	public static function getAvailableGateways(bool $forceRefresh = false): array
	{
		$client = ClientFactory::create();
		$cacheKey = self::getCacheKey($client->getConfiguration()->getMerchantId());

		if (!$forceRefresh) {
			$cached = get_transient($cacheKey);
			if (is_array($cached)) {
				return $cached;
			}
		}

		$terminal = new Terminal(ClientFactory::getTerminalId());

		$gateways = SdkCaller::call(
			'merchant.list_payment_methods',
			static fn () => $client
				->merchantOperation()
				->listPaymentMethods(ProductType::PAYMENT_GATEWAY, $terminal)
		);

		$normalized = self::normalizeGateways($gateways);
		set_transient($cacheKey, $normalized, self::CACHE_TTL_IN_SECONDS);

		return $normalized;
	}

	private static function getCacheKey(string $merchantId): string
	{
		return self::TRANSIENT_PREFIX . $merchantId;
	}

	/**
	 * @param array<int, string> $gateways
	 *
	 * @return string[]
	 */
	private static function normalizeGateways(array $gateways): array
	{
		$normalized = array_map(static fn ($gateway) => strtoupper((string) $gateway), $gateways);

		return array_values(array_intersect($normalized, self::SUPPORTED_GATEWAYS));
	}
}
