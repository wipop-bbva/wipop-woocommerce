<?php

declare(strict_types=1);

namespace Wipop\Gateways\Bizum;

use WC_Payment_Gateway;
use Wipop\Charge\ChargeMethod;
use Wipop\Core\Logger;
use Wipop\Gateways\Support\PaymentsProcessor;

defined('ABSPATH') || exit;

class Gateway extends WC_Payment_Gateway
{
	use PaymentsProcessor;

	public const ID = 'wipop_bizum_gateway';

	public function __construct()
	{
		$this->id = self::ID;
		$this->method_title = __('Bizum', 'wipop');
		$this->method_description = __('Paga con Bizum', 'wipop');

		$this->icon = plugins_url(
			'gateways/bizum/assets/img/cellphone-svgrepo-com.svg',
			WIPOP_PLUGIN_FILE
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled = $this->get_option('enabled');
		$this->title = $this->get_option('title');
		$this->description = '';

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
		add_filter(
			'woocommerce_gateway_icon',
			[$this, 'filter_gateway_icon'],
			10,
			2
		);

		add_filter(
			'woocommerce_gateway_title',
			[$this, 'prepend_icon_to_title'],
			10,
			2
		);
	}

	public function init_form_fields()
	{
		$this->form_fields = [
			'enabled' => [
				'title' => __('Enable Bizum', 'wipop'),
				'type' => 'checkbox',
				'label' => __('Enable Bizum payments', 'wipop'),
				'default' => 'no',
			],
			'title' => [
				'title' => __('Checkout title', 'wipop'),
				'type' => 'text',
				'description' => __('Title that the customer will see at payment.', 'wipop'),
				'default' => __('Pay with Bizum', 'wipop'),
				'desc_tip' => true,
			],
		];
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

		$icon_url = plugins_url(
			'gateways/bizum/assets/img/cellphone-svgrepo-com.svg',
			WIPOP_PLUGIN_FILE
		);

		$html = sprintf(
			'<label for="payment_method_%1$s" class="wipop-gateway-label">
              <input id="payment_method_%1$s" class="input-radio" name="payment_method" type="radio" value="%1$s" />
              <img src="%2$s" alt="%3$s" class="wipop-gateway-icon" />
              <div class="wipop-gateway-text">
                  <span class="wipop-title-text">%4$s</span>
                  <small class="wipop-subtext">Wipop by BBVA</small>
              </div>
            </label>',
			esc_attr($this->id),
			esc_url($icon_url),
			esc_attr($this->method_title),
			esc_html($title)
		);

		return wp_kses_post($html);
	}

	public function process_payment($order_id)
	{
		Logger::log('Processing Bizum payment for order ' . $order_id);

		return $this->processGatewayPayment($order_id, ChargeMethod::BIZUM);
	}
}
