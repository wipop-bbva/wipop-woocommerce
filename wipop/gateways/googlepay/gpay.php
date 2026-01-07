<?php

declare(strict_types=1);

namespace WipopWC\Gateways\Googlepay;

use WC_Payment_Gateway;
use WipopWC\Core\Logger;

use function is_admin;
use function is_checkout;

defined('ABSPATH') || exit;

class Gateway extends WC_Payment_Gateway
{
	public const ID = 'wipop_gpay_gateway';

	public function __construct()
	{
		$this->id = self::ID;
		$this->method_title = __('GPay', 'wipop');
		$this->method_description = __('Paga con GPay', 'wipop');

		$this->icon = plugins_url(
			'gateways/googlepay/assets/img/google-svgrepo-com.svg',
			WIPOP_PLUGIN_FILE
		);
		$this->init_form_fields();
		$this->init_settings();

		$this->enabled = $this->get_option('enabled', 'no');
		$this->title = __('Google Pay (BBVA)', 'wipop');
		$this->description = '';

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

		$icon_url = plugins_url(
			'gateways/googlepay/assets/img/google-svgrepo-com.svg',
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

	/**
	 * TODO
	 *
	 * @param mixed $order_id
	 */
	public function process_payment($order_id)
	{
		Logger::log('Processing GPay payment for order ' . $order_id);

		return ['result' => 'success'];
	}
}
