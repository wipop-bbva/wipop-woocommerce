<?php

namespace Wipop\Core;

use Wipop\Core\Logger;

defined('ABSPATH') || exit;

/**
 * Loader class responsible for registering payment gateways.
 */
class Loader {
    protected static array $available_gateways = [];

    public static function init(): void {
        add_action('init', [ __CLASS__, 'setup_available_gateways' ], 5);

        add_action(
            'update_option_woocommerce_payment_gateways',
            array(__CLASS__, 'on_list_change'),
            10,
            2
        );

        add_action(
            'update_option_woocommerce_wipop_bizum_gateway_settings',
            array(__CLASS__, 'on_settings_change'),
            10,
            3
        );

        add_action(
            'update_option_woocommerce_wipop_card_gateway_settings',
            array(__CLASS__, 'on_settings_change'),
            10,
            3
        );

        add_action(
            'update_option_woocommerce_wipop_gpay_gateway_settings',
            array(__CLASS__, 'on_settings_change'),
            10,
            3
        );

        require_once WIPOP_PLUGIN_PATH . 'admin/Product/RecurringPaymentSettings.php';
        \Wipop\Admin\Product\RecurringPaymentSettings::init();

        add_action('admin_post_wipop_toggle_bizum', [ __CLASS__, 'handle_toggle_bizum' ]);
        add_action('admin_post_wipop_toggle_card', [ __CLASS__, 'handle_toggle_card' ]);
        add_action('admin_post_wipop_toggle_gpay', [ __CLASS__, 'handle_toggle_gpay' ]);

        add_action('admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ]);
    }

    public static function setup_available_gateways(): void {
        $settings    = get_option('wipop_settings', []);
        $merchant_id = trim($settings['merchant_id'] ?? '');

        if (empty($merchant_id)) {
            return;
        }

        self::$available_gateways = self::fetch_merchant_gateways($merchant_id);
        if (empty(self::$available_gateways)) {
            return;
        }

        add_filter('woocommerce_payment_gateways', [ __CLASS__, 'register_available_gateways' ]);
    }

    protected static function fetch_merchant_gateways(string $merchant_id): array {
        /**
         * TODO: hacer petición real a BBVA
         */
        return ['card', 'bizum', 'googlepay'];
    }

    public static function register_available_gateways(array $gateways): array {
        if (in_array('card', self::$available_gateways, true)) {
            require_once WIPOP_PLUGIN_PATH . 'gateways/card/card.php';
            $gateways[] = 'Wipop\Gateways\Card\Gateway';
        }
        if (in_array('bizum', self::$available_gateways, true)) {
            require_once WIPOP_PLUGIN_PATH . 'gateways/bizum/bizum.php';
            $gateways[] = 'Wipop\Gateways\Bizum\Gateway';
        }
        if (in_array('googlepay', self::$available_gateways, true)) {
            require_once WIPOP_PLUGIN_PATH . 'gateways/googlepay/gpay.php';
            $gateways[] = 'Wipop\Gateways\Googlepay\Gateway';
        }

        return $gateways;
    }

    public static function on_list_change($old, $new) {
        $oldList = (array) $old;
        $newList = (array) $new;

        foreach (array_diff($newList, $oldList) as $id) {
            Logger::log("Gateway {$id} activated", 'info');
        }
        foreach (array_diff($oldList, $newList) as $id) {
            Logger::log("Gateway {$id} deactivated", 'info');
        }
    }

    public static function on_settings_change($old, $new, $option_name) {
        $wasOn = ! empty($old['enabled']) && $old['enabled'] === 'yes';
        $isOn  = ! empty($new['enabled']) && $new['enabled'] === 'yes';

        if ($wasOn === $isOn) {
            return;
        }

        $gateway_id = str_replace('_settings', '', $option_name);
        $label      = ucwords(str_replace(
            ['wipop_', '_gateway', '_'],
            ['', '', ' '],
            $gateway_id
        ));
        $action = $isOn ? 'activated' : 'deactivated';

        Logger::log("Gateway {$label} {$action}", 'info');
    }

    public static function enqueue_admin_assets($hook): void {
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
            'wipop-admin-toggle',
            plugin_dir_url(WIPOP_PLUGIN_FILE) . 'assets/js/toggle-btn-gateways.js',
            [ 'wp-i18n' ],
            filemtime(WIPOP_PLUGIN_PATH . 'assets/js/toggle-btn-gateways.js'),
            true
        );

        wp_localize_script('wipop-admin-toggle', 'wipopToggle', [
            'nonce' => wp_create_nonce('wipop_toggle_button'),
            'i18n'  => [
                'activate'   => __('Activar', 'wipop'),
                'deactivate' => __('Desactivar', 'wipop'),
                'confirm_deactivate' => __('¿Estás segura de que quieres desactivar %s?', 'wipop'),
                'default_label'     => __('este método de pago', 'wipop'),
            ],
        ]);

        wp_enqueue_style(
            'wipop-admin-style',
            plugin_dir_url(WIPOP_PLUGIN_FILE) . 'assets/css/admin-gateways.css',
            [],
            filemtime(WIPOP_PLUGIN_PATH . 'assets/css/admin-gateways.css')
        );
    }

    public static function handle_toggle_bizum(): void {
        self::toggle_gateway('woocommerce_wipop_bizum_gateway_settings', 'Bizum');
    }

    public static function handle_toggle_card(): void {
        self::toggle_gateway('woocommerce_wipop_card_gateway_settings', 'Card');
    }

    public static function handle_toggle_gpay(): void {
        self::toggle_gateway('woocommerce_wipop_gpay_gateway_settings', 'GPay');
    }

    protected static function toggle_gateway(string $option_key, string $label): void {
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
