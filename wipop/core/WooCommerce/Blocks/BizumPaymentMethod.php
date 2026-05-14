<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce\Blocks;

use WipopWC\Core\WooCommerce\RecurringPayments;
use WipopWC\Gateways\Bizum\Gateway;

use function __;

defined('ABSPATH') || exit;

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

	public function is_active()
	{
		return parent::is_active() && !RecurringPayments::cartContainsRecurringProduct();
	}
}
