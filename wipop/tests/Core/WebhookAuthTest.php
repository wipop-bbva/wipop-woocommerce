<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WipopWC\Core\WebhookAuth;

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__, 2) . '/');
}

require_once dirname(__DIR__, 2) . '/core/webhook-auth.php';

/**
 * @covers \WipopWC\Core\WebhookAuth
 *
 * @internal
 */
final class WebhookAuthTest extends TestCase
{
	/**
	 * @var array<string, mixed>
	 */
	private array $serverBackup = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->serverBackup = $_SERVER;
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->serverBackup;
		parent::tearDown();
	}

	public function testEnsureCredentialsGeneratesRequiredValues(): void
	{
		$settings = WebhookAuth::ensureCredentials([]);

		$this->assertNotSame('', (string) ($settings[WebhookAuth::KEY_USERNAME] ?? ''));
		$this->assertNotSame('', (string) ($settings[WebhookAuth::KEY_PASSWORD_ENCRYPTED] ?? ''));
		$this->assertSame(WebhookAuth::STATE_DISCONNECTED, WebhookAuth::state($settings));
		$this->assertNotSame('', WebhookAuth::decryptedPassword($settings));
	}

	public function testRegenerateCredentialsResetsConnectionState(): void
	{
		$settings = WebhookAuth::ensureCredentials([]);
		$settings[WebhookAuth::KEY_STATE] = WebhookAuth::STATE_CONNECTED;
		$settings[WebhookAuth::KEY_VERIFICATION_CODE] = 'code123';
		$settings[WebhookAuth::KEY_VERIFICATION_EVENT_ID] = 'event123';
		$settings[WebhookAuth::KEY_VERIFICATION_RECEIVED_AT] = '2026-01-01T00:00:00+00:00';
		$settings[WebhookAuth::KEY_CONNECTED_AT] = '2026-01-01T00:00:00+00:00';

		$regenerated = WebhookAuth::regenerateCredentials($settings);

		$this->assertSame(WebhookAuth::STATE_DISCONNECTED, WebhookAuth::state($regenerated));
		$this->assertSame('', (string) ($regenerated[WebhookAuth::KEY_VERIFICATION_CODE] ?? ''));
		$this->assertSame('', (string) ($regenerated[WebhookAuth::KEY_VERIFICATION_EVENT_ID] ?? ''));
		$this->assertSame('', (string) ($regenerated[WebhookAuth::KEY_VERIFICATION_RECEIVED_AT] ?? ''));
		$this->assertSame('', (string) ($regenerated[WebhookAuth::KEY_CONNECTED_AT] ?? ''));
	}

	public function testAuthorizationStatusIsMissingWithoutHeader(): void
	{
		$settings = WebhookAuth::ensureCredentials([]);

		$this->assertSame(WebhookAuth::AUTH_MISSING, WebhookAuth::authorizationStatus($settings));
	}

	public function testAuthorizationStatusAcceptsValidBasicHeader(): void
	{
		$settings = WebhookAuth::ensureCredentials([]);
		$username = (string) ($settings[WebhookAuth::KEY_USERNAME] ?? '');
		$password = WebhookAuth::decryptedPassword($settings);

		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode($username . ':' . $password);

		$this->assertSame(WebhookAuth::AUTH_VALID_BASIC, WebhookAuth::authorizationStatus($settings));
	}

	public function testAuthorizationStatusRejectsInvalidBasicHeader(): void
	{
		$settings = WebhookAuth::ensureCredentials([]);
		$username = (string) ($settings[WebhookAuth::KEY_USERNAME] ?? '');

		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode($username . ':wrong');

		$this->assertSame(WebhookAuth::AUTH_INVALID_BASIC, WebhookAuth::authorizationStatus($settings));
	}

	public function testAuthorizationStatusTreatsOtherSchemeAsOtherScheme(): void
	{
		$settings = WebhookAuth::ensureCredentials([]);
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc123';

		$this->assertSame(WebhookAuth::AUTH_OTHER_SCHEME, WebhookAuth::authorizationStatus($settings));
	}

	public function testAuthorizationStatusRejectsMalformedBasicHeader(): void
	{
		$settings = WebhookAuth::ensureCredentials([]);
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic malformed-not-base64';

		$this->assertSame(WebhookAuth::AUTH_INVALID_BASIC, WebhookAuth::authorizationStatus($settings));
	}

	public function testAuthorizationRequiredOnlyForConnectedState(): void
	{
		$this->assertFalse(WebhookAuth::isAuthorizationRequired(WebhookAuth::STATE_DISCONNECTED));
		$this->assertFalse(WebhookAuth::isAuthorizationRequired(WebhookAuth::STATE_PENDING_VERIFICATION));
		$this->assertTrue(WebhookAuth::isAuthorizationRequired(WebhookAuth::STATE_CONNECTED));
	}
}
