<?php

declare(strict_types=1);

namespace WipopWC\Admin\Product;

use WipopWC\Core\WooCommerce\RecurringPayments;

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
			'id' => RecurringPayments::META_ENABLED,
			'label' => __('Habilitar pago recurrente', 'wipop'),
		]);

		woocommerce_wp_select([
			'id' => RecurringPayments::META_PERIOD,
			'label' => __('Periodicidad', 'wipop'),
			'options' => [
				'' => __('Seleccione una opción...', 'wipop'),
				RecurringPayments::PERIOD_MONTHLY => __('Mensual', 'wipop'),
				RecurringPayments::PERIOD_YEARLY => __('Anual', 'wipop'),
			],
		]);
		echo '</div>';
	}

	public static function saveRecurringFields(int $postId): void
	{
		$rawPeriod = $_POST[RecurringPayments::META_PERIOD] ?? '';
		if (!is_string($rawPeriod)) {
			$rawPeriod = '';
		}

		$period = trim((string) sanitize_text_field($rawPeriod));

		$enabled = !empty($_POST[RecurringPayments::META_ENABLED]) && in_array(
			$period,
			[RecurringPayments::PERIOD_MONTHLY, RecurringPayments::PERIOD_YEARLY],
			true
		)
			? RecurringPayments::META_ENABLED_YES
			: 'no';

		update_post_meta($postId, RecurringPayments::META_ENABLED, $enabled);

		if ($enabled === RecurringPayments::META_ENABLED_YES) {
			update_post_meta($postId, RecurringPayments::META_PERIOD, $period);
		} else {
			delete_post_meta($postId, RecurringPayments::META_PERIOD);
		}
	}
}
