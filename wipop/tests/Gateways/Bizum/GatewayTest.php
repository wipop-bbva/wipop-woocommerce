<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WipopWC\Gateways\Bizum\Gateway;

require_once dirname(__DIR__, 3) . '/gateways/bizum/bizum.php';

/**
 * @covers \WipopWC\Gateways\Bizum\Gateway
 *
 * @internal
 */
final class BizumGatewayTest extends TestCase
{
	public function testBizumDoesNotAdvertiseRefundSupport(): void
	{
		$this->assertNotContains('refunds', $this->gateway()->supports);
	}

	public function testBizumRefundReturnsUnsupportedError(): void
	{
		$result = $this->gateway()->process_refund(123, 1.0, 'QA');

		$this->assertInstanceOf(WP_Error::class, $result);
	}

	private function gateway(): Gateway
	{
		if (!defined('WIPOP_PLUGIN_FILE')) {
			define('WIPOP_PLUGIN_FILE', dirname(__DIR__, 3) . '/wipop.php');
		}

		return new Gateway();
	}
}
