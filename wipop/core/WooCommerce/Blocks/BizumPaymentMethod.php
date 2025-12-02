<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce\Blocks;

use WipopWC\Gateways\Bizum\Gateway;

use function __;

class BizumPaymentMethod extends AbstractBlockPaymentMethod
{
	protected $name = Gateway::ID;

	public function __construct()
	{
		parent::__construct(
			'woocommerce_' . Gateway::ID . '_settings',
			__('Bizum (BBVA)', 'wipop'),
			__('Paga con Bizum', 'wipop'),
			'gateways/bizum/assets/img/cellphone-svgrepo-com.svg'
		);
	}
}
