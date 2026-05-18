<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce\Blocks;

use WipopWC\Gateways\Card\Gateway;

use function __;

defined('ABSPATH') || exit;

class CardPaymentMethod extends AbstractBlockPaymentMethod
{
	protected $name = Gateway::ID;

	public function __construct()
	{
		parent::__construct(
			'woocommerce_' . Gateway::ID . '_settings',
			__('Card (BBVA)', 'wipop'),
			__('Paga con Card', 'wipop'),
			'gateways/card/assets/img/credit-card-svgrepo-com.svg',
			['products', 'tokenization'],
			[
				'showSaveOption' => true,
				'showSavedCards' => true,
			]
		);
	}
}
