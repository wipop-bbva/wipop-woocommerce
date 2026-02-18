<?php

declare(strict_types=1);

namespace WipopWC\Core;

use Throwable;

use function add_query_arg;
use function base64_decode;
use function base64_encode;
use function get_option;
use function gmdate;
use function hash;
use function hash_equals;
use function home_url;
use function is_string;
use function openssl_cipher_iv_length;
use function openssl_decrypt;
use function openssl_encrypt;
use function random_bytes;
use function strlen;
use function substr;
use function trim;
use function update_option;
use function wp_salt;

defined('ABSPATH') || exit;

class WebhookAuth
{
	public const SETTINGS_OPTION = 'wipop_settings';
	public const STATE_DISCONNECTED = 'disconnected';
	public const STATE_PENDING_VERIFICATION = 'pending_verification';
	public const STATE_CONNECTED = 'connected';
	public const AUTH_MISSING = 'missing';
	public const AUTH_VALID_BASIC = 'valid_basic';
	public const AUTH_INVALID_BASIC = 'invalid_basic';
	public const AUTH_OTHER_SCHEME = 'other_scheme';
	public const KEY_USERNAME = 'webhook_auth_username';
	public const KEY_PASSWORD_ENCRYPTED = 'webhook_auth_password_enc';
	public const KEY_STATE = 'webhook_state';
	public const KEY_VERIFICATION_CODE = 'webhook_verification_code';
	public const KEY_VERIFICATION_EVENT_ID = 'webhook_verification_event_id';
	public const KEY_VERIFICATION_RECEIVED_AT = 'webhook_verification_received_at';
	public const KEY_CONNECTED_AT = 'webhook_connected_at';
	public const KEY_CREDENTIALS_ROTATED_AT = 'webhook_credentials_rotated_at';

	private const CIPHER = 'aes-256-cbc';

