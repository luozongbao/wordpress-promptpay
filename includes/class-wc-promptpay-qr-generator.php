<?php
/**
 * WooCommerce PromptPay QR Generator Class
 *
 * @package WC_PromptPay_Gateway
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load bundled PromptPay QR library
require_once plugin_dir_path(__FILE__) . 'lib/promptpay-qr.php';

/**
 * WC_PromptPay_QR_Generator class
 * Generates PromptPay QR codes using bundled library
 */
class WC_PromptPay_QR_Generator {

    /**
     * Generate PromptPay QR data string
     *
     * @param string $promptpay_id Phone number or National ID
     * @param float $amount Transaction amount
     * @return string QR data string
     */
    public function generate_qr_data($promptpay_id, $amount = null) {
        try {
            if (!class_exists('WC_PromptPay_QR_Lib')) {
                throw new Exception('PromptPay QR library not found');
            }

            $payload = WC_PromptPay_QR_Lib::generatePayload($promptpay_id, $amount ? floatval($amount) : null);
            
            return $payload;
        } catch (Exception $e) {
            error_log('PromptPay QR Generator Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate QR code image
     *
     * @param string $qr_data QR code data string
     * @param int $order_id Order ID for caching
     * @param int $size QR code size in pixels
     * @return string|false QR code image URL or false on failure
     */
    public function generate_qr_image($qr_data, $order_id, $size = 300) {
        try {
            if (!class_exists('WC_PromptPay_QR_Lib')) {
                throw new Exception('PromptPay QR library not found');
            }

            // Create uploads directory if it doesn't exist
            $upload_dir = wp_upload_dir();
            $promptpay_dir = $upload_dir['basedir'] . '/promptpay-qr/';
            if (!file_exists($promptpay_dir)) {
                wp_mkdir_p($promptpay_dir);
            }

            // Generate unique filename
            $filename = 'qr_order_' . $order_id . '_' . time() . '.png';
            $file_path = $promptpay_dir . $filename;

            // Get PromptPay ID and amount from order
            $order = wc_get_order($order_id);
            if ($order) {
                $promptpay_id = $order->get_meta('_promptpay_id');
                $amount = $order->get_total();
                
                // Generate QR code file using bundled library
                $success = WC_PromptPay_QR_Lib::generateQrCode($file_path, $promptpay_id, $amount ? floatval($amount) : null, $size);
                
                if ($success && file_exists($file_path)) {
                    // Return URL to the generated QR code
                    $file_url = $upload_dir['baseurl'] . '/promptpay-qr/' . $filename;
                    
                    // Clean up old QR files for this order
                    $this->cleanup_old_qr_files($order_id);
                    
                    return $file_url;
                } else {
                    // Fallback to data URL
                    $data_url = WC_PromptPay_QR_Lib::generateQrDataUrl($promptpay_id, $amount ? floatval($amount) : null, $size);
                    if ($data_url) {
                        return $data_url;
                    }
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('PromptPay QR Image Generation Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate PromptPay ID format
     *
     * @param string $promptpay_id PromptPay ID to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_promptpay_id($promptpay_id) {
        if (!class_exists('WC_PromptPay_QR_Lib')) {
            // Fallback validation if library not available
            $clean_id = preg_replace('/[\s\-]/', '', $promptpay_id);
            
            // Check for phone number (10 digits starting with 0)
            if (preg_match('/^0[0-9]{9}$/', $clean_id)) {
                return true;
            }
            
            // Check for National ID (13 digits)
            if (preg_match('/^[0-9]{13}$/', $clean_id)) {
                return true;
            }
            
            // Check for e-Wallet ID (15+ digits)
            if (preg_match('/^[0-9]{15,}$/', $clean_id)) {
                return true;
            }
            
            return false;
        }
        
        return WC_PromptPay_QR_Lib::validatePromptPayId($promptpay_id);
    }

    /**
     * Clean up old QR code files for a specific order
     *
     * @param int $order_id Order ID
     */
    public function cleanup_old_qr_files($order_id = null) {
        $upload_dir = wp_upload_dir();
        $promptpay_dir = $upload_dir['basedir'] . '/promptpay-qr/';
        
        if (!is_dir($promptpay_dir)) {
            return;
        }
        
        $pattern = $order_id ? "qr_order_{$order_id}_*.png" : "qr_*.png";
        $files = glob($promptpay_dir . $pattern);
        
        foreach ($files as $file) {
            // Delete files older than 1 hour, or all files for specific order
            if ($order_id || (filemtime($file) < time() - 3600)) {
                unlink($file);
            }
        }
    }

    /**
     * Generate payment verification token
     *
     * @param int $order_id Order ID
     * @return string Verification token
     */
    public function generate_verification_token($order_id) {
        return wp_hash($order_id . time() . wp_salt());
    }
}
