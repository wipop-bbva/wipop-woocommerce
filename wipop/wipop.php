<?php

declare(strict_types=1);

use WipopWC\Admin\Admin;
use WipopWC\Core\Loader;
use WipopWC\Core\Logger;
use WipopWC\Core\Webhook;

/*
 * Plugin Name: Wipop
 * Description: Plataforma de pagos de BBVA en España para pymes y autónomos.
 * Version: 0.9.4
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Wipöp by BBVA
 * Text Domain: wipop
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

if (!defined('WIPOP_PLUGIN_FILE')) {
	define('WIPOP_PLUGIN_FILE', __FILE__);
	define('WIPOP_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
/**
 * Load Wipop php library from file
 */
$wipop_composer_autoload = WIPOP_PLUGIN_PATH . 'vendor/autoload.php';
if (is_readable($wipop_composer_autoload)) {
	require_once $wipop_composer_autoload;
}

require_once WIPOP_PLUGIN_PATH . 'core/logger.php';
require_once WIPOP_PLUGIN_PATH . 'core/loader.php';
require_once WIPOP_PLUGIN_PATH . 'core/webhook-auth.php';
require_once WIPOP_PLUGIN_PATH . 'core/webhook.php';
require_once WIPOP_PLUGIN_PATH . 'admin/admin.php';

function wipop_missing_wc_notice(): void
{
	echo '<div class="error"><p><strong>' . esc_html__('Wipop requires WooCommerce to be installed and active.', 'wipop') . '</strong></p></div>';
}

/**
 * Initialize the plugin.
 */
function wipop_init(): void
{
	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', 'wipop_missing_wc_notice');

		return;
	}

	Loader::init();
	new Admin();
	Webhook::init();
}

add_action('plugins_loaded', 'wipop_init');

function wipop_activate(): void
{
	Logger::log('Wipop plugin activated', 'info');
}

register_activation_hook(WIPOP_PLUGIN_FILE, 'wipop_activate');

/**
 * Enqueue gateway‐specific styles (only on checkout).
 */
function wipop_enqueue_gateway_styles(): void
{
	if (function_exists('is_checkout') && is_checkout()) {
		$css_path = plugin_dir_path(WIPOP_PLUGIN_FILE) . 'assets/css/gateways.css';
		$css_url = plugins_url('assets/css/gateways.css', WIPOP_PLUGIN_FILE);

		wp_enqueue_style(
			'wipop-gateways',
			$css_url,
			[],
			filemtime($css_path)
		);
	}
}
add_action('wp_enqueue_scripts', 'wipop_enqueue_gateway_styles');

function wipop_checkout_secure_notice(): void
{
	$lock_svg = plugins_url('assets/img/lock-filled-svgrepo-com.svg', WIPOP_PLUGIN_FILE);

	echo '<div class="checkout-security-message">';
	echo '  <img src="' . esc_url($lock_svg) . '" alt="Secure payment icon" class="checkout-lock-icon" />';
	echo '  <span class="checkout-security-text">Secured by Wipöp - BBVA</span>';
	echo '</div>';
}
add_action('woocommerce_review_order_after_submit', 'wipop_checkout_secure_notice');

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wipop_add_settings_link');

/**
 * @param array<int, string> $links
 *
 * @return array<int, string>
 */
function wipop_add_settings_link(array $links): array
{
	$settings_url = admin_url('admin.php?page=wipop');
	$settings_link = sprintf('<a href="%s">%s</a>', $settings_url, __('Ajustes', 'wipop'));
	array_unshift($links, $settings_link);

	return $links;
}
