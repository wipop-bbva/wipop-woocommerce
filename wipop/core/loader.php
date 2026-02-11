<?php

declare(strict_types=1);

namespace WipopWC\Core;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Wipop\Charge\ChargeMethod;
use WipopWC\Admin\Product\RecurringPaymentSettings;
use WipopWC\Core\Api\MerchantOperationsService;
use WipopWC\Core\Exception\ApiCallException;
use WipopWC\Core\Exception\ClientConfigurationException;
use WipopWC\Core\WooCommerce\Blocks\BizumPaymentMethod;
use WipopWC\Core\WooCommerce\Blocks\CardPaymentMethod;
use WipopWC\Core\WooCommerce\ManualCaptureManager;
use WipopWC\Core\WooCommerce\RecurringPayments;

defined('ABSPATH') || exit;

/**
 * Loader class responsible for registering payment gateways.
 */
class Loader
{
	protected static array $available_gateways = [];

	public static function init(): void
	{
		add_action('init', [__CLASS__, 'setup_available_gateways'], 5);
		add_action(
			'update_option_woocommerce_payment_gateways',
			[__CLASS__, 'on_list_change'],
			10,
			2
		);

		add_action(
			'update_option_woocommerce_wipop_bizum_gateway_settings',
			[__CLASS__, 'on_settings_change'],
			10,
			3
		);

		add_action(
			'update_option_woocommerce_wipop_card_gateway_settings',
			[__CLASS__, 'on_settings_change'],
			10,
			3
		);

		add_action(
			'update_option_woocommerce_wipop_gpay_gateway_settings',
			[__CLASS__, 'on_settings_change'],
			10,
			3
		);

		require_once WIPOP_PLUGIN_PATH . 'admin/Product/RecurringPaymentSettings.php';
		require_once WIPOP_PLUGIN_PATH . 'core/WooCommerce/RecurringRenewalOrderFactory.php';
		RecurringPaymentSettings::init();
		ManualCaptureManager::init();
		RecurringPayments::init();

		add_action('admin_post_wipop_toggle_bizum', [__CLASS__, 'handle_toggle_bizum']);
		add_action('admin_post_wipop_toggle_card', [__CLASS__, 'handle_toggle_card']);
		add_action('admin_post_wipop_toggle_gpay', [__CLASS__, 'handle_toggle_gpay']);

		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			[__CLASS__, 'register_block_payment_methods'],
			10,
			1
		);
	}

	public static function setup_available_gateways(): void
	{
		$settings = get_option('wipop_settings', []);
		$merchant_id = trim($settings['merchant_id'] ?? '');

		if (empty($merchant_id)) {
			return;
		}

		try {
			self::$available_gateways = MerchantOperationsService::getAvailableGateways();
		} catch (ApiCallException | ClientConfigurationException $exception) {
			Logger::log('Unable to fetch merchant gateways: ' . $exception->getMessage(), 'error');
			self::$available_gateways = [];
		}

		if (empty(self::$available_gateways)) {
			return;
		}

		add_filter('woocommerce_payment_gateways', [__CLASS__, 'register_available_gateways']);
	}

	public static function register_available_gateways(array $gateways): array
	{
		if (in_array(ChargeMethod::CARD, self::$available_gateways, true)) {
			require_once WIPOP_PLUGIN_PATH . 'gateways/card/card.php';
			$gateways[] = 'WipopWC\Gateways\Card\Gateway';
		}
		if (in_array(ChargeMethod::BIZUM, self::$available_gateways, true)) {
			require_once WIPOP_PLUGIN_PATH . 'gateways/bizum/bizum.php';
			$gateways[] = 'WipopWC\Gateways\Bizum\Gateway';
		}
		if (in_array(ChargeMethod::GOOGLE_PAY, self::$available_gateways, true)) {
			require_once WIPOP_PLUGIN_PATH . 'gateways/googlepay/gpay.php';
			$gateways[] = 'WipopWC\Gateways\Googlepay\Gateway';
		}

		return $gateways;
	}

	public static function on_list_change($old, $new): void
	{
		$oldList = (array) $old;
		$newList = (array) $new;

		foreach (array_diff($newList, $oldList) as $id) {
			// @phpstan-ignore-next-line
			Logger::log("Gateway {$id} activated", 'info');
		}
		foreach (array_diff($oldList, $newList) as $id) {
			// @phpstan-ignore-next-line
			Logger::log("Gateway {$id} deactivated", 'info');
		}
	}

	public static function on_settings_change($old, $new, $option_name)
	{
		// @phpstan-ignore-next-line
		$wasOn = !empty($old['enabled']) && $old['enabled'] === 'yes';
		// @phpstan-ignore-next-line
		$isOn = !empty($new['enabled']) && $new['enabled'] === 'yes';

		if ($wasOn === $isOn) {
			return;
		}

		$gateway_id = str_replace('_settings', '', $option_name);
		$label = ucwords(str_replace(
			['wipop_', '_gateway', '_'],
			['', '', ' '],
			$gateway_id
		));
		$action = $isOn ? 'activated' : 'deactivated';

		Logger::log("Gateway {$label} {$action}", 'info');
	}

	public static function enqueue_admin_assets($hook): void
	{
		wp_enqueue_script(
			'wipop-recurring-settings',
			plugin_dir_url(WIPOP_PLUGIN_FILE) . 'assets/js/recurring-settings.js',
			['jquery'],
			filemtime(WIPOP_PLUGIN_PATH . 'assets/js/recurring-settings.js'),
			true
		);

		if ($hook !== 'woocommerce_page_wc-settings') {
			return;
		}

		if (!isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
			return;
		}
		wp_enqueue_script(
			'wipop-confirm-modal',
			plugin_dir_url(WIPOP_PLUGIN_FILE) . 'assets/js/confirm-modal.js',
			[],
			filemtime(WIPOP_PLUGIN_PATH . 'assets/js/confirm-modal.js'),
			true
		);

		wp_enqueue_script(
			'wipop-admin-toggle',
			plugin_dir_url(WIPOP_PLUGIN_FILE) . 'assets/js/toggle-btn-gateways.js',
			['wipop-confirm-modal', 'wp-i18n'],
			filemtime(WIPOP_PLUGIN_PATH . 'assets/js/toggle-btn-gateways.js'),
			true
		);

		wp_localize_script('wipop-admin-toggle', 'wipopToggle', [
			'nonce' => wp_create_nonce('wipop_toggle_button'),
			'modalTemplateUrl' => plugin_dir_url(WIPOP_PLUGIN_FILE) . 'assets/templates/confirmation-modal.html',
			'i18n' => [
				'activate' => __('Activar', 'wipop'),
				'deactivate' => __('Desactivar', 'wipop'),
				'confirm_deactivate' => __('¿Estás seguro de que quieres desactivar %s?', 'wipop'),
				'default_label' => __('este método de pago', 'wipop'),
				'confirm' => __('Confirmar', 'wipop'),
				'cancel' => __('Cancelar', 'wipop'),
				'error_message' => __('Ups… no pudimos mostrar la ventana de confirmación. Puedes desactivar la pasarela desde el botón ⋮', 'wipop'),
			],
		]);

		wp_enqueue_style(
			'wipop-admin-style',
			plugin_dir_url(WIPOP_PLUGIN_FILE) . 'assets/css/admin-gateways.css',
			[],
			filemtime(WIPOP_PLUGIN_PATH . 'assets/css/admin-gateways.css')
		);

		wp_enqueue_style(
			'wipop-modal-style',
			plugin_dir_url(WIPOP_PLUGIN_FILE) . 'assets/css/confirmation-modal.css',
			[],
			filemtime(WIPOP_PLUGIN_PATH . 'assets/css/confirmation-modal.css')
		);
	}

	public static function register_block_payment_methods($payment_method_registry): void
	{
		if (!$payment_method_registry instanceof PaymentMethodRegistry) {
			return;
		}

		$payment_method_registry->register(new CardPaymentMethod());
		$payment_method_registry->register(new BizumPaymentMethod());
	}

	public static function handle_toggle_bizum(): void
	{
		self::toggle_gateway('woocommerce_wipop_bizum_gateway_settings', 'Bizum');
	}

	public static function handle_toggle_card(): void
	{
		self::toggle_gateway('woocommerce_wipop_card_gateway_settings', 'Card');
	}

	public static function handle_toggle_gpay(): void
	{
		self::toggle_gateway('woocommerce_wipop_gpay_gateway_settings', 'GPay');
	}

	protected static function toggle_gateway(string $option_key, string $label): void
	{
		check_admin_referer('wipop_toggle_button');

		$action = ($_GET['wipop_gateway_action'] ?? '') === 'on' ? 'yes' : 'no';

		$settings = get_option($option_key, []);
		$settings['enabled'] = $action;

		update_option($option_key, $settings);

		$estado = $action === 'yes' ? 'activado' : 'desactivado';
		Logger::log("[{$label}] {$estado} via botón", 'info');

		wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout'));
		exit;
	}
}
