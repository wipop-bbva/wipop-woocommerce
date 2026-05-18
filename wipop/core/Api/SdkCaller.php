<?php

declare(strict_types=1);

namespace WipopWC\Core\Api;

use Throwable;
use Wipop\Exception\WipopException;
use WipopWC\Core\Exception\ApiCallException;
use WipopWC\Core\Logger;

use function esc_html;
use function esc_html__;
use function sprintf;

defined('ABSPATH') || exit;

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
		} catch (WipopException $exception) {
			Logger::log(
				sprintf('Wipop API error during %s: %s', $operation, $exception->getMessage()),
				'error',
				['exception' => $exception]
			);

			throw new ApiCallException(
				esc_html(self::buildUserMessage($exception)),
				0,
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Chained exceptions are not rendered output.
				$exception
			);
		} catch (Throwable $throwable) {
			Logger::log(
				sprintf('Unexpected Wipop error during %s: %s', $operation, $throwable->getMessage()),
				'error',
				['exception' => $throwable]
			);

			throw new ApiCallException(
				esc_html__('Ha ocurrido un error inesperado al comunicarse con Wipop.', 'wipop'),
				0,
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Chained exceptions are not rendered output.
				$throwable
			);
		}
	}

	private static function buildUserMessage(WipopException $exception): string
	{
		return sprintf(
			// translators: %s: Wipop API error message.
			__('No se pudo completar la operación con Wipop: %s', 'wipop'),
			$exception->getMessage()
		);
	}
}
