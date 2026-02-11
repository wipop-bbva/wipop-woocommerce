<?php

declare(strict_types=1);

namespace WipopWC\Core\Api;

use Wipop\Client\Environment;
use Wipop\Client\WipopClient;
use Wipop\Client\WipopClientConfiguration;
use WipopWC\Core\Exception\ClientConfigurationException;

use function __;
use function array_key_exists;
use function get_option;
use function is_numeric;
use function trim;

class ClientFactory
{
	/**
	 * @throws ClientConfigurationException when mandatory settings are missing
	 */
	public static function create(): WipopClient
	{
		$settings = (array) get_option('wipop_settings', []);
		$merchantId = self::stringSetting($settings, 'merchant_id');
		$privateKey = self::stringSetting($settings, 'private_key');
		$environment = self::resolveEnvironment((string) ($settings['environment'] ?? 'sandbox'));

		if ($merchantId === '' || $privateKey === '') {
			throw new ClientConfigurationException(
				__('Configura tu Merchant ID y Private Key para usar Wipop.', 'wipop')
			);
		}

		$configuration = new WipopClientConfiguration(
			$environment,
			$merchantId,
			$privateKey
		);

		return new WipopClient($configuration);
	}

	public static function getTerminalId(): int
	{
		$settings = (array) get_option('wipop_settings', []);
		$value = self::stringSetting($settings, 'terminal_id');

		if (!is_numeric($value)) {
			throw new ClientConfigurationException(
				__('Configura tu Terminal ID para utilizar Wipop.', 'wipop')
			);
		}

		return (int) $value;
	}

	private static function resolveEnvironment(string $environment): string
	{
		return $environment === 'production'
			? Environment::PRODUCTION
			: Environment::SANDBOX;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function stringSetting(array $settings, string $key): string
	{
		if (!array_key_exists($key, $settings)) {
			return '';
		}

		$value = $settings[$key];

		return trim((string) $value);
	}
}
