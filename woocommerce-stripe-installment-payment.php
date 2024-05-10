<?php

/**
 * Plugin Name: WooCommerce - Stripe Installments Payment
 * Description: Collect payment on monthly basis with stripe. Only mexican credit card is applicable
 * Plugin Version: 1.0.0
 * Author: ETMAT
 * Author URI: 
 * Text Domain: srp-text
 * Domain Path: /languages 
 */

if (!defined('ABSPATH')) {
    exit;
}

include_once  ABSPATH . 'wp-admin/includes/plugin.php';

// check for plugin using plugin name
if (!is_plugin_active('woocommerce/woocommerce.php')) {
    //plugin is activated
    add_action('admin_notices', function () {
        printf('<div class="notice notice-error"><p>%s</p></div>', __('Stripe Installments Payment for WooCommerce plugin requires WooCommerce.', 'srp-text'));
    });

    return;
}

define('srp_path', trailingslashit(plugin_dir_path(__FILE__)));
define('srp_url', trailingslashit(plugin_dir_url(__FILE__)));

add_action('plugins_loaded', 'srp_initialize_gateway');

function srp_initialize_gateway()
{

    class SRP_Gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'srp-payment';
            $this->icon = srp_url . 'assets/images/stripe.svg';
            $this->has_fields = true;
            $this->method_title = 'Stripe Installments Payment';
            $this->method_description = 'Stripe installments payment gateway to collect monthly payments.';

            $this->supports = array(
                'products'
            );

            $this->init_form_fields();


            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->private_key = $this->testmode ? $this->get_option('test_private_key') : $this->get_option('private_key');
            $this->publishable_key = $this->testmode ? $this->get_option('test_publishable_key') : $this->get_option('publishable_key');

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        }

        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Stripe Installments Payment Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Stripe installments payment',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Collect payment on monthly basis with stripe. Only mexican credit card is applicable.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_publishable_key' => array(
                    'title'       => 'Test Publishable Key',
                    'type'        => 'text'
                ),
                'test_private_key' => array(
                    'title'       => 'Test Secret Key',
                    'type'        => 'password',
                ),
                'publishable_key' => array(
                    'title'       => 'Live Publishable Key',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'Live Secret Key',
                    'type'        => 'password'
                )
            );
        }

        public function payment_fields()
        {

?>
            <input type="hidden" name="src_stat" value="" id="srp_stat">
            <div id="details">
                <input id="cardholder-name" type="text" placeholder="Cardholder name">
                <div id="card-element"></div>
                <button id="get_packages"><?php _e('Select plan', 'srp-text'); ?></button>
            </div>

            <div id="plans" hidden>
                <form id="installment-plan-form">
                    <label><input id="immediate-plan" type="radio" name="installment_plan" value="-1" /> Immediate</label>
                    <input id="payment-intent-id" type="hidden" />
                </form>
            </div>

            <div id="result" hidden>
                <p id="status-message"></p>
            </div>
<?php

        }

        public function payment_scripts()
        {
            wp_enqueue_style('srp-css', srp_url . 'assets/public.css', array(), null, 'all');
            wp_enqueue_script('srp-stripe-js', 'https://js.stripe.com/v3/', array(), null, false);
            wp_enqueue_script('srp-js', srp_url . 'assets/public.js', array(), null, true);
            wp_localize_script('srp-js', 'srpLocal', array(
                'get_packages' => srp_url . 'includes/get_packages.php',
                'confirm_payment' => srp_url . 'includes/confirm_payment.php',
                'pKey' => $this->publishable_key,
                'sKey' => $this->private_key,
                'amount' => round(WC()->cart->get_total('edit')),
                'redirect' => wc_get_checkout_url()
            ));
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            if (isset($_POST['srp_stat']) && $_POST['srp_stat'] != 'success') {
                wp_add_notice('Error processing payment', 'error');
                return;
            } else {
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            }
        }
    }
}


add_filter('woocommerce_payment_gateways', 'srp_add_gateway_class');

function srp_add_gateway_class($methods)
{
    $methods[] = 'SRP_Gateway';
    return $methods;
}
