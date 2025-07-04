<?php

namespace Wipop\Gateways\Bizum;

use WC_Payment_Gateway;
use Wipop\Core\Logger;

defined('ABSPATH') || exit;

class Gateway extends WC_Payment_Gateway
{
  use Logger;

  public function __construct()
  {
    $this->id                 = 'wipop_bizum_gateway';
    $this->method_title       = __('Bizum', 'wipop');
    $this->method_description = __('Paga con Bizum', 'wipop');

    $this->init_form_fields();
    $this->init_settings();

    $this->enabled = $this->get_option('enabled');

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
  }

  public function init_form_fields()
  {
    $this->form_fields = array(
      'enabled' => array(
        'title'   => __('Enable Bizum', 'wipop'),
        'type'    => 'checkbox',
        'label'   => __('Enable Bizum payments', 'wipop'),
        'default' => 'no',
      ),
    );
  }

  
  /**
   * TODO
   */
  public function process_payment($order_id)
  {
    $this->log('Processing Bizum payment for order ' . $order_id);
    return array('result' => 'success');
  }
}
