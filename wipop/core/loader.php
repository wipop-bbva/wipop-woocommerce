<?php

namespace Wipop\Core;

defined('ABSPATH') || exit;

/**
 * Loader class responsible for registering payment gateways.
 */
class Loader {
    public static function init() {
        add_filter('woocommerce_payment_gateways', array( __CLASS__, 'register_gateways' ));

        require_once WIPOP_PLUGIN_PATH . 'admin/Product/RecurringPaymentSettings.php';
        \Wipop\Admin\Product\RecurringPaymentSettings::init();
    }

    public static function register_gateways($gateways) {
        require_once WIPOP_PLUGIN_PATH . 'gateways/bizum/bizum.php';
        require_once WIPOP_PLUGIN_PATH . 'gateways/card/card.php';
        require_once WIPOP_PLUGIN_PATH . 'gateways/googlepay/gpay.php';

        $gateways[] = 'Wipop\Gateways\Bizum\Gateway';
        $gateways[] = 'Wipop\Gateways\Card\Gateway';
        $gateways[] = 'Wipop\Gateways\Googlepay\Gateway';

        return $gateways;
    }
}
