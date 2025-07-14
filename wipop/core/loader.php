<?php

namespace Wipop\Core;

use Wipop\Core\Logger;

defined('ABSPATH') || exit;

/**
 * Loader class responsible for registering payment gateways.
 */
class Loader {
    public static function init() {
        add_filter(
            'woocommerce_payment_gateways',
            array(__CLASS__, 'register_gateways')
        );

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
    }


    public static function register_gateways(array $gateways): array {
        require_once WIPOP_PLUGIN_PATH . 'gateways/bizum/bizum.php';
        $gateways[] = 'Wipop\Gateways\Bizum\Gateway';

        require_once WIPOP_PLUGIN_PATH . 'gateways/card/card.php';
        $gateways[] = 'Wipop\Gateways\Card\Gateway';

        require_once WIPOP_PLUGIN_PATH . 'gateways/googlepay/gpay.php';
        $gateways[] = 'Wipop\Gateways\Googlepay\Gateway';

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
}
