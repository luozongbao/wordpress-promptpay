<?php
/**
 * PromptPay Bank API Integration Example
 *
 * @package WC_PromptPay_Gateway
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_PromptPay_Bank_API class
 * Handles integration with various Thai bank APIs for payment verification
 */
class WC_PromptPay_Bank_API {

    /**
     * API provider
     */
    private $provider;

    /**
     * API endpoint
     */
    private $endpoint;

    /**
     * API key
     */
    private $api_key;

    /**
     * Constructor
     */
    public function __construct() {
        $this->provider = get_option('promptpay_bank_api_provider', '');
        $this->endpoint = get_option('promptpay_bank_api_endpoint', '');
        $this->api_key = get_option('promptpay_bank_api_key', '');
    }

    /**
     * Verify payment with bank API
     *
     * @param WC_Order $order Order object
     * @return array|false Payment verification result or false
     */
    public function verify_payment($order) {
        if (empty($this->provider) || empty($this->endpoint) || empty($this->api_key)) {
            return false;
        }

        switch ($this->provider) {
            case 'scb':
                return $this->verify_with_scb($order);
            case 'bbl':
                return $this->verify_with_bbl($order);
            case 'ktb':
                return $this->verify_with_ktb($order);
            case 'custom':
                return $this->verify_with_custom_api($order);
            default:
                return false;
        }
    }

    /**
     * Verify payment with SCB Easy API
     */
    private function verify_with_scb($order) {
        $promptpay_id = $order->get_meta('_promptpay_id');
        $amount = $order->get_total();
        $order_date = $order->get_date_created();
        
        // SCB API request
        $request_data = array(
            'request_id' => 'PP_' . $order->get_id() . '_' . time(),
            'promptpay_id' => $promptpay_id,
            'amount' => $amount,
            'date_from' => $order_date->format('Y-m-d H:i:s'),
            'date_to' => current_time('mysql'),
        );

        $response = $this->make_api_request('POST', $this->endpoint . '/verify-payment', $request_data);
        
        if ($response && isset($response['status']) && $response['status'] === 'success') {
            return array(
                'verified' => true,
                'transaction_id' => $response['transaction_id'],
                'bank_reference' => $response['reference'],
                'timestamp' => time(),
                'amount' => $response['amount'],
                'provider' => 'scb'
            );
        }

        return false;
    }

    /**
     * Verify payment with Bangkok Bank API
     */
    private function verify_with_bbl($order) {
        // Similar implementation for Bangkok Bank API
        // Each bank has different API structure and requirements
        
        return false; // Placeholder
    }

    /**
     * Verify payment with Krung Thai Bank API
     */
    private function verify_with_ktb($order) {
        // Similar implementation for KTB API
        
        return false; // Placeholder
    }

    /**
     * Verify payment with custom API
     */
    private function verify_with_custom_api($order) {
        $promptpay_id = $order->get_meta('_promptpay_id');
        $amount = $order->get_total();
        
        $request_data = array(
            'promptpay_id' => $promptpay_id,
            'amount' => $amount,
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
        );

        $response = $this->make_api_request('POST', $this->endpoint, $request_data);
        
        if ($response && isset($response['verified']) && $response['verified']) {
            return array(
                'verified' => true,
                'transaction_id' => $response['transaction_id'] ?? '',
                'bank_reference' => $response['reference'] ?? '',
                'timestamp' => time(),
                'amount' => $response['amount'] ?? $amount,
                'provider' => 'custom'
            );
        }

        return false;
    }

    /**
     * Make API request
     */
    private function make_api_request($method, $url, $data = array()) {
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'User-Agent' => 'WooCommerce-PromptPay/' . WC_PROMPTPAY_VERSION,
            ),
        );

        if (!empty($data)) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('PromptPay Bank API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            error_log('PromptPay Bank API HTTP Error: ' . $status_code . ' - ' . $body);
            return false;
        }

        return $decoded;
    }

    /**
     * Test API connection
     */
    public function test_connection() {
        if (empty($this->endpoint) || empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('API endpoint and key are required.', 'wc-promptpay-gateway')
            );
        }

        $test_data = array(
            'test' => true,
            'timestamp' => time()
        );

        $response = $this->make_api_request('POST', $this->endpoint . '/test', $test_data);

        if ($response) {
            return array(
                'success' => true,
                'message' => __('API connection successful.', 'wc-promptpay-gateway'),
                'response' => $response
            );
        } else {
            return array(
                'success' => false,
                'message' => __('API connection failed.', 'wc-promptpay-gateway')
            );
        }
    }

    /**
     * Get supported banks
     */
    public static function get_supported_banks() {
        return array(
            'scb' => array(
                'name' => 'SCB Easy API',
                'description' => 'Siam Commercial Bank Easy API',
                'docs_url' => 'https://developer.scb.co.th/',
            ),
            'bbl' => array(
                'name' => 'Bangkok Bank API',
                'description' => 'Bangkok Bank Developer API',
                'docs_url' => 'https://developer.bangkokbank.com/',
            ),
            'ktb' => array(
                'name' => 'Krung Thai Bank API',
                'description' => 'Krung Thai Bank Open API',
                'docs_url' => 'https://openapi.ktb.co.th/',
            ),
            'custom' => array(
                'name' => 'Custom API',
                'description' => 'Custom payment verification API',
                'docs_url' => '',
            ),
        );
    }
}
