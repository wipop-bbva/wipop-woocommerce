<?php

declare(strict_types=1);

namespace WipopWC\Core\Api;

use Throwable;
use Wipop\Client\Environment;
use Wipop\Client\WipopClient;
use Wipop\Client\WipopClientConfiguration;
use Wipop\Domain\Value\Terminal;
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

		try {
			return new WipopClient($configuration);
		} catch (Throwable $throwable) {
			throw new ClientConfigurationException(
				__('No se pudo inicializar el cliente de Wipop. Revisa la instalación del plugin.', 'wipop'),
				0,
				$throwable
			);
		}
	}

	public static function getTerminalId(): int
	{
		$settings = (array) get_option('wipop_settings', []);
		$value = self::stringSetting($settings, 'terminal_id');

		$terminalId = is_numeric($value) ? (int) $value : null;
		if (
			$terminalId === null
			|| $terminalId < self::getMinTerminalId()
			|| $terminalId > self::getMaxTerminalId()
		) {
			throw new ClientConfigurationException(
				__('Configura tu Terminal ID para utilizar Wipop.', 'wipop')
			);
		}

		return $terminalId;
	}

	public static function getMinTerminalId(): int
	{
		return Terminal::MIN_TERMINAL_ID;
	}

	public static function getMaxTerminalId(): int
	{
		return Terminal::MAX_TERMINAL_ID;
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
