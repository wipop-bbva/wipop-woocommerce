<?php

declare(strict_types=1);

namespace WipopWC\Core;

use Throwable;

use function in_array;
use function is_array;
use function is_object;
use function is_scalar;
use function str_contains;
use function str_repeat;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;

defined('ABSPATH') || exit;

class Logger
{
	private const REDACTED = '[redacted]';
	private const SECRET_KEY_PARTS = [
		'authorization',
		'password',
		'privatekey',
		'secret',
		'apikey',
		'verificationcode',
		'cvv',
		'cvc',
		'cryptogram',
	];
	private const PARTIALLY_MASKED_KEY_PARTS = [
		'cardnumber',
		'pan',
		'sourceid',
		'cardid',
		'paymenttoken',
	];

	/**
	 * @param array<string, mixed> $context
	 */
	public static function log(string $message, string $level = 'info', array $context = []): void
	{
		if (!function_exists('wc_get_logger')) {
			return;
		}

		$logger = wc_get_logger();
		$context = array_merge(['source' => 'wipop'], self::sanitizeData($context));
		$logger->log($level, $message, $context);
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public static function sanitizeData($value)
	{
		return self::sanitizeValue($value);
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	private static function sanitizeValue($value, string $key = '', string $parentKey = '')
	{
		$normalizedKey = self::normalizeKey($key);
		$normalizedParentKey = self::normalizeKey($parentKey);

		if ($normalizedKey !== '' && self::containsAny($normalizedKey, self::SECRET_KEY_PARTS)) {
			return self::REDACTED;
		}

		if ($normalizedKey !== '' && self::shouldMask($normalizedKey, $normalizedParentKey)) {
			return self::maskValue($value);
		}

		if (is_array($value)) {
			$sanitized = [];

			foreach ($value as $childKey => $childValue) {
				$sanitized[$childKey] = self::sanitizeValue($childValue, (string) $childKey, $key);
			}

			return $sanitized;
		}

		if ($value instanceof Throwable) {
			return [
				'class' => $value::class,
				'message' => $value->getMessage(),
				'code' => $value->getCode(),
			];
		}

		if (is_object($value)) {
			return [
				'class' => $value::class,
			];
		}

		return $value;
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	private static function maskValue($value)
	{
		if ($value === null) {
			return null;
		}

		return is_scalar($value) ? self::maskScalar((string) $value) : self::REDACTED;
	}

	private static function shouldMask(string $normalizedKey, string $normalizedParentKey): bool
	{
		return ($normalizedParentKey === 'card' && in_array($normalizedKey, ['id', 'number'], true))
			|| self::containsAny($normalizedKey, self::PARTIALLY_MASKED_KEY_PARTS);
	}

	/**
	 * @param array<int, string> $needles
	 */
	private static function containsAny(string $value, array $needles): bool
	{
		foreach ($needles as $needle) {
			if ($needle !== '' && str_contains($value, $needle)) {
				return true;
			}
		}

		return false;
	}

	private static function normalizeKey(string $key): string
	{
		return strtolower(str_replace(['-', '_'], '', $key));
	}

	private static function maskScalar(string $value): string
	{
		$length = strlen($value);
		if ($length <= 4) {
			return self::REDACTED;
		}

		return str_repeat('*', $length - 4) . substr($value, -4);
	}
}
