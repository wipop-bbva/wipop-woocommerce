<?php

namespace Wipop\Admin;

use Wipop\Core\Logger;

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
		add_action(
			'update_option_' . $this->option_name,
			[__CLASS__, 'log_settings_update'],
			10,
			3
		);
	}

	public function add_menu()
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

	public function register_settings()
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

	public function fields_validator($input)
	{
		$valid = [];
		$old = (array) get_option($this->option_name, []);

		foreach ($this->get_fields() as $key => $field) {
			$value = trim((string) ($input[$key] ?? ''));

			if ($field['type'] === 'text' && strlen($value) < 6) {
				add_settings_error(
					$this->option_name,
					$key,
					sprintf(
						__('%s must have at least 6 characters.', 'wipop'),
						$field['title']
					),
					'error'
				);
				$value = $old[$key] ?? '';
			}

			$valid[$key] = sanitize_text_field($value);
		}

		return $valid;
	}

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

	public function enqueue_assets()
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
				[],
				filemtime($js_path),
				true
			);
		}
	}

	private function get_fields()
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
