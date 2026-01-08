<?php

declare(strict_types=1);

namespace WipopWC\Core\WooCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

use function file_exists;
use function get_option;
use function plugin_dir_url;
use function plugins_url;
use function wp_register_script;
use function wp_script_is;

abstract class AbstractBlockPaymentMethod extends AbstractPaymentMethodType
{
	protected string $settings_option = '';
	protected string $default_title = '';
	protected string $description = '';
	protected string $icon_relative_path = '';
	protected array $supported_features = [];
	protected array $supported_flags = [];

	public function __construct(
		string $settings_option,
		string $default_title,
		string $description,
		string $icon_relative_path,
		array $supported_features = [],
		array $supported_flags = []
	) {
		$this->settings_option = $settings_option;
		$this->default_title = $default_title;
		$this->description = $description;
		$this->icon_relative_path = $icon_relative_path;
		$this->supported_features = $supported_features;
		$this->supported_flags = $supported_flags;
	}

	public function initialize()
	{
		$this->settings = (array) get_option($this->settings_option, []);
	}

	public function is_active()
	{
		return !empty($this->settings['enabled']) && $this->settings['enabled'] === 'yes';
	}

	public function get_payment_method_script_handles()
	{
		$handle = 'wipop-blocks-payment-methods';
		$script_path = WIPOP_PLUGIN_PATH . 'build/blocks-payment-methods.js';
		$asset_path = WIPOP_PLUGIN_PATH . 'build/blocks-payment-methods.asset.php';

		if (!wp_script_is($handle, 'registered') && file_exists($script_path) && file_exists($asset_path)) {
			/** @var array{dependencies: array<int, string>, version: string} $asset */
			$asset = require $asset_path;

			wp_register_script(
				$handle,
				plugin_dir_url(WIPOP_PLUGIN_FILE) . 'build/blocks-payment-methods.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);
		}

		return [$handle];
	}

	public function get_payment_method_script_handles_for_admin()
	{
		return ['wipop-blocks-payment-methods'];
	}

	public function get_supported_features()
	{
		return $this->supported_features;
	}

	public function get_payment_method_data()
	{
		return [
			'title' => $this->get_setting('title', $this->default_title),
			'description' => $this->description,
			'icon' => plugins_url($this->icon_relative_path, WIPOP_PLUGIN_FILE),
			'supports' => $this->supported_features,
			'supportsFlags' => $this->supported_flags,
			'is_active' => (bool) $this->is_active(),
		];
	}
}
