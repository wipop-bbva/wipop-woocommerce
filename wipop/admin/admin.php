<?php

namespace WipopWC\Admin;

use WipopWC\Core\Api\MerchantOperationsService;
use WipopWC\Core\Exception\ApiCallException;
use WipopWC\Core\Exception\ClientConfigurationException;
use WipopWC\Core\Logger;
use WipopWC\Core\WooCommerce\ManualCaptureManager;

defined('ABSPATH') || exit;

/**
 * Admin settings for Wipop.
 */
class Admin
{
	/**
	 * Option name used to store settings.
	 *
	 * @var string
	 */
	private $option_name = 'wipop_settings';

	/**
	 * Page slug for settings page.
	 *
	 * @var string
	 */
	private $page_slug = 'wipop';

	/**
	 * Settings group slug.
	 *
	 * @var string
	 */
	private $group_slug = 'wipop_group';

	/**
	 * Settings section slug.
	 *
	 * @var string
	 */
	private $section_slug = 'wipop_main';

	public function __construct()
	{
		add_action('admin_menu', [$this, 'add_menu']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('admin_post_wipop_verify_credentials', [__CLASS__, 'verify_credentials']);
		add_action(
			'update_option_' . $this->option_name,
			[__CLASS__, 'log_settings_update'],
			10,
			3
		);
	}

	public static function verify_credentials(): void
	{
		check_admin_referer('wipop_verify_credentials');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('No tienes permisos suficientes.', 'wipop')], 403);
		}

		try {
			$gateways = MerchantOperationsService::getAvailableGateways(true);

			wp_send_json_success([
				'gateways' => $gateways,
				'message' => __('Credenciales verificadas correctamente.', 'wipop'),
			]);
		} catch (ApiCallException | ClientConfigurationException $exception) {
			wp_send_json_error([
				'message' => $exception->getMessage(),
			]);
		}
	}

	public function add_menu(): void
	{
		add_submenu_page(
			'woocommerce',
			__('Wipop Settings', 'wipop'),
			'Wipop',
			'manage_options',
			$this->page_slug,
			[$this, 'settings_page']
		);
	}

	public function register_settings(): void
	{
		register_setting(
			$this->group_slug,
			$this->option_name,
			[$this, 'fields_validator']
		);

		add_settings_section(
			$this->section_slug,
			'',
			'__return_false',
			$this->page_slug
		);

		foreach ($this->get_fields() as $key => $field) {
			$title = esc_html($field['title']);

			if (!empty($field['description'])) {
				$title .= sprintf(
					' <span class="wipop-inline-tooltip help-tip" data-tip="%s" tabindex="0">'
					. '<span class="dashicons dashicons-editor-help"></span>'
					. '</span>',
					esc_attr($field['description'])
				);
			}

			add_settings_field(
				$key,
				$title,
				[$this, 'render_field'],
				$this->page_slug,
				$this->section_slug,
				['key' => $key, 'field' => $field]
			);
		}
	}

	public function fields_validator(array $input): array
	{
		$valid = [];
		$old = (array) get_option($this->option_name, []);

		foreach ($this->get_fields() as $key => $field) {
			// @phpstan-ignore-next-line
			$value = trim((string) ($input[$key] ?? ''));

			switch ($field['type']) {
				case 'number':
					$value = $this->validateNumberField($key, $field, $value, $old);
					break;
				case 'text':
					$value = $this->validateTextField($key, $field, $value, $old);
					break;
				case 'select':
					$value = $this->validateSelectField($key, $field, $value, $old);
					break;
				default:
					if ($value === '') {
						$value = (string) ($old[$key] ?? $field['default']);
					}
					break;
			}

			$valid[$key] = sanitize_text_field($value);
		}

		return $valid;
	}

	/**
	 * @param mixed $args
	 */
	public function render_field($args)
	{
		$options = (array) get_option($this->option_name, []);
		$key = $args['key'];
		$field = $args['field'];
		$value = $options[$key] ?? $field['default'];

		echo '<div class="wipop-field-wrapper">';

		if (!empty($field['description'])) {
			printf(
				'<span class="wipop-tooltip help-tip" data-tip="%s" tabindex="0">'
				. '<span class="dashicons dashicons-editor-help"></span>'
				. '</span>',
				esc_attr($field['description'])
			);
		}

		switch ($field['type']) {
			case 'text':
				printf(
					'<input type="text" class="%1$s" name="%2$s[%3$s]" value="%4$s" placeholder="%5$s" />',
					esc_attr($field['class']),
					esc_attr($this->option_name),
					esc_attr($key),
					esc_attr($value),
					esc_attr($field['placeholder'])
				);
				break;
			case 'select':
				printf(
					'<select class="%1$s" name="%2$s[%3$s]">',
					esc_attr($field['class']),
					esc_attr($this->option_name),
					esc_attr($key)
				);
				foreach ($field['options'] as $opt_value => $opt_label) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr($opt_value),
						selected($value, $opt_value, false),
						esc_html($opt_label)
					);
				}
				echo '</select>';
				break;
			case 'password':
				echo '<div class="wipop-password-inner">';
				printf(
					'<input type="password" id="%1$s" class="%2$s wipop-password-field" name="%3$s[%1$s]" value="%4$s" placeholder="%5$s" />',
					esc_attr($key),
					esc_attr($field['class']),
					esc_attr($this->option_name),
					esc_attr($value),
					esc_attr($field['placeholder'])
				);
				printf(
					'<span class="wipop-toggle-password dashicons dashicons-visibility" data-target="%s"></span>',
					esc_attr($key)
				);
				echo '</div>';
				break;
			case 'number':
				$min = isset($field['min']) ? (int) $field['min'] : 0;
				$max = isset($field['max']) ? (int) $field['max'] : 99;
				$step = isset($field['step']) ? (int) $field['step'] : 1;
				$value = is_numeric($value) ? (int) $value : '';
				printf(
					'<input type="number" class="%1$s" name="%2$s[%3$s]" value="%4$u" placeholder="%5$s" min="%6$u" max="%7$u" step="%8$u" />',
					esc_attr($field['class']),
					esc_attr($this->option_name),
					esc_attr($key),
					$value,
					esc_attr($field['placeholder']),
					$min,
					$max,
					$step
				);
				break;
		}
		echo '</div>';
	}

	public function settings_page()
	{
		?>
	<div class="wrap admin-page-wipop-settings">
		<h1><?php esc_html_e('Wipop Settings', 'wipop'); ?></h1>
		<?php settings_errors($this->option_name); ?>
		<form method="post" action="options.php">
			<?php settings_fields($this->group_slug); ?>
			<?php do_settings_sections($this->page_slug); ?>
			<div class="wipop-button-group">
				<button type="submit" class="button button-primary" id="wipop-admin-save-button" disabled>
					<?php esc_html_e('Guardar', 'wipop'); ?>
				</button>
				<button type="button" class="button button-secondary" id="wipop-admin-verify-button">
					<?php esc_html_e('Verificar datos', 'wipop'); ?>
				</button>
			</div>
		</form>
	</div>
	<?php
	}

	public static function log_settings_update($old_value, $new_value, $option_name)
	{
		Logger::log('Wipop settings updated.', 'info');
	}

	public function enqueue_assets(): void
	{
		if (is_admin() && isset($_GET['page']) && $_GET['page'] === $this->page_slug) {
			$css_path = plugin_dir_path(__FILE__) . '../assets/css/admin-settings-menu.css';

			wp_enqueue_style(
				'wipop-admin-style',
				plugin_dir_url(__FILE__) . '../assets/css/admin-settings-menu.css',
				[],
				filemtime($css_path)
			);

			$js_path = plugin_dir_path(__FILE__) . '../assets/js/admin-settings-menu.js';

			wp_enqueue_script(
				'admin-settings-menu',
				plugin_dir_url(__FILE__) . '../assets/js/admin-settings-menu.js',
				['jquery'],
				filemtime($js_path),
				true
			);

			wp_localize_script('admin-settings-menu', 'wipopAdminVerify', [
				'ajaxUrl' => admin_url('admin-post.php'),
				'nonce' => wp_create_nonce('wipop_verify_credentials'),
				'successMessage' => __('Tus credenciales son válidas.', 'wipop'),
				'errorMessage' => __('No pudimos verificar las credenciales. Revisa los datos e inténtalo de nuevo.', 'wipop'),
			]);
		}
	}

	/**
	 * @param array<string, mixed> $field
	 * @param array<string, mixed> $old
	 */
	private function validateNumberField(string $key, array $field, string $value, array $old): string
	{
		$min = isset($field['min']) ? (int) $field['min'] : 0;
		$max = isset($field['max']) ? (int) $field['max'] : 99;

		if (!is_numeric($value)) {
			$this->addNumberFieldError($key, $field, $min, $max);

			return (string) ($old[$key] ?? $field['default']);
		}

		$number = (int) $value;
		if ($number < $min || $number > $max) {
			$this->addNumberFieldError($key, $field, $min, $max);

			return (string) ($old[$key] ?? $field['default']);
		}

		return (string) $number;
	}

	/**
	 * @param array<string, mixed> $field
	 * @param array<string, mixed> $old
	 */
	private function validateTextField(string $key, array $field, string $value, array $old): string
	{
		if ($value === '') {
			return (string) ($old[$key] ?? '');
		}

		if (strlen($value) >= 5) {
			return $value;
		}

		add_settings_error(
			$this->option_name,
			$key,
			sprintf(
				__('%s must have at least 5 characters.', 'wipop'),
				$field['title']
			),
			'error'
		);

		return (string) ($old[$key] ?? '');
	}

	/**
	 * @param array<string, mixed> $field
	 * @param array<string, mixed> $old
	 */
	private function validateSelectField(string $key, array $field, string $value, array $old): string
	{
		if ($value !== '') {
			return $value;
		}

		return (string) ($old[$key] ?? $field['default'] ?? '');
	}

	/**
	 * @param array<string, mixed> $field
	 */
	private function addNumberFieldError(string $key, array $field, int $min, int $max): void
	{
		add_settings_error(
			$this->option_name,
			$key,
			sprintf(
				__('%1$s must be a number between %2$d and %3$d.', 'wipop'),
				$field['title'],
				$min,
				$max
			),
			'error'
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function get_fields(): array
	{
		return [
			'merchant_id' => [
				'title' => __('Merchant ID', 'wipop'),
				'type' => 'text',
				'class' => 'wipop-merchant-id',
				'placeholder' => __('Tu Merchant ID', 'wipop'),
				'description' => __('Introduce tu Merchant ID del BBVA.', 'wipop'),
				'default' => '',
			],
			'environment' => [
				'title' => __('Entorno', 'wipop'),
				'type' => 'select',
				'class' => 'wipop-environment',
				'options' => [
					'sandbox' => __('Sandbox', 'wipop'),
					'production' => __('Producción', 'wipop'),
				],
				'description' => __('Elige el entorno de pagos.', 'wipop'),
				'default' => 'sandbox',
			],
			'manual_capture_mode' => [
				'title' => __('Preautorizaciones', 'wipop'),
				'type' => 'select',
				'class' => 'wipop-manual-capture',
				'options' => [
					ManualCaptureManager::CAPTURE_MODE_AUTO => __('Cobrar automáticamente', 'wipop'),
					ManualCaptureManager::CAPTURE_MODE_MANUAL => __('Solo preautorizar y capturar manualmente', 'wipop'),
				],
				'description' => __(
					'Si eliges preautorizar, tendrás que capturar o anular cada pago desde el pedido antes de una semana. Aplica a tarjetas.',
					'wipop'
				),
				'default' => ManualCaptureManager::CAPTURE_MODE_AUTO,
			],
			'terminal_id' => [
				'title' => __('Terminal ID', 'wipop'),
				'type' => 'number',
				'class' => 'wipop-terminal-id',
				'placeholder' => __('Introduce un número entre 0 y 99', 'wipop'),
				'description' => __('Identificador del terminal en Wipop.', 'wipop'),
				'default' => '1',
				'min' => 0,
				'max' => 99,
				'step' => 1,
			],
			'public_key' => [
				'title' => __('Public Key', 'wipop'),
				'type' => 'password',
				'class' => 'wipop-public-key',
				'placeholder' => __('Tu Clave Pública', 'wipop'),
				'description' => __('Introduce tu Public Key del BBVA.', 'wipop'),
				'default' => '',
			],
			'private_key' => [
				'title' => __('Private Key', 'wipop'),
				'type' => 'password',
				'class' => 'wipop-private-key',
				'placeholder' => __('Tu Clave Privada', 'wipop'),
				'description' => __('Introduce tu Private Key del BBVA.', 'wipop'),
				'default' => '',
			],
		];
	}
}
