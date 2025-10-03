<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Basic unit tests.
 *
 * @internal
 *
 * @coversNothing
 */
final class Test extends TestCase
{
	/**
	 * Test if 1 === 1.
	 */
	public function testEquality(): void
	{
		$this->assertEquals(1, 1);
	}
}
