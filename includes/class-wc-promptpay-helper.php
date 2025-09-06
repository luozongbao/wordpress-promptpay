<?php
/**
 * WooCommerce PromptPay Helper Class
 *
 * @package WC_PromptPay_Gateway
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_PromptPay_Helper class
 * Contains utility functions and helpers
 */
class WC_PromptPay_Helper {

    /**
     * Format PromptPay ID for display
     *
     * @param string $promptpay_id PromptPay ID
     * @return string Formatted PromptPay ID
     */
    public static function format_promptpay_id($promptpay_id) {
        $clean_id = preg_replace('/[\s\-]/', '', $promptpay_id);
        
        if (strlen($clean_id) === 10) {
            // Phone number: 0XX-XXX-XXXX
            return substr($clean_id, 0, 3) . '-' . substr($clean_id, 3, 3) . '-' . substr($clean_id, 6);
        } elseif (strlen($clean_id) === 13) {
            // National ID: X-XXXX-XXXXX-XX-X
            return substr($clean_id, 0, 1) . '-' . substr($clean_id, 1, 4) . '-' . substr($clean_id, 5, 5) . '-' . substr($clean_id, 10, 2) . '-' . substr($clean_id, 12, 1);
        }
        
        return $clean_id;
    }

    /**
     * Validate PromptPay ID
     *
     * @param string $promptpay_id PromptPay ID to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate_promptpay_id($promptpay_id) {
        if (empty($promptpay_id)) {
            return new WP_Error('empty_id', __('PromptPay ID cannot be empty.', 'wc-promptpay-gateway'));
        }

        $clean_id = preg_replace('/[\s\-]/', '', $promptpay_id);
        
        // Check phone number format (10 digits starting with 0)
        if (preg_match('/^0[0-9]{9}$/', $clean_id)) {
            return true;
        }
        
        // Check National ID format (13 digits)
        if (preg_match('/^[0-9]{13}$/', $clean_id)) {
            // Optional: Add National ID checksum validation
            if (self::validate_national_id_checksum($clean_id)) {
                return true;
            } else {
                return new WP_Error('invalid_national_id', __('Invalid National ID checksum.', 'wc-promptpay-gateway'));
            }
        }
        
        return new WP_Error('invalid_format', __('PromptPay ID must be a 10-digit phone number (starting with 0) or 13-digit National ID.', 'wc-promptpay-gateway'));
    }

    /**
     * Validate Thai National ID checksum
     *
     * @param string $national_id 13-digit National ID
     * @return bool
     */
    public static function validate_national_id_checksum($national_id) {
        if (strlen($national_id) !== 13 || !ctype_digit($national_id)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$national_id[$i] * (13 - $i);
        }
        
        $remainder = $sum % 11;
        $checksum = (11 - $remainder) % 10;
        
        return $checksum == (int)$national_id[12];
    }

    /**
     * Get PromptPay ID type
     *
     * @param string $promptpay_id PromptPay ID
     * @return string 'phone' or 'national_id'
     */
    public static function get_promptpay_id_type($promptpay_id) {
        $clean_id = preg_replace('/[\s\-]/', '', $promptpay_id);
        
        if (strlen($clean_id) === 10 && substr($clean_id, 0, 1) === '0') {
            return 'phone';
        } elseif (strlen($clean_id) === 13) {
            return 'national_id';
        }
        
        return 'unknown';
    }

    /**
     * Format currency amount for PromptPay
     *
     * @param float $amount Amount
     * @return string Formatted amount
     */
    public static function format_amount($amount) {
        return number_format((float)$amount, 2, '.', '');
    }

    /**
     * Get order reference for PromptPay
     *
     * @param WC_Order $order WooCommerce order
     * @return string Order reference
     */
    public static function get_order_reference($order) {
        $reference = $order->get_order_number();
        
        // Add prefix if needed
        $prefix = apply_filters('wc_promptpay_order_reference_prefix', 'WC');
        if ($prefix) {
            $reference = $prefix . '-' . $reference;
        }
        
        return $reference;
    }

