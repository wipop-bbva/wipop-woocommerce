<?php

declare(strict_types=1);

namespace WipopWC\Gateways\Card;

use WC_Payment_Gateway;
use Wipop\Domain\ChargeMethod;
use WipopWC\Core\Logger;
use WipopWC\Gateways\Support\PaymentsProcessor;

use function is_admin;
use function is_checkout;
use function is_user_logged_in;

defined('ABSPATH') || exit;

class Gateway extends WC_Payment_Gateway
{
	use PaymentsProcessor;

	public const ID = 'wipop_card_gateway';

	public function __construct()
	{
		$this->id = self::ID;
		$this->method_title = __('Card', 'wipop');
		$this->method_description = __('Paga con Card', 'wipop');
		$this->supports = array_unique(array_merge($this->supports, ['tokenization', 'refunds']));
		$this->has_fields = true;

		$this->icon = plugins_url(
			'gateways/card/assets/img/credit-card-svgrepo-com.svg',
			WIPOP_PLUGIN_FILE
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled = $this->get_option('enabled', 'no');
		$this->title = __('Card (BBVA)', 'wipop');
		$this->description = '';

		add_filter(
			'woocommerce_gateway_title',
			[$this, 'prepend_icon_to_title'],
			10,
			2
		);

		add_filter(
			'woocommerce_gateway_icon',
			[$this, 'filter_gateway_icon'],
			10,
			2
		);
	}

	public function init_form_fields()
	{
		$this->form_fields = [];
	}

	public function admin_options()
	{
		echo '<h2>' . esc_html($this->get_method_title()) . '</h2>';
		echo '<p>' . esc_html__('Gestiona este método desde WooCommerce > Wipop.', 'wipop') . '</p>';
	}

	public function filter_gateway_icon($icon, $gateway_id)
	{
		if ($gateway_id === $this->id && !is_admin()) {
			return '';
		}

		return $icon;
	}

	public function prepend_icon_to_title($title, $gateway_id)
	{
		if ($gateway_id !== $this->id || 'yes' !== $this->enabled) {
			return $title;
		}

		// Only render the custom label on checkout
		if (is_admin() || !is_checkout()) {
			return $title;
		}

		$html = sprintf(
			'<span class="wipop-gateway-label">
              <img src="%1$s" alt="%2$s" class="wipop-gateway-icon" />
              <span class="wipop-gateway-text">
                  <span class="wipop-title-text">%3$s</span>
                  <small class="wipop-subtext">Wipop by BBVA</small>
              </span>
            </span>',
			esc_url($this->icon),
			esc_attr($this->method_title),
			esc_html($title)
		);

		return wp_kses_post($html);
	}

	public function process_payment($order_id)
	{
		Logger::log('Processing Card payment for order ' . $order_id);

		return $this->processGatewayPayment($order_id, ChargeMethod::CARD);
	}

	public function payment_fields()
	{
		if (!is_user_logged_in()) {
			return;
		}

		$this->saved_payment_methods();
		$this->save_payment_method_checkbox();
	}

	public function process_refund($order_id, $amount = null, $reason = '')
	{
		Logger::log('Processing Card refund for order ' . $order_id);
		$numericAmount = is_numeric($amount) ? (float) $amount : null;

		return $this->processGatewayRefund($order_id, $numericAmount, $reason);
	}
}
