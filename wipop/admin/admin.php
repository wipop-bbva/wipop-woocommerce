<?php

namespace Wipop\Admin;

defined('ABSPATH') || exit;

/**
 * Admin settings for Wipop.
 */
class Admin {
    /**
     * Option name used to store settings.
     *
     * @var string
     */
    private $option_name = 'Wipop_settings';

    public function __construct() {
        add_action('admin_menu', array( $this, 'add_menu' ));
        add_action('admin_init', array( $this, 'register_settings' ));
    }

    /**
     * Register settings fields.
     */
    public function register_settings() {
        register_setting('Wipop_group', $this->option_name);
        add_settings_section('Wipop_main', '', '__return_false', 'wipop');

        foreach ($this->get_fields() as $key => $field) {
            add_settings_field(
                $key,
                $field['title'],
                array( $this, 'render_field' ),
                'Wipop',
                'Wipop_main',
                array( 'key' => $key, 'field' => $field )
            );
        }
    }


    public function render_field($args) {
        $options = (array) get_option($this->option_name);
        $key     = $args['key'];
        $field   = $args['field'];
        $value   = isset($options[ $key ]) ? $options[ $key ] : $field['default'];

        switch ($field['type']) {
            case 'text':
                printf(
                    '<input type="text" class="%s" name="%s[%s]" value="%s" placeholder="%s"/>',
                    esc_attr($field['class']),
                    esc_attr($this->option_name),
                    esc_attr($key),
                    esc_attr($value),
                    esc_attr($field['placeholder'])
                );
                break;
            case 'select':
                printf('<select class="%s" name="%s[%s]">', esc_attr($field['class']), esc_attr($this->option_name), esc_attr($key));
                foreach ($field['options'] as $id => $label) {
                    printf('<option value="%s" %s>%s</option>', esc_attr($id), selected($value, $id, false), esc_html($label));
                }
                echo '</select>';
                break;
        }

        if (! empty($field['description'])) {
            printf('<p class="description">%s</p>', esc_html($field['description']));
        }
    }


    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            __('Wipop Settings', 'wipop'),
            'Wipop',
            'manage_options',
            'Wipop',
            array( $this, 'settings_page' )
        );
    }

    public function settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Wipop Settings', 'wipop') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('Wipop_group');
        do_settings_sections('Wipop');
        submit_button();
        echo '</form></div>';
    }

    private function get_fields() {
        return array(
            'merchant_id' => array(
                'title'       => __('Merchant ID', 'wipop'),
                'type'        => 'text',
                'class'       => 'Wipop-merchant-id',
                'placeholder' => 'Tu Merchant ID',
                'description' => __('Introduce tu Merchant ID del BBVA.', 'wipop'),
                'default'     => '',
            ),
            'environment' => array(
                'title'       => __('Entorno', 'wipop'),
                'type'        => 'select',
                'class'       => 'Wipop-environment',
                'placeholder' => 'Entorno de pruebas o producción',
                'description' => __('Elige el entorno de pagos.', 'wipop'),
                'options'     => array(
                    'sandbox'    => __('Sandbox', 'wipop'),
                    'production' => __('Producción', 'wipop'),
                ),
                'default'     => 'sandbox',
            ),
            'public_key' => array(
                'title'       => __('Public Key', 'wipop'),
                'type'        => 'text',
                'class'       => 'Wipop-public-key',
                'placeholder' => 'Tu Clave Pública',
                'description' => __('Introduce tu Public Key del BBVA.', 'wipop'),
                'default'     => '',
            ),
            'private_key' => array(
                'title'       => __('Private Key', 'wipop'),
                'type'        => 'text',
                'class'       => 'Wipop-private-key',
                'placeholder' => 'Tu Clave Privada',
                'description' => __('Introduce tu Private Key del BBVA.', 'wipop'),
                'default'     => '',
            ),
        );
    }
}
