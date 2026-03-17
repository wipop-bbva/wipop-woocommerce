<?php

declare(strict_types=1);

namespace WipopWC\Admin;

use WipopWC\Core\Api\ClientFactory;
use WipopWC\Core\Api\MerchantOperationsService;
use WipopWC\Core\Exception\ApiCallException;
use WipopWC\Core\Exception\ClientConfigurationException;
use WipopWC\Core\Logger;
use WipopWC\Core\WebhookAuth;
use WipopWC\Core\WooCommerce\ManualCaptureManager;

defined('ABSPATH') || exit;

/**
 * Admin settings for Wipop.
 */
class Admin
{
	private const OPTION_NAME = 'wipop_settings';
	private const PAGE_SLUG = 'wipop';
	private const GROUP_SLUG = 'wipop_group';
	private const SECTION_SLUG = 'wipop_main';
	private const WEBHOOK_REGENERATED_QUERY_ARG = 'wipop_webhook_regenerated';

	public function __construct()
	{
		add_action('admin_menu', [$this, 'add_menu']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('admin_post_wipop_verify_credentials', [__CLASS__, 'verify_credentials']);
		add_action(
			'admin_post_wipop_regenerate_webhook_credentials',
			[__CLASS__, 'regenerate_webhook_credentials']
		);
		add_action(
			'update_option_' . self::OPTION_NAME,
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

	public static function regenerate_webhook_credentials(): void
	{
		check_admin_referer('wipop_regenerate_webhook_credentials');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('No tienes permisos suficientes.', 'wipop'));
		}

		$settings = WebhookAuth::getSettings();
		$settings = WebhookAuth::regenerateCredentials($settings);
		update_option(WebhookAuth::SETTINGS_OPTION, $settings);

		Logger::log('Webhook credentials regenerated.', 'info');
		wp_safe_redirect(admin_url('admin.php?page=wipop&wipop_webhook_regenerated=1'));
		exit;
	}

	public function add_menu(): void
	{
		add_submenu_page(
			'woocommerce',
			__('Wipop Settings', 'wipop'),
			'Wipop',
			'manage_options',
			self::PAGE_SLUG,
			[$this, 'settings_page']
		);
	}

	public function register_settings(): void
	{
		register_setting(
			self::GROUP_SLUG,
			self::OPTION_NAME,
			[$this, 'fields_validator']
		);

		add_settings_section(
			self::SECTION_SLUG,
			'',
			'__return_false',
			self::PAGE_SLUG
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
				self::PAGE_SLUG,
				self::SECTION_SLUG,
				['key' => $key, 'field' => $field]
			);
		}
	}

