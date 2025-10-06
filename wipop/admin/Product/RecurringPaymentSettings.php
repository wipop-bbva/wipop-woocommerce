<?php

declare(strict_types=1);

namespace Wipop\Admin\Product;

defined('ABSPATH') || exit;

class RecurringPaymentSettings
{
	public static function init(): void
	{
		add_filter('woocommerce_product_data_tabs', [__CLASS__, 'addRecurringTab'], 21);
		add_action('woocommerce_product_data_panels', [__CLASS__, 'outputRecurringPanel']);
		add_action('woocommerce_process_product_meta', [__CLASS__, 'saveRecurringFields'], 10, 1);
	}

	/**
	 * @param array<string, array<string, mixed>> $tabs
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function addRecurringTab(array $tabs): array
	{
		$tabs['wipop_recurring'] = [
			'label' => __('Pago recurrente', 'wipop'),
			'target' => 'wipop_recurring_options',
			'class' => ['show_if_simple', 'show_if_variable'],
			'priority' => 21,
		];

		return $tabs;
	}

	public static function outputRecurringPanel(): void
	{
		echo '<div id="wipop_recurring_options" class="panel woocommerce_options_panel">';
		woocommerce_wp_checkbox([
			'id' => '_wipop_recurring_enabled',
			'label' => __('Habilitar pago recurrente', 'wipop'),
		]);

		woocommerce_wp_select([
			'id' => '_wipop_recurring_period',
			'label' => __('Periodicidad', 'wipop'),
			'options' => [
				'' => __('Seleccione una opción...', 'wipop'),
				'monthly' => __('Mensual', 'wipop'),
				'yearly' => __('Anual', 'wipop'),
			],
		]);
		echo '</div>';
	}

	public static function saveRecurringFields(int $postId): void
	{
		$rawPeriod = $_POST['_wipop_recurring_period'] ?? '';
		if (!is_string($rawPeriod)) {
			$rawPeriod = '';
		}

		$period = trim((string) sanitize_text_field($rawPeriod));

		$enabled = !empty($_POST['_wipop_recurring_enabled']) && in_array($period, ['monthly', 'yearly'], true)
			? 'yes'
			: 'no';

		update_post_meta($postId, '_wipop_recurring_enabled', $enabled);

		if ($enabled === 'yes') {
			update_post_meta($postId, '_wipop_recurring_period', $period);
		} else {
			delete_post_meta($postId, '_wipop_recurring_period');
		}
	}
}