    /**
     * Log PromptPay related messages
     *
     * @param string $message Log message
     * @param string $level Log level (info, error, debug)
     */
    public static function log($message, $level = 'info') {
        if (class_exists('WC_Logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message, array('source' => 'promptpay-gateway'));
        } else {
            error_log('PromptPay Gateway: ' . $message);
        }
    }

    /**
     * Check if current page is PromptPay related
     *
     * @return bool
     */
    public static function is_promptpay_page() {
        global $wp;
        
        if (is_checkout() || is_order_received_page()) {
            return true;
        }
        
        if (is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received')) {
            return true;
        }
        
        return false;
    }

    /**
     * Get PromptPay gateway instance
     *
     * @return WC_PromptPay_Gateway|false
     */
    public static function get_gateway() {
        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        
        if (isset($payment_gateways['promptpay'])) {
            return $payment_gateways['promptpay'];
        }
        
        return false;
    }

    /**
     * Check if PromptPay gateway is enabled
     *
     * @return bool
     */
    public static function is_gateway_enabled() {
        $gateway = self::get_gateway();
        
        if (!$gateway) {
            return false;
        }
        
        return 'yes' === $gateway->enabled;
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public static function get_plugin_version() {
        return WC_PROMPTPAY_VERSION;
    }

    /**
     * Check if current environment is development
     *
     * @return bool
     */
    public static function is_development() {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Sanitize PromptPay ID
     *
     * @param string $promptpay_id Raw PromptPay ID
     * @return string Cleaned PromptPay ID
     */
    public static function sanitize_promptpay_id($promptpay_id) {
        // Remove spaces, dashes, and other non-numeric characters except for phone prefix
        $clean_id = preg_replace('/[^\d]/', '', $promptpay_id);
        
        return $clean_id;
    }

    /**
     * Generate transaction reference
     *
     * @param int $order_id Order ID
     * @return string Transaction reference
     */
    public static function generate_transaction_reference($order_id) {
        $timestamp = time();
        $random = wp_rand(1000, 9999);
        
        return sprintf('PP%d%d%d', $order_id, $timestamp, $random);
    }

    /**
     * Check if QR code can be generated
     *
     * @return bool
     */
    public static function can_generate_qr() {
        // Check if we have internet connection for API-based QR generation
        $response = wp_remote_get('https://www.google.com', array(
            'timeout' => 5,
            'sslverify' => false
        ));
        
        return !is_wp_error($response);
    }

    /**
     * Get supported currencies
     *
     * @return array
     */
    public static function get_supported_currencies() {
        return apply_filters('wc_promptpay_supported_currencies', array('THB'));
    }

    /**
     * Check if current currency is supported
     *
     * @return bool
     */
    public static function is_currency_supported() {
        $current_currency = get_woocommerce_currency();
        $supported_currencies = self::get_supported_currencies();
        
        return in_array($current_currency, $supported_currencies);
    }

    /**
     * Get default settings
     *
     * @return array
     */
    public static function get_default_settings() {
        return array(
            'enabled' => 'yes',
            'title' => __('PromptPay', 'wc-promptpay-gateway'),
            'description' => __('Pay securely using PromptPay QR code. Scan the QR code with your mobile banking app.', 'wc-promptpay-gateway'),
            'promptpay_id' => '',
            'instructions' => __('Please scan the QR code below with your mobile banking app to complete the payment. The order will be processed after payment confirmation.', 'wc-promptpay-gateway'),
            'order_status' => 'on-hold',
        );
    }

    /**
     * Check if High-Performance Order Storage (HPOS) is enabled
     *
     * @return bool
     */
    public static function is_hpos_enabled() {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        return false;
    }

    /**
     * Get order meta data in HPOS-compatible way
     *
     * @param WC_Order $order Order object
     * @param string $meta_key Meta key
     * @param bool $single Return single value
     * @return mixed
     */
    public static function get_order_meta($order, $meta_key, $single = true) {
        if (self::is_hpos_enabled()) {
            return $order->get_meta($meta_key, $single);
        } else {
            return get_post_meta($order->get_id(), $meta_key, $single);
        }
    }

    /**
     * Update order meta data in HPOS-compatible way
     *
     * @param WC_Order $order Order object
     * @param string $meta_key Meta key
     * @param mixed $meta_value Meta value
     */
    public static function update_order_meta($order, $meta_key, $meta_value) {
        if (self::is_hpos_enabled()) {
            $order->update_meta_data($meta_key, $meta_value);
            $order->save();
        } else {
            update_post_meta($order->get_id(), $meta_key, $meta_value);
        }
    }

    /**
     * Delete order meta data in HPOS-compatible way
     *
     * @param WC_Order $order Order object
     * @param string $meta_key Meta key
     */
    public static function delete_order_meta($order, $meta_key) {
        if (self::is_hpos_enabled()) {
            $order->delete_meta_data($meta_key);
            $order->save();
        } else {
            delete_post_meta($order->get_id(), $meta_key);
        }
    }
}