	/**
	 * @param array<string, mixed> $input
	 *
	 * @return array<string, mixed>
	 */
	public function fields_validator(array $input): array
	{
		$old = (array) get_option(self::OPTION_NAME, []);
		$valid = $old;

		foreach ($this->get_fields() as $key => $field) {
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

		$webhookInternalKeys = [
			WebhookAuth::KEY_USERNAME,
			WebhookAuth::KEY_PASSWORD_ENCRYPTED,
			WebhookAuth::KEY_STATE,
			WebhookAuth::KEY_VERIFICATION_CODE,
			WebhookAuth::KEY_VERIFICATION_EVENT_ID,
			WebhookAuth::KEY_VERIFICATION_RECEIVED_AT,
			WebhookAuth::KEY_CONNECTED_AT,
			WebhookAuth::KEY_CREDENTIALS_ROTATED_AT,
		];

		foreach ($webhookInternalKeys as $key) {
			if (!array_key_exists($key, $input)) {
				continue;
			}

			$valid[$key] = sanitize_text_field((string) $input[$key]);
		}

		$valid = WebhookAuth::ensureCredentials($valid);
		$valid[WebhookAuth::KEY_STATE] = WebhookAuth::state($valid);

		return $valid;
	}

	/**
	 * @param mixed $args
	 */
	public function render_field($args)
	{
		$options = (array) get_option(self::OPTION_NAME, []);
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
					esc_attr(self::OPTION_NAME),
					esc_attr($key),
					esc_attr((string) $value),
					esc_attr($field['placeholder'])
				);
				break;
			case 'select':
				printf(
					'<select class="%1$s" name="%2$s[%3$s]">',
					esc_attr($field['class']),
					esc_attr(self::OPTION_NAME),
					esc_attr($key)
				);
				foreach ($field['options'] as $opt_value => $opt_label) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr((string) $opt_value),
						selected($value, $opt_value, false),
						esc_html((string) $opt_label)
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
					esc_attr(self::OPTION_NAME),
					esc_attr((string) $value),
					esc_attr($field['placeholder'])
				);
				printf(
					'<span class="wipop-toggle-password dashicons dashicons-visibility" data-target="%s"></span>',
					esc_attr($key)
				);
				echo '</div>';
				break;
			case 'number':
				$constraints = $this->numberFieldConstraints($field);
				$numberValue = is_numeric($value) ? (int) $value : '';
				printf(
					'<input type="number" class="%1$s" name="%2$s[%3$s]" value="%4$s" placeholder="%5$s" min="%6$u" max="%7$u" step="%8$u" />',
					esc_attr($field['class']),
					esc_attr(self::OPTION_NAME),
					esc_attr($key),
					esc_attr((string) $numberValue),
					esc_attr($field['placeholder']),
					$constraints['min'],
					$constraints['max'],
					$constraints['step']
				);
				break;
		}

		echo '</div>';
	}

	public function settings_page()
	{
		$settings = WebhookAuth::ensureCredentialsStored();
		?>
	<div class="wrap admin-page-wipop-settings">
		<h1><?php esc_html_e('Wipop Settings', 'wipop'); ?></h1>
		<?php if (!empty($_GET[self::WEBHOOK_REGENERATED_QUERY_ARG])) { ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e('Credenciales de webhook regeneradas correctamente.', 'wipop'); ?></p>
			</div>
		<?php } ?>
		<?php settings_errors($this->option_name); ?>
		<form method="post" action="options.php">
			<?php settings_fields($this->group_slug); ?>
			<?php do_settings_sections($this->page_slug); ?>
			<div class="wipop-button-group">
				<button type="submit" class="button button-primary" id="wipop-admin-save-button">
					<?php esc_html_e('Guardar', 'wipop'); ?>
				</button>
				<button type="button" class="button button-secondary" id="wipop-admin-verify-button">
					<?php esc_html_e('Verificar datos', 'wipop'); ?>
				</button>
			</div>
		</form>
		<?php $this->renderWebhookSection($settings); ?>
	</div>
	<?php
	}

	/**
	 * @param mixed $old_value
	 * @param mixed $new_value
	 * @param mixed $option_name
	 */
	public static function log_settings_update($old_value, $new_value, $option_name): void
	{
		Logger::log('Wipop settings updated.', 'info');
	}

	public function enqueue_assets(): void
	{
		if (!is_admin() || !isset($_GET['page']) || $_GET['page'] !== self::PAGE_SLUG) {
			return;
		}

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
			'copySuccessMessage' => __('Copiado al portapapeles.', 'wipop'),
			'copyErrorMessage' => __('No se pudo copiar automáticamente. Copia el valor manualmente.', 'wipop'),
			'manualCopyPrompt' => __('Copia el valor manualmente (Ctrl/Cmd + C)', 'wipop'),
			'regenerateConfirmMessage' => __('Al regenerar credenciales tendrás que actualizar el portal Wipöp. ¿Quieres continuar?', 'wipop'),
		]);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function renderWebhookSection(array $settings): void
	{
		echo '<h2>' . esc_html__('Autenticación del webhook', 'wipop') . '</h2>';
		echo '<table class="form-table wipop-webhook-table" role="presentation">';
		foreach ($this->webhookRows($settings) as $row) {
			$this->renderWebhookRow($row['id'], $row['label'], $row['value'], $row['copyable']);
		}
		echo '</table>';
		$this->renderWebhookRegenerateForm();
	}

	/**
	 * @param array<string, mixed> $settings
	 *
	 * @return array<int, array{id: string, label: string, value: string, copyable: bool}>
	 */
	private function webhookRows(array $settings): array
	{
		return [
			[
				'id' => 'wipop-webhook-url',
				'label' => __('Webhook URL', 'wipop'),
				'value' => WebhookAuth::webhookUrl(),
				'copyable' => true,
			],
			[
				'id' => 'wipop-webhook-username',
				'label' => __('Usuario webhook', 'wipop'),
				'value' => (string) ($settings[WebhookAuth::KEY_USERNAME] ?? ''),
				'copyable' => true,
			],
			[
				'id' => 'wipop-webhook-password',
				'label' => __('Contraseña webhook', 'wipop'),
				'value' => WebhookAuth::decryptedPassword($settings),
				'copyable' => true,
			],
			[
				'id' => 'wipop-webhook-state',
				'label' => __('Estado', 'wipop'),
				'value' => $this->webhookStateLabel(WebhookAuth::state($settings)),
				'copyable' => false,
			],
			[
				'id' => 'wipop-webhook-code',
				'label' => __('Código de verificación', 'wipop'),
				'value' => (string) ($settings[WebhookAuth::KEY_VERIFICATION_CODE] ?? ''),
				'copyable' => true,
			],
			[
				'id' => 'wipop-webhook-received-at',
				'label' => __('Código recibido en', 'wipop'),
				'value' => (string) ($settings[WebhookAuth::KEY_VERIFICATION_RECEIVED_AT] ?? ''),
				'copyable' => false,
			],
			[
				'id' => 'wipop-webhook-connected-at',
				'label' => __('Conectado en', 'wipop'),
				'value' => (string) ($settings[WebhookAuth::KEY_CONNECTED_AT] ?? ''),
				'copyable' => false,
			],
		];
	}

	private function renderWebhookRegenerateForm(): void
	{
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="wipop-webhook-regenerate-form">';
		wp_nonce_field('wipop_regenerate_webhook_credentials');
		echo '<input type="hidden" name="action" value="wipop_regenerate_webhook_credentials" />';
		echo '<button type="submit" class="button button-secondary">' . esc_html__('Regenerar credenciales', 'wipop') . '</button>';
		echo ' <span class="description">'
			. esc_html__('Después de regenerar, actualiza usuario y contraseña en el portal Wipöp.', 'wipop')
			. '</span>';
		echo '</form>';
	}

	private function renderWebhookRow(string $id, string $label, string $value, bool $copyable): void
	{
		$displayValue = $value !== '' ? $value : '-';

		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th>';
		echo '<td><div class="wipop-webhook-row">';
		echo '<input type="text" id="' . esc_attr($id) . '" class="regular-text code wipop-webhook-value" readonly value="' . esc_attr($displayValue) . '" />';

		if ($copyable && $displayValue !== '-') {
			echo '<button type="button" class="button wipop-copy-button" data-wipop-copy-target="'
				. esc_attr($id)
				. '">'
				. esc_html__('Copiar', 'wipop')
				. '</button>';
		}

		echo '</div></td>';
		echo '</tr>';
	}

	private function webhookStateLabel(string $state): string
	{
		return match ($state) {
			WebhookAuth::STATE_CONNECTED => __('Conectado', 'wipop'),
			WebhookAuth::STATE_PENDING_VERIFICATION => __('Sin verificar', 'wipop'),
			default => __('Desconectado', 'wipop'),
		};
	}

	/**
	 * @param array<string, mixed> $field
	 * @param array<string, mixed> $old
	 */
	private function validateNumberField(string $key, array $field, string $value, array $old): string
	{
		$constraints = $this->numberFieldConstraints($field);
		$min = $constraints['min'];
		$max = $constraints['max'];

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
			self::OPTION_NAME,
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
			self::OPTION_NAME,
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

	private function settingsErrorsHtml(): string
	{
		ob_start();
		settings_errors(self::OPTION_NAME);

		return trim((string) ob_get_clean());
	}

	private function renderSuccessNotice(string $message): void
	{
		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html($message)
			. '</p></div>';
	}

	/**
	 * @param array<string, mixed> $field
	 *
	 * @return array{min: int, max: int, step: int}
	 */
	private function numberFieldConstraints(array $field): array
	{
		return [
			'min' => isset($field['min']) ? (int) $field['min'] : 0,
			'max' => isset($field['max']) ? (int) $field['max'] : 99,
			'step' => isset($field['step']) ? (int) $field['step'] : 1,
		];
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function get_fields(): array
	{
		$terminalMin = ClientFactory::getMinTerminalId();
		$terminalMax = ClientFactory::getMaxTerminalId();

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
				'placeholder' => sprintf(
					__('Introduce un número entre %1$d y %2$d', 'wipop'),
					$terminalMin,
					$terminalMax
				),
				'description' => __('Identificador del terminal en Wipop.', 'wipop'),
				'default' => '1',
				'min' => $terminalMin,
				'max' => $terminalMax,
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
