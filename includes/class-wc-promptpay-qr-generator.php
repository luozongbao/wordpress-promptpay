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

/**
 * WC_PromptPay_QR_Generator class
 * Generates PromptPay QR codes using direct EMVCo implementation
 */
class WC_PromptPay_QR_Generator {

    // EMVCo QR Code constants
    const ID_PAYLOAD_FORMAT = '00';
    const ID_POI_METHOD = '01';
    const ID_MERCHANT_INFORMATION_BOT = '29';
    const ID_TRANSACTION_CURRENCY = '53';
    const ID_TRANSACTION_AMOUNT = '54';
    const ID_COUNTRY_CODE = '58';
    const ID_CRC = '63';
    
    const PAYLOAD_FORMAT_EMV_QRCPS_MERCHANT_PRESENTED_MODE = '01';
    const POI_METHOD_STATIC = '11';
    const POI_METHOD_DYNAMIC = '12';
    const MERCHANT_INFORMATION_TEMPLATE_ID_GUID = '00';
    const BOT_ID_MERCHANT_PHONE_NUMBER = '01';
    const BOT_ID_MERCHANT_TAX_ID = '02';
    const BOT_ID_MERCHANT_EWALLET_ID = '03';
    const GUID_PROMPTPAY = 'A000000677010111';
    const TRANSACTION_CURRENCY_THB = '764';
    const COUNTRY_CODE_TH = 'TH';

    /**
     * Generate PromptPay QR data string
     *
     * @param string $promptpay_id Phone number or National ID
     * @param float $amount Transaction amount
     * @return string QR data string
     */
    public function generate_qr_data($promptpay_id, $amount = null) {
        try {
            $target = $this->sanitize_target($promptpay_id);
            $target_type = strlen($target) >= 15 ? self::BOT_ID_MERCHANT_EWALLET_ID : 
                          (strlen($target) >= 13 ? self::BOT_ID_MERCHANT_TAX_ID : self::BOT_ID_MERCHANT_PHONE_NUMBER);

            $data = [
                $this->format_tlv(self::ID_PAYLOAD_FORMAT, self::PAYLOAD_FORMAT_EMV_QRCPS_MERCHANT_PRESENTED_MODE),
                $this->format_tlv(self::ID_POI_METHOD, $amount ? self::POI_METHOD_DYNAMIC : self::POI_METHOD_STATIC),
                $this->format_tlv(self::ID_MERCHANT_INFORMATION_BOT, $this->serialize([
                    $this->format_tlv(self::MERCHANT_INFORMATION_TEMPLATE_ID_GUID, self::GUID_PROMPTPAY),
                    $this->format_tlv($target_type, $this->format_target($target))
                ])),
                $this->format_tlv(self::ID_COUNTRY_CODE, self::COUNTRY_CODE_TH),
                $this->format_tlv(self::ID_TRANSACTION_CURRENCY, self::TRANSACTION_CURRENCY_THB),
            ];
            
            if ($amount !== null && $amount > 0) {
                array_push($data, $this->format_tlv(self::ID_TRANSACTION_AMOUNT, $this->format_amount($amount)));
            }
            
            $data_to_crc = $this->serialize($data) . self::ID_CRC . '04';
            array_push($data, $this->format_tlv(self::ID_CRC, $this->crc16($data_to_crc)));
            
            return $this->serialize($data);
            
        } catch (Exception $e) {
            error_log('PromptPay QR Generator Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate QR code image URL using online API
     *
     * @param string $qr_data QR code data string
     * @param int $order_id Order ID for caching
     * @param int $size QR code size in pixels
     * @return string|false QR code image URL or false on failure
     */
    public function generate_qr_image($qr_data, $order_id, $size = 300) {
        try {
            // For now, use direct API URL for better reliability
            // We can implement local caching later if needed
            return $this->get_qr_api_url($qr_data, $size);
            
        } catch (Exception $e) {
            error_log('PromptPay QR Image Generation Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate and save QR code image locally
     */
    private function generate_local_qr_image($qr_data, $order_id, $size = 300) {
        try {
            // Create uploads directory if it doesn't exist
            $upload_dir = wp_upload_dir();
            $promptpay_dir = $upload_dir['basedir'] . '/promptpay-qr/';
            if (!file_exists($promptpay_dir)) {
                wp_mkdir_p($promptpay_dir);
            }

            // Generate unique filename
            $filename = 'qr_order_' . $order_id . '_' . md5($qr_data) . '.png';
            $file_path = $promptpay_dir . $filename;
            $file_url = $upload_dir['baseurl'] . '/promptpay-qr/' . $filename;

            // Check if file already exists
            if (file_exists($file_path)) {
                return $file_url;
            }

            // Generate QR code using external API
            $api_url = $this->get_qr_api_url($qr_data, $size);
            
            // Download and save QR code image
            $qr_image_data = $this->download_qr_image($api_url);
            if ($qr_image_data && file_put_contents($file_path, $qr_image_data)) {
                // Clean up old QR files for this order
                $this->cleanup_old_qr_files($order_id);
                return $file_url;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Local QR generation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get QR code API URL
     */
    private function get_qr_api_url($qr_data, $size = 300) {
        $params = [
            'data' => $qr_data,
            'size' => $size . 'x' . $size,
            'ecc' => 'M',  // Medium error correction
            'format' => 'png',
            'qzone' => 1,  // Quiet zone
            'charset-source' => 'UTF-8',
            'charset-target' => 'UTF-8'
        ];
        
        return "https://api.qrserver.com/v1/create-qr-code/?" . http_build_query($params);
    }

    /**
     * Download QR image from API
     */
    private function download_qr_image($url) {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'WordPress/PromptPay-Gateway'
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }
        
        return wp_remote_retrieve_body($response);
    }

    /**
     * Format TLV (Tag-Length-Value) field
     */
    private function format_tlv($id, $value) {
        // EMVCo: Length field should be at least 2 digits, but allow longer for values >99
        $length = strlen($value);
        $length_str = str_pad($length, max(2, strlen((string)$length)), '0', STR_PAD_LEFT);
        return implode('', [$id, $length_str, $value]);
    }
    
    /**
     * Serialize array of strings
     */
    private function serialize($xs) {
        return implode('', $xs);
    }
    
    /**
     * Sanitize target (remove non-numeric characters)
     */
    private function sanitize_target($str) {
        return preg_replace('/[^0-9]/', '', $str);
    }

    /**
     * Format target according to PromptPay specification
     */
    private function format_target($target) {
        $str = $this->sanitize_target($target);
        if (strlen($str) >= 13) {
            return $str;
        }
        
        $str = preg_replace('/^0/', '66', $str);
        $str = '0000000000000' . $str;
        
        return substr($str, -13);
    }

    /**
     * Format amount for EMVCo
     */
    private function format_amount($amount) {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Calculate CRC16 checksum
     */
    private function crc16($data) {
        $crc = 0xFFFF;
        $polynomial = 0x1021;
        
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ $polynomial;
                } else {
                    $crc = $crc << 1;
                }
            }
        }
        
        return strtoupper(dechex($crc & 0xFFFF));
    }
    /**
     * Validate PromptPay ID format
     *
     * @param string $promptpay_id PromptPay ID to validate
     * @return bool True if valid, false otherwise
     */
    public function validate_promptpay_id($promptpay_id) {
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
