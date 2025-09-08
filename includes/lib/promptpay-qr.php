<?php
/**
 * Bundled PromptPay QR Code Generator
 * Based on kittinan/php-promptpay-qr library
 * 
 * @package WC_PromptPay_Gateway
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_PromptPay_QR_Lib')) {
    /**
     * PromptPay QR Code Library
     * Bundled implementation for WordPress PromptPay plugin
     */
    class WC_PromptPay_QR_Lib {
        
        /**
         * Generate PromptPay payload string
         *
         * @param string $id PromptPay ID (phone number or National ID)
         * @param float|null $amount Payment amount (optional)
         * @return string EMVCo compliant payload string
         */
        public static function generatePayload($id, $amount = null) {
            // Format PromptPay ID
            $formatted_id = self::formatPromptPayId($id);
            
            // Build EMVCo QR payload
            $payload = '';
            
            // 00: Payload Format Indicator
            $payload .= self::formatTLV('00', '01');
            
            // 01: Point of Initiation Method
            $payload .= self::formatTLV('01', $amount ? '12' : '11');
            
            // 29: Merchant Account Information - PromptPay
            $merchant_info = self::buildPromptPayMerchantInfo($formatted_id);
            $payload .= self::formatTLV('29', $merchant_info);
            
            // 53: Transaction Currency (764 = Thai Baht)
            $payload .= self::formatTLV('53', '764');
            
            // 54: Transaction Amount (only if amount is specified)
            if ($amount && $amount > 0) {
                $amount_str = number_format($amount, 2, '.', '');
                $payload .= self::formatTLV('54', $amount_str);
            }
            
            // 58: Country Code
            $payload .= self::formatTLV('58', 'TH');
            
            // 63: CRC16 checksum (calculated at the end)
            $payload_with_crc_placeholder = $payload . '6304';
            $crc = self::calculateCRC16($payload_with_crc_placeholder);
            $payload .= self::formatTLV('63', sprintf('%04X', $crc));
            
            return $payload;
        }
        
        /**
         * Generate QR code image file
         *
         * @param string $payload PromptPay payload
         * @param string $filepath Output file path
         * @param int $size QR code size in pixels
         * @return bool Success status
         */
        public static function generateQrCode($filepath, $id, $amount = null, $size = 300) {
            $payload = self::generatePayload($id, $amount);
            
            // Try different QR generation methods
            
            // Method 1: Check if we have QR code library available
            if (self::generateWithPhpQrCode($payload, $filepath, $size)) {
                return true;
            }
            
            // Method 2: Use Google Charts API (fallback)
            return self::generateWithGoogleCharts($payload, $filepath, $size);
        }
        
        /**
         * Format PromptPay ID according to specification
         *
         * @param string $id Raw PromptPay ID
         * @return string Formatted PromptPay ID
         */
        private static function formatPromptPayId($id) {
            // Remove any non-numeric characters
            $clean_id = preg_replace('/[^0-9]/', '', $id);
            
            if (strlen($clean_id) === 10 && substr($clean_id, 0, 1) === '0') {
                // Thai mobile number: 0899999999 -> 0066899999999
                return '0066' . substr($clean_id, 1);
            } elseif (strlen($clean_id) === 13) {
                // Thai National ID: use as is
                return $clean_id;
            } elseif (strlen($clean_id) >= 15) {
                // e-Wallet ID: use as is
                return $clean_id;
            } else {
                throw new InvalidArgumentException('Invalid PromptPay ID format: ' . self::maskPromptPayId($id));
            }
        }
        
        /**
         * Mask PromptPay ID for error messages (show only last 4 digits)
         *
         * @param string $id Raw PromptPay ID
         * @return string Masked PromptPay ID
         */
        private static function maskPromptPayId($id) {
            $clean_id = preg_replace('/[^0-9]/', '', $id);
            $len = strlen($clean_id);
            if ($len <= 4) {
                return str_repeat('*', $len);
            }
            return str_repeat('*', $len - 4) . substr($clean_id, -4);
        }
        
        /**
         * Build PromptPay merchant account information
         *
         * @param string $formatted_id Formatted PromptPay ID
         * @return string Merchant info string
         */
        private static function buildPromptPayMerchantInfo($formatted_id) {
            $merchant_info = '';
            
            // 00: Globally Unique Identifier
            $merchant_info .= self::formatTLV('00', 'A000000677010111');
            
            // 01: PromptPay ID
            $merchant_info .= self::formatTLV('01', $formatted_id);
            
            return $merchant_info;
        }
        
        /**
         * Format Tag-Length-Value (TLV) data object
         *
         * @param string $tag 2-digit tag
         * @param string $value Value
         * @return string Formatted TLV string
         */
        private static function formatTLV($tag, $value) {
            $length = strlen($value);
            return $tag . sprintf('%02d', $length) . $value;
        }
        
        /**
         * Calculate CRC16-CCITT checksum
         *
         * @param string $data Input data
         * @return int CRC16 checksum
         */
        private static function calculateCRC16($data) {
            $crc = 0xFFFF;
            $polynomial = 0x1021;
            
            for ($i = 0; $i < strlen($data); $i++) {
                $crc ^= (ord($data[$i]) << 8);
                
                for ($j = 0; $j < 8; $j++) {
                    if ($crc & 0x8000) {
                        $crc = (($crc << 1) ^ $polynomial) & 0xFFFF;
                    } else {
                        $crc = ($crc << 1) & 0xFFFF;
                    }
                }
            }
            
            return $crc;
        }
        
        /**
         * Generate QR code using PHP QR Code library (if available)
         *
         * @param string $payload QR payload
         * @param string $filepath Output file path
         * @param int $size QR code size
         * @return bool Success status
         */
        private static function generateWithPhpQrCode($payload, $filepath, $size) {
            // Check if phpqrcode library is available
            if (!class_exists('QRcode') && file_exists(ABSPATH . 'wp-content/phpqrcode/qrlib.php')) {
                include_once ABSPATH . 'wp-content/phpqrcode/qrlib.php';
            }
            
            if (class_exists('QRcode')) {
                try {
                    QRcode::png($payload, $filepath, QR_ECLEVEL_L, 8, 2);
                    return file_exists($filepath);
                } catch (Exception $e) {
                    error_log('QRcode generation failed: ' . $e->getMessage());
                    return false;
                }
            }
            
            return false;
        }
        
        /**
         * Generate QR code using Google Charts API
         *
         * @param string $payload QR payload
         * @param string $filepath Output file path
         * @param int $size QR code size
         * @return bool Success status
         */
        private static function generateWithGoogleCharts($payload, $filepath, $size) {
            $chart_size = $size . 'x' . $size;
            $url = 'https://chart.googleapis.com/chart?chs=' . $chart_size . '&cht=qr&chl=' . urlencode($payload);
            
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'user-agent' => 'WordPress-PromptPay/' . (defined('WC_PROMPTPAY_VERSION') ? WC_PROMPTPAY_VERSION : '1.0.0')
            ));
            
            if (is_wp_error($response)) {
                error_log('Google Charts API error: ' . $response->get_error_message());
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                error_log('Google Charts API HTTP error: ' . $response_code);
                return false;
            }
            
            $image_data = wp_remote_retrieve_body($response);
            if (empty($image_data)) {
                return false;
            }
            
            $result = file_put_contents($filepath, $image_data);
            return $result !== false;
        }
        
        /**
         * Validate PromptPay ID format
         *
         * @param string $id PromptPay ID to validate
         * @return bool True if valid, false otherwise
         */
        public static function validatePromptPayId($id) {
            $clean_id = preg_replace('/[^0-9]/', '', $id);
            
            // Check for Thai mobile number (10 digits starting with 0)
            if (preg_match('/^0[0-9]{9}$/', $clean_id)) {
                return true;
            }
            
            // Check for Thai National ID (13 digits)
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
         * Generate QR code as base64 data URL
         *
         * @param string $id PromptPay ID
         * @param float|null $amount Payment amount
         * @param int $size QR code size
         * @return string|false Base64 data URL or false on failure
         */
        public static function generateQrDataUrl($id, $amount = null, $size = 300) {
            $payload = self::generatePayload($id, $amount);
            
            $chart_size = $size . 'x' . $size;
            $url = 'https://chart.googleapis.com/chart?chs=' . $chart_size . '&cht=qr&chl=' . urlencode($payload);
            
            $response = wp_remote_get($url, array('timeout' => 30));
            
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return false;
            }
            
            $image_data = wp_remote_retrieve_body($response);
            if (empty($image_data)) {
                return false;
            }
            
            return 'data:image/png;base64,' . base64_encode($image_data);
        }
    }
}
