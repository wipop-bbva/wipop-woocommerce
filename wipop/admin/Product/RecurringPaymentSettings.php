<?php

namespace Wipop\Admin\Product;

defined('ABSPATH') || exit;

class RecurringPaymentSettings {
    public static function init() {
        add_filter('woocommerce_product_data_tabs', [ __CLASS__, 'add_recurring_tab' ], 21);
        add_action('woocommerce_product_data_panels', [ __CLASS__, 'output_recurring_panel' ]);
        add_action('woocommerce_process_product_meta', [ __CLASS__, 'save_recurring_fields' ], 10, 1);
    }

    public static function add_recurring_tab($tabs) {
        $tabs['wipop_recurring'] = [
            'label'    => __('Pago recurrente', 'wipop'),
            'target'   => 'wipop_recurring_options',
            'class'    => ['show_if_simple','show_if_variable'],
            'priority' => 21,
        ];
        return $tabs;
    }

    public static function output_recurring_panel() {
        echo '<div id="wipop_recurring_options" class="panel woocommerce_options_panel">';
        woocommerce_wp_checkbox([
            'id'    => '_wipop_recurring_enabled',
            'label' => __('Habilitar pago recurrente', 'wipop'),
        ]);

        woocommerce_wp_select([
            'id'      => '_wipop_recurring_period',
            'label'   => __('Periodicidad', 'wipop'),
            'options' => [
                'monthly' => __('Mensual', 'wipop'),
                'yearly'  => __('Anual', 'wipop'),
            ],
        ]);
        echo '</div>';
    }

    public static function save_recurring_fields($post_id) {

        $enabled = ! empty($_POST['_wipop_recurring_enabled']) ? 'yes' : 'no';
        update_post_meta($post_id, '_wipop_recurring_enabled', $enabled);

        if (isset($_POST['_wipop_recurring_period'])) {
            update_post_meta(
                $post_id,
                '_wipop_recurring_period',
                sanitize_text_field($_POST['_wipop_recurring_period'])
            );
        }
    }
}
