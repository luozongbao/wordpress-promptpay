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
 * Generates PromptPay QR codes following Bank of Thailand EMVCo specification
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
        // EMVCo QR Code Data Objects
        $data = array();
        
        // 00: Payload Format Indicator
        $data['00'] = '01';
        
        // 01: Point of Initiation Method (11 = static, 12 = dynamic)
        $data['01'] = $amount ? '12' : '11';
        
        // 29: Merchant Account Information - PromptPay
        $promptpay_data = $this->build_promptpay_data($promptpay_id);
        $data['29'] = $promptpay_data;
        
        // 52: Merchant Category Code (not required for PromptPay but good practice)
        // $data['52'] = '0000';
        
        // 53: Transaction Currency (764 = Thai Baht)
        $data['53'] = '764';
        
        // 54: Transaction Amount (only if amount is specified)
        if ($amount && $amount > 0) {
            $data['54'] = number_format($amount, 2, '.', '');
        }
        
        // 58: Country Code
        $data['58'] = 'TH';
        
        // 62: Additional Data Field Template (optional - can include order reference)
        // $data['62'] = $this->build_additional_data();
        
        // Build QR string without CRC
        $qr_string = '';
        foreach ($data as $tag => $value) {
            $qr_string .= $this->format_data_object($tag, $value);
        }
        
        // 63: CRC16-CCITT Checksum (must be last)
        $crc = $this->calculate_crc16($qr_string . '6304');
        $qr_string .= '63' . '04' . $crc;
        
        return $qr_string;
    }

    /**
     * Build PromptPay merchant account information
     *
     * @param string $promptpay_id Phone number or National ID
     * @return string Formatted PromptPay data
     */
    private function build_promptpay_data($promptpay_id) {
        // Clean the PromptPay ID
        $clean_id = preg_replace('/[\s\-]/', '', $promptpay_id);
        
        $promptpay_data = array();
        
        // 00: Globally Unique Identifier
        $promptpay_data['00'] = 'A000000677010111';
        
        // 01: PromptPay ID
        if (strlen($clean_id) === 10 && substr($clean_id, 0, 1) === '0') {
            // Phone number format: remove leading 0 and add 66
            $promptpay_data['01'] = '0066' . substr($clean_id, 1);
        } elseif (strlen($clean_id) === 13) {
            // National ID format: use as is
            $promptpay_data['01'] = $clean_id;
        } else {
            throw new Exception('Invalid PromptPay ID format');
        }
        
        // 02: PromptPay ID Type (not always required, but can be used)
        // For phone: MSISDN, For National ID: NATID
        
        // Build PromptPay string
        $promptpay_string = '';
        foreach ($promptpay_data as $tag => $value) {
            $promptpay_string .= $this->format_data_object($tag, $value);
        }
        
        return $promptpay_string;
    }

    /**
     * Format data object with tag, length, and value
     *
     * @param string $tag Data object tag
     * @param string $value Data object value
     * @return string Formatted data object
     */
    private function format_data_object($tag, $value) {
        $length = strlen($value);
        return $tag . sprintf('%02d', $length) . $value;
    }

    /**
     * Calculate CRC16-CCITT checksum
     *
     * @param string $data Input data
     * @return string 4-character hex CRC
     */
    private function calculate_crc16($data) {
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
        
        return strtoupper(sprintf('%04X', $crc));
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
        // Check if we can use a QR code library
        if (!$this->check_qr_library()) {
            return $this->generate_qr_image_api($qr_data, $order_id, $size);
        }
        
        // Generate QR code using local library (if available)
        return $this->generate_qr_image_local($qr_data, $order_id, $size);
    }

    /**
     * Check if QR code library is available
     *
     * @return bool
     */
    private function check_qr_library() {
        // Check for popular QR code libraries
        return class_exists('Endroid\QrCode\QrCode') || 
               class_exists('BaconQrCode\Renderer\Image\Png') ||
               function_exists('imagecreatetruecolor'); // GD library check
    }

    /**
     * Generate QR code using external API service
     *
     * @param string $qr_data QR code data
     * @param int $order_id Order ID
     * @param int $size QR code size
     * @return string|false QR image URL or false
     */
    private function generate_qr_image_api($qr_data, $order_id, $size = 300) {
        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/promptpay-qr';
        $qr_url_dir = $upload_dir['baseurl'] . '/promptpay-qr';
        
        // Ensure directory exists
        if (!file_exists($qr_dir)) {
            wp_mkdir_p($qr_dir);
        }
        
        $filename = 'qr-' . $order_id . '-' . md5($qr_data) . '.png';
        $file_path = $qr_dir . '/' . $filename;
        $file_url = $qr_url_dir . '/' . $filename;
        
        // Check if file already exists
        if (file_exists($file_path)) {
            return $file_url;
        }
        
        // Use Google Charts API (free alternative)
        $api_url = add_query_arg(array(
            'chs' => $size . 'x' . $size,
            'cht' => 'qr',
            'chl' => rawurlencode($qr_data),
            'choe' => 'UTF-8'
        ), 'https://chart.googleapis.com/chart');
        
        // Download QR code image
        $response = wp_remote_get($api_url, array(
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            error_log('PromptPay QR: Failed to generate QR code - ' . $response->get_error_message());
            return false;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        
        if (empty($image_data)) {
            error_log('PromptPay QR: Empty QR code response');
            return false;
        }
        
        // Save image to file
        if (file_put_contents($file_path, $image_data)) {
            return $file_url;
        }
        
        error_log('PromptPay QR: Failed to save QR code image');
        return false;
    }

    /**
     * Generate QR code using local library (placeholder for future implementation)
     *
     * @param string $qr_data QR code data
     * @param int $order_id Order ID
     * @param int $size QR code size
     * @return string|false QR image URL or false
     */
    private function generate_qr_image_local($qr_data, $order_id, $size = 300) {
        // This method can be extended to use local QR libraries like:
        // - endroid/qr-code
        // - bacon/bacon-qr-code
        // - chillerlan/php-qrcode
        
        // For now, fallback to API method
        return $this->generate_qr_image_api($qr_data, $order_id, $size);
    }

    /**
     * Validate PromptPay ID format
     *
     * @param string $promptpay_id PromptPay ID to validate
     * @return bool
     */
    public function validate_promptpay_id($promptpay_id) {
        $clean_id = preg_replace('/[\s\-]/', '', $promptpay_id);
        
        // Check phone number format (10 digits starting with 0)
        if (preg_match('/^0[0-9]{9}$/', $clean_id)) {
            return true;
        }
        
        // Check National ID format (13 digits)
        if (preg_match('/^[0-9]{13}$/', $clean_id)) {
            return true;
        }
        
        return false;
    }

    /**
     * Clean up old QR code files
     *
     * @param int $days_old Delete files older than this many days
     */
    public function cleanup_old_qr_files($days_old = 7) {
        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/promptpay-qr';
        
        if (!file_exists($qr_dir)) {
            return;
        }
        
        $files = glob($qr_dir . '/qr-*.png');
        $cutoff_time = time() - ($days_old * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}