	/**
	 * @return array<string, mixed>
	 */
	public static function getSettings(): array
	{
		return (array) get_option(self::SETTINGS_OPTION, []);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function state(array $settings): string
	{
		return $settings[self::KEY_STATE] ?? self::STATE_DISCONNECTED;
	}

	public static function isAuthorizationRequired(string $state): bool
	{
		return $state === self::STATE_CONNECTED;
	}

	/**
	 * @param array<string, mixed> $settings
	 *
	 * @return array<string, mixed>
	 */
	public static function ensureCredentials(array $settings): array
	{
		$settings[self::KEY_STATE] = self::state($settings);

		$username = trim((string) ($settings[self::KEY_USERNAME] ?? ''));
		$password = self::decryptedPassword($settings);

		if ($username !== '' && $password !== '') {
			return $settings;
		}

		$generated = self::buildCredentials();
		if ($generated === null) {
			return $settings;
		}

		$settings[self::KEY_USERNAME] = $generated['username'];
		$settings[self::KEY_PASSWORD_ENCRYPTED] = $generated['password_enc'];
		$settings[self::KEY_STATE] = self::STATE_DISCONNECTED;
		$settings[self::KEY_CREDENTIALS_ROTATED_AT] = self::now();

		return $settings;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function ensureCredentialsStored(): array
	{
		$settings = self::getSettings();
		$ensured = self::ensureCredentials($settings);

		if ($ensured !== $settings) {
			self::saveSettings($ensured);
		}

		return $ensured;
	}

	/**
	 * @param array<string, mixed> $settings
	 *
	 * @return array<string, mixed>
	 */
	public static function regenerateCredentials(array $settings): array
	{
		$generated = self::buildCredentials();
		if ($generated === null) {
			return $settings;
		}

		$settings[self::KEY_USERNAME] = $generated['username'];
		$settings[self::KEY_PASSWORD_ENCRYPTED] = $generated['password_enc'];
		$settings[self::KEY_STATE] = self::STATE_DISCONNECTED;
		$settings[self::KEY_CREDENTIALS_ROTATED_AT] = self::now();
		$settings[self::KEY_VERIFICATION_CODE] = '';
		$settings[self::KEY_VERIFICATION_EVENT_ID] = '';
		$settings[self::KEY_VERIFICATION_RECEIVED_AT] = '';
		$settings[self::KEY_CONNECTED_AT] = '';

		return $settings;
	}

	/**
	 * @param array<string, mixed> $settings
	 *
	 * @return self::AUTH_INVALID_BASIC|self::AUTH_MISSING|self::AUTH_OTHER_SCHEME|self::AUTH_VALID_BASIC
	 */
	public static function authorizationStatus(array $settings): string
	{
		$header = self::readAuthorizationHeader();
		if ($header === '') {
			return self::AUTH_MISSING;
		}

		$parsed = self::parseAuthorizationHeader($header);
		if ($parsed['type'] !== self::AUTH_VALID_BASIC) {
			return $parsed['type'];
		}

		$credentials = $parsed['credentials'];
		$expectedUsername = trim((string) ($settings[self::KEY_USERNAME] ?? ''));
		$expectedPassword = self::decryptedPassword($settings);

		if ($expectedUsername === '' || $expectedPassword === '') {
			return self::AUTH_INVALID_BASIC;
		}

		$isValid = hash_equals($expectedUsername, $credentials['username'])
			&& hash_equals($expectedPassword, $credentials['password']);

		return $isValid ? self::AUTH_VALID_BASIC : self::AUTH_INVALID_BASIC;
	}

	public static function webhookUrl(): string
	{
		return (string) add_query_arg('wc-api', 'wipop_bbva', home_url('/'));
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function decryptedPassword(array $settings): string
	{
		$encrypted = trim((string) ($settings[self::KEY_PASSWORD_ENCRYPTED] ?? ''));
		if ($encrypted === '') {
			return '';
		}

		return self::decryptPassword($encrypted);
	}

	public static function markVerificationEvent(string $eventId, string $verificationCode): bool
	{
		$eventId = trim($eventId);
		$verificationCode = trim($verificationCode);

		if ($eventId === '' || $verificationCode === '') {
			return false;
		}

		$settings = self::getSettings();
		$currentEvent = trim((string) ($settings[self::KEY_VERIFICATION_EVENT_ID] ?? ''));
		if ($currentEvent === $eventId) {
			return false;
		}

		$settings[self::KEY_VERIFICATION_CODE] = $verificationCode;
		$settings[self::KEY_VERIFICATION_EVENT_ID] = $eventId;
		$settings[self::KEY_VERIFICATION_RECEIVED_AT] = self::now();
		$settings[self::KEY_STATE] = self::STATE_PENDING_VERIFICATION;

		self::saveSettings($settings);

		return true;
	}

	public static function markConnectedIfPending(bool $isAuthenticated): void
	{
		if (!$isAuthenticated) {
			return;
		}

		$settings = self::getSettings();
		if (self::state($settings) !== self::STATE_PENDING_VERIFICATION) {
			return;
		}

		$settings[self::KEY_STATE] = self::STATE_CONNECTED;
		$settings[self::KEY_CONNECTED_AT] = self::now();
		self::saveSettings($settings);
	}

	/**
	 * @return null|array{username: string, password_enc: string}
	 */
	private static function buildCredentials(): ?array
	{
		$usernameSuffix = self::randomHex(6);
		$password = self::randomHex(16);
		if ($usernameSuffix === null || $password === null) {
			Logger::log('Unable to generate webhook credentials.', 'error');

			return null;
		}

		$passwordEncrypted = self::encryptPassword($password);
		if ($passwordEncrypted === null) {
			Logger::log('Fail during webhook password encryption.', 'error');

			return null;
		}

		return [
			'username' => 'wipop_' . $usernameSuffix,
			'password_enc' => $passwordEncrypted,
		];
	}

	private static function randomHex(int $bytes): ?string
	{
		try {
			return bin2hex(random_bytes($bytes));
		} catch (Throwable $throwable) {
			Logger::log('Unable to generate secure ramndom bytes for webhook credentials.', 'error', [
				'exception' => $throwable,
			]);

			return null;
		}
	}

	private static function now(): string
	{
		return gmdate('c');
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function saveSettings(array $settings): void
	{
		update_option(self::SETTINGS_OPTION, $settings);
	}

	private static function encryptionKey(): string
	{
		$seed = (string) wp_salt('auth') . '|' . (string) wp_salt('secure_auth');

		return hash('sha256', $seed, true);
	}

	private static function encryptPassword(string $password): ?string
	{
		$ivLength = openssl_cipher_iv_length(self::CIPHER);
		if ($ivLength <= 0) {
			return null;
		}

		try {
			$iv = random_bytes($ivLength);
		} catch (Throwable $throwable) {
			Logger::log('Unable to generate Initialice Vector for password encryption.', 'error', [
				'exception' => $throwable,
			]);

			return null;
		}

		$encrypted = openssl_encrypt(
			$password,
			self::CIPHER,
			self::encryptionKey(),
			OPENSSL_RAW_DATA,
			$iv
		);

		if (!is_string($encrypted) || $encrypted === '') {
			return null;
		}

		return base64_encode($iv . $encrypted);
	}

	private static function decryptPassword(string $encryptedValue): string
	{
		$decoded = base64_decode($encryptedValue, true);
		if (!is_string($decoded) || $decoded === '') {
			return '';
		}

		$ivLength = openssl_cipher_iv_length(self::CIPHER);
		if ($ivLength <= 0 || strlen($decoded) <= $ivLength) {
			return '';
		}

		$iv = substr($decoded, 0, $ivLength);
		$cipherText = substr($decoded, $ivLength);

		$plain = openssl_decrypt(
			$cipherText,
			self::CIPHER,
			self::encryptionKey(),
			OPENSSL_RAW_DATA,
			$iv
		);

		return is_string($plain) ? $plain : '';
	}

	private static function readAuthorizationHeader(): string
	{
		if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
			return trim((string) $_SERVER['HTTP_AUTHORIZATION']);
		}

		return '';
	}

	/**
	 * @return array{type: string, credentials?: array{username: string, password: string}}
	 */
	private static function parseAuthorizationHeader(string $header): array
	{
		$prefix = 'Basic ';
		if (stripos($header, $prefix) !== 0) {
			return ['type' => self::AUTH_OTHER_SCHEME];
		}

		$encoded = substr($header, strlen($prefix));
		$credentials = self::parseBasicCredentials($encoded);
		if ($credentials === null) {
			return ['type' => self::AUTH_INVALID_BASIC];
		}

		return [
			'type' => self::AUTH_VALID_BASIC,
			'credentials' => $credentials,
		];
	}

	/**
	 * @return null|array{username: string, password: string}
	 */
	private static function parseBasicCredentials(string $encoded): ?array
	{
		$decoded = base64_decode($encoded, true);

		if (!is_string($decoded) || $decoded === '') {
			return null;
		}

		$separator = strpos($decoded, ':');
		if ($separator === false) {
			return null;
		}

		return [
			'username' => substr($decoded, 0, $separator),
			'password' => substr($decoded, $separator + 1),
		];
	}
}
