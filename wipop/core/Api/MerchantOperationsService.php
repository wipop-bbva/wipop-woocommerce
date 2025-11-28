<?php

declare(strict_types=1);

namespace WipopWC\Core\Api;

use Wipop\Charge\ChargeMethod;

class MerchantOperationsService
{
	private const SUPPORTED_GATEWAYS = [
		ChargeMethod::CARD,
		ChargeMethod::BIZUM,
		ChargeMethod::GOOGLE_PAY,
	];

	/**
	 * @return string[]
	 */
	public static function getAvailableGateways(bool $forceRefresh = false): array
	{
		return self::SUPPORTED_GATEWAYS;
	}
}
