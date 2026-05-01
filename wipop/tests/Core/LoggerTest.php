<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WipopWC\Core\Logger;

require_once dirname(__DIR__, 2) . '/core/logger.php';

/**
 * @covers \WipopWC\Core\Logger
 *
 * @internal
 */
final class LoggerTest extends TestCase
{
	public function testSanitizeDataRedactsSecretsAndMasksPaymentIdentifiers(): void
	{
		$sanitized = Logger::sanitizeData([
			'authorization' => 'Bearer abc',
			'verification_code' => 'verify-me',
			'card_number' => '5410080000000021',
			'card_id' => '123',
			'payment_token' => [
				'id' => 'tok-123',
			],
			'selected_token_id' => '12',
			'source_id' => null,
			'card' => [
				'id' => 'card-token-123456',
				'number' => '5410080000000021',
				'brand' => 'VISA',
			],
			'transaction_id' => 'tx-123',
		]);

		$this->assertSame('[redacted]', $sanitized['authorization']);
			$this->assertSame('[redacted]', $sanitized['verification_code']);
			$this->assertSame('************0021', $sanitized['card_number']);
			$this->assertSame('[redacted]', $sanitized['card_id']);
			$this->assertSame('[redacted]', $sanitized['payment_token']);
			$this->assertSame('12', $sanitized['selected_token_id']);
		$this->assertNull($sanitized['source_id']);
		$this->assertSame('*************3456', $sanitized['card']['id']);
		$this->assertSame('************0021', $sanitized['card']['number']);
		$this->assertSame('VISA', $sanitized['card']['brand']);
		$this->assertSame('tx-123', $sanitized['transaction_id']);
	}
}
