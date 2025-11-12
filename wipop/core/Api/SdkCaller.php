<?php

declare(strict_types=1);

namespace Wipop\Core\Api;

use Throwable;
use Wipop\Client\Exception\WipopApiException;
use Wipop\Core\Api\Exception\ApiCallException;
use Wipop\Core\Logger;

use function sprintf;

class SdkCaller
{
	/**
	 * @template T
	 *
	 * @param callable():T $callback callback that executes the SDK request
	 *
	 * @return T
	 *
	 * @throws ApiCallException when the SDK reports an error
	 */
	public static function call(string $operation, callable $callback)
	{
		try {
			return $callback();
		} catch (WipopApiException $exception) {
			Logger::log(
				sprintf('Wipop API error during %s: %s', $operation, $exception->getMessage()),
				'error',
				['exception' => $exception]
			);

			throw new ApiCallException(
				self::buildUserMessage($exception),
				0,
				$exception
			);
		} catch (Throwable $throwable) {
			Logger::log(
				sprintf('Unexpected Wipop error during %s: %s', $operation, $throwable->getMessage()),
				'error',
				['exception' => $throwable]
			);

			throw new ApiCallException(
				__('Ha ocurrido un error inesperado al comunicarse con Wipop.', 'wipop'),
				0,
				$throwable
			);
		}
	}

	private static function buildUserMessage(WipopApiException $exception): string
	{
		return sprintf(
			__('No se pudo completar la operación con Wipop: %s', 'wipop'),
			$exception->getMessage()
		);
	}
}
