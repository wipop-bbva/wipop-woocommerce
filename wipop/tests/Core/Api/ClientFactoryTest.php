<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wipop\Domain\Value\Terminal;
use WipopWC\Core\Api\ClientFactory;

/**
 * @covers \WipopWC\Core\Api\ClientFactory
 *
 * @internal
 */
final class ClientFactoryTest extends TestCase
{
	public function testTerminalLimitsMatchSdkConstants(): void
	{
		$this->assertSame(Terminal::MIN_TERMINAL_ID, ClientFactory::getMinTerminalId());
		$this->assertSame(Terminal::MAX_TERMINAL_ID, ClientFactory::getMaxTerminalId());
	}
}
