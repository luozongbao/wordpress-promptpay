<?php
/**
 * Plugin Name: WooCommerce PromptPay Gateway
 * Plugin URI: https://github.com/luozongbao/wordpress-promptpay
 * Description: Accept PromptPay payments in WooCommerce using QR code generation following Bank of Thailand EMVCo specification.
 * Version: 1.0.0
 * Author: Zongbao Luo
 * Author URI: https://github.com/luozongbao
 * Text Domain: wc-promptpay-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * WC requires at least: 3.0
 * WC tested up to: 9.0
 * Woo: 8734941:d6e0db0c-72de-4a16-8a2e-35ea5b64a8c0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_PROMPTPAY_VERSION', '1.0.0');
define('WC_PROMPTPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_PROMPTPAY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_PROMPTPAY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main WooCommerce PromptPay Gateway Class
 */
class WC_PromptPay_Gateway_Main {

    /**
     * Instance of this class
     *
     * @var object
     */
    protected static $_instance = null;

    /**
     * Initialize the plugin
     */
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway_class'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        add_action('woocommerce_blocks_loaded', array($this, 'blocks_support'));
        register_activation_hook(__FILE__, array($this, 'plugin_activate'));
        register_deactivation_hook(__FILE__, array($this, 'plugin_deactivate'));
    }

    /**
     * Get main instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load plugin text domain
        load_plugin_textdomain('wc-promptpay-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        // Include required files
        $this->includes();

        // Initialize gateway
        add_action('woocommerce_init', array($this, 'init_gateway'));
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once WC_PROMPTPAY_PLUGIN_PATH . 'includes/class-wc-promptpay-helper.php';
        require_once WC_PROMPTPAY_PLUGIN_PATH . 'includes/class-wc-promptpay-gateway.php';
        require_once WC_PROMPTPAY_PLUGIN_PATH . 'includes/class-wc-promptpay-qr-generator.php';
        require_once WC_PROMPTPAY_PLUGIN_PATH . 'includes/class-wc-promptpay-payment-handler.php';
        
        // Include admin class only in admin area
        if (is_admin()) {
            require_once WC_PROMPTPAY_PLUGIN_PATH . 'includes/class-wc-promptpay-admin.php';
        }

        // Include blocks support
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once WC_PROMPTPAY_PLUGIN_PATH . 'includes/class-wc-promptpay-blocks-support.php';
        }
    }

    /**
     * Initialize gateway class
     */
    public function init_gateway() {
        if (class_exists('WC_PromptPay_Gateway')) {
            new WC_PromptPay_Gateway();
        }
        
        // Initialize payment handler
        if (class_exists('WC_PromptPay_Payment_Handler')) {
            new WC_PromptPay_Payment_Handler();
        }
        
        // Initialize admin class
        if (is_admin() && class_exists('WC_PromptPay_Admin')) {
            new WC_PromptPay_Admin();
        }
    }

    /**
     * Add gateway to WooCommerce
     */
    public function add_gateway_class($gateways) {
        $gateways[] = 'WC_PromptPay_Gateway';
        return $gateways;
    }

    /**
     * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS)
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Declare HPOS compatibility
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            
            // Declare block checkout compatibility 
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }

    /**
     * Add support for WooCommerce Blocks
     */
    public function blocks_support() {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function( $payment_method_registry ) {
                    $payment_method_registry->register( new WC_PromptPay_Blocks_Support() );
                }
            );
        }
    }

    /**
     * Check HPOS compatibility
     */
    public function check_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            if (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                // HPOS is enabled and we're compatible
                return true;
            }
        }
        return false;
    }

    /**
     * Debug admin notice (only shown when WP_DEBUG is true)
     * Temporarily disabled to avoid class loading issues
     */
    public function debug_admin_notice() {
        // Temporarily disabled
        return;
        
        /*
        // Only show on WooCommerce settings pages
        if (!isset($_GET['page']) || $_GET['page'] !== 'wc-settings') {
            return;
        }

        // Make sure helper class exists
        if (!class_exists('WC_PromptPay_Helper')) {
            echo '<div class="notice notice-error"><p><strong>PromptPay Debug:</strong> Helper class not loaded!</p></div>';
            return;
        }

        $debug_info = WC_PromptPay_Helper::debug_gateway_availability();
        
        echo '<div class="notice notice-info">';
        echo '<p><strong>PromptPay Gateway Debug Info:</strong></p>';
        echo '<ul>';
        foreach ($debug_info as $key => $value) {
            $status = is_bool($value) ? ($value ? '✅' : '❌') : $value;
            echo '<li>' . esc_html($key) . ': ' . esc_html($status) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
        */
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (is_checkout() || is_order_received_page()) {
            wp_enqueue_style(
                'wc-promptpay-style',
                WC_PROMPTPAY_PLUGIN_URL . 'assets/css/promptpay.css',
                array(),
                WC_PROMPTPAY_VERSION
            );

            wp_enqueue_script(
                'wc-promptpay-script',
                WC_PROMPTPAY_PLUGIN_URL . 'assets/js/promptpay.js',
                array('jquery'),
                WC_PROMPTPAY_VERSION,
                true
            );

            wp_localize_script('wc-promptpay-script', 'promptpay_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('promptpay_nonce'),
                'copy_text' => __('Copy PromptPay ID', 'wc-promptpay-gateway'),
                'copied_text' => __('Copied!', 'wc-promptpay-gateway'),
            ));
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        if ('woocommerce_page_wc-settings' === $hook) {
            wp_enqueue_style(
                'wc-promptpay-admin-style',
                WC_PROMPTPAY_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                WC_PROMPTPAY_VERSION
            );
        }
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . 
             sprintf(
                 esc_html__('WooCommerce PromptPay Gateway requires WooCommerce to be installed and active. You can download %s here.', 'wc-promptpay-gateway'),
                 '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
             ) . 
             '</strong></p></div>';
    }

    /**
     * Currency not supported notice
     */
    public function currency_not_supported_notice() {
        $current_currency = get_woocommerce_currency();
        echo '<div class="notice notice-warning"><p><strong>' . 
             sprintf(
                 esc_html__('PromptPay Gateway: Current currency (%s) is not supported. Please set WooCommerce currency to THB (Thai Baht).', 'wc-promptpay-gateway'),
                 $current_currency
             ) . 
             '</strong></p></div>';
    }

    /**
     * Plugin activation
     */
    public function plugin_activate() {
        // Create upload directory for QR codes
        $upload_dir = wp_upload_dir();
        $promptpay_dir = $upload_dir['basedir'] . '/promptpay-qr';
        
        if (!file_exists($promptpay_dir)) {
            wp_mkdir_p($promptpay_dir);
        }

        // Add .htaccess file to protect directory
        $htaccess_file = $promptpay_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Options -Indexes\nDeny from all");
        }

        // Flush rewrite rules for payment page
        if (class_exists('WC_PromptPay_Payment_Handler')) {
            WC_PromptPay_Payment_Handler::flush_rewrite_rules();
        }
    }

    /**
     * Plugin deactivation
     */
    public function plugin_deactivate() {
        // Clean up QR code cache
        $upload_dir = wp_upload_dir();
        $promptpay_dir = $upload_dir['basedir'] . '/promptpay-qr';
        
        if (file_exists($promptpay_dir)) {
            $files = glob($promptpay_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}

/**
 * Returns the main instance of WC_PromptPay_Gateway_Main
 */
function WC_PromptPay_Gateway() {
    return WC_PromptPay_Gateway_Main::instance();
}

// Initialize the plugin
WC_PromptPay_Gateway();
