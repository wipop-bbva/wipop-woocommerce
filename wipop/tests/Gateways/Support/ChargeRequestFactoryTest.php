<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WipopWC\Gateways\Support\ChargeRequestFactory;

require_once dirname(__DIR__, 3) . '/gateways/Support/ChargeRequestFactory.php';

/**
 * @covers \WipopWC\Gateways\Support\ChargeRequestFactory
 *
 * @internal
 */
final class ChargeRequestFactoryTest extends TestCase
{
	#[DataProvider('languageProvider')]
	public function testNormalizeLanguageMapsSupportedWipopLocales(string $locale, string $expected): void
	{
		$this->assertSame($expected, ChargeRequestFactory::normalizeLanguage($locale));
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function languageProvider(): array
	{
		return [
			'empty locale defaults to spanish' => ['', 'es-ES'],
			'spanish locale remains spanish' => ['es_ES', 'es-ES'],
			'generic catalan maps to catalan spain' => ['ca', 'ca-ES'],
			'generic basque maps to basque spain' => ['eu', 'eu-ES'],
			'generic galician maps to galician spain' => ['gl', 'gl-ES'],
			'american english maps to supported english locale' => ['en_US', 'en-GB'],
			'unsupported locale falls back to spanish' => ['fr_FR', 'es-ES'],
		];
	}

	public function testResolveWipopCustomerIdFallsBackToParentOrderMeta(): void
	{
		$order = $this->createMock(WC_Order::class);
		$order->expects($this->once())
			->method('get_meta')
			->with('_wipop_customer_id', true)
			->willReturn('')
		;

		$parentOrder = $this->createMock(WC_Order::class);
		$parentOrder->expects($this->once())
			->method('get_meta')
			->with('_wipop_customer_id', true)
			->willReturn('cust_parent_123')
		;

		$result = ChargeRequestFactory::resolveWipopCustomerId($order, 0, $parentOrder);

		$this->assertSame('cust_parent_123', $result);
	}

	public function testResolveWipopCustomerIdPrioritizesCurrentOrderMeta(): void
	{
		$order = $this->createMock(WC_Order::class);
		$order->expects($this->once())
			->method('get_meta')
			->with('_wipop_customer_id', true)
			->willReturn('cust_current_123')
		;

		$parentOrder = $this->createMock(WC_Order::class);
		$parentOrder->expects($this->never())->method('get_meta');

		$result = ChargeRequestFactory::resolveWipopCustomerId($order, 0, $parentOrder);

		$this->assertSame('cust_current_123', $result);
	}
}
