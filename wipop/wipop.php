<?php

/**
 * Plugin Name: Wipop
 * Description: Pasarelas de pago BBVA (Bizum, Tarjeta y Google Pay) para WooCommerce.
 * Version: 0.1.0
 * Author: Secture Labs S.L.
 * Text Domain: wipop
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

if (! defined('WIPOP_PLUGIN_FILE')) {
    define('WIPOP_PLUGIN_FILE', __FILE__);
    define('WIPOP_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

require_once WIPOP_PLUGIN_PATH . 'core/logger.php';
require_once WIPOP_PLUGIN_PATH . 'core/loader.php';
require_once WIPOP_PLUGIN_PATH . 'core/webhook.php';
require_once WIPOP_PLUGIN_PATH . 'admin/admin.php';


function wipop_missing_wc_notice() {
    echo '<div class="error"><p><strong>' . esc_html__('Wipop requires WooCommerce to be installed and active.', 'wipop') . '</strong></p></div>';
}

/**
 * Initialize the plugin.
 */
function wipop_init() {
    load_plugin_textdomain('wipop', false, dirname(plugin_basename(__FILE__)) . '/languages');

    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', 'wipop_missing_wc_notice');
        return;
    }

    Wipop\Core\Loader::init();
    new Wipop\Admin\Admin();
    Wipop\Core\Webhook::init();
}

add_action('plugins_loaded', 'wipop_init');
