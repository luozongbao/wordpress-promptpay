<?php
/**
 * WooCommerce PromptPay Blocks Support
 *
 * @package WC_PromptPay_Gateway
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * PromptPay payment method integration for WooCommerce Blocks
 */
final class WC_PromptPay_Blocks_Support extends AbstractPaymentMethodType {

    /**
     * The gateway instance.
     *
     * @var WC_PromptPay_Gateway
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'promptpay';

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_promptpay_settings', []);
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = $gateways[$this->name];
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $script_path = '/assets/js/frontend/blocks.js';
        $script_asset_path = WC_PROMPTPAY_PLUGIN_PATH . 'assets/js/frontend/blocks.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require($script_asset_path)
            : array(
                'dependencies' => array(),
                'version' => WC_PROMPTPAY_VERSION
            );
        $script_url = WC_PROMPTPAY_PLUGIN_URL . $script_path;

        wp_register_script(
            'wc-promptpay-payments-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('wc-promptpay-payments-blocks', 'wc-promptpay-gateway', WC_PROMPTPAY_PLUGIN_PATH . 'languages/');
        }

        return ['wc-promptpay-payments-blocks'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        return [
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports'])
        ];
    }
}
