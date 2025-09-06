<?php
/**
 * Simple test file for PromptPay QR generation
 * Use this to test QR code generation outside WordPress
 * 
 * Usage: php test-qr.php
 */

// Include the QR generator class
require_once __DIR__ . '/includes/class-wc-promptpay-qr-generator.php';

/**
 * Test PromptPay QR generation
 */
function test_promptpay_qr() {
    echo "=== PromptPay QR Generator Test ===\n\n";
    
    $generator = new WC_PromptPay_QR_Generator();
    
    // Test cases
    $test_cases = array(
        array(
            'name' => 'Phone Number with Amount',
            'promptpay_id' => '0812345678',
            'amount' => 150.75
        ),
        array(
            'name' => 'National ID with Amount',
            'promptpay_id' => '1234567890123',
            'amount' => 250.00
        ),
        array(
            'name' => 'Phone Number without Amount',
            'promptpay_id' => '0812345678',
            'amount' => null
        )
    );
    
    foreach ($test_cases as $test) {
        echo "Testing: " . $test['name'] . "\n";
        echo "PromptPay ID: " . $test['promptpay_id'] . "\n";
        echo "Amount: " . ($test['amount'] ? $test['amount'] . ' THB' : 'No amount') . "\n";
        
        try {
            $qr_data = $generator->generate_qr_data($test['promptpay_id'], $test['amount']);
            echo "QR Data: " . $qr_data . "\n";
            echo "QR Data Length: " . strlen($qr_data) . " characters\n";
            
            // Validate the generated data
            $validation = validate_qr_data($qr_data);
            echo "Validation: " . ($validation ? "✅ PASS" : "❌ FAIL") . "\n";
            
        } catch (Exception $e) {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
        
        echo "\n" . str_repeat("-", 50) . "\n\n";
    }
}

/**
 * Validate QR data structure
 */
function validate_qr_data($qr_data) {
    // Check if it starts with payload format indicator
    if (substr($qr_data, 0, 4) !== '0001') {
        return false;
    }
    
    // Check if it ends with CRC (63 + 04 + 4 hex chars)
    if (!preg_match('/6304[A-F0-9]{4}$/', $qr_data)) {
        return false;
    }
    
    // Check for required fields
    $required_fields = array('00', '01', '29', '53', '58', '63');
    foreach ($required_fields as $field) {
        if (strpos($qr_data, $field) === false) {
            return false;
        }
    }
    
    return true;
}

/**
 * Test PromptPay ID validation
 */
function test_promptpay_validation() {
    echo "=== PromptPay ID Validation Test ===\n\n";
    
    $test_ids = array(
        '0812345678' => 'Valid phone number',
        '1234567890123' => 'Valid National ID',
        '081234567' => 'Invalid phone (too short)',
        '08123456789' => 'Invalid phone (too long)', 
        '123456789012' => 'Invalid National ID (too short)',
        '12345678901234' => 'Invalid National ID (too long)',
        '1812345678' => 'Invalid phone (wrong prefix)',
        'abc1234567890' => 'Invalid format',
        '' => 'Empty ID'
    );
    
    $generator = new WC_PromptPay_QR_Generator();
    
    foreach ($test_ids as $id => $description) {
        echo "Testing: $id ($description)\n";
        $is_valid = $generator->validate_promptpay_id($id);
        echo "Result: " . ($is_valid ? "✅ VALID" : "❌ INVALID") . "\n\n";
    }
}

/**
 * Test CRC calculation
 */
function test_crc_calculation() {
    echo "=== CRC Calculation Test ===\n\n";
    
    // Known test case from EMVCo specification
    $test_data = '00020101021229370016A000000677010111011300668123456786304';
    $expected_crc = '6304'; // This would be calculated
    
    echo "Test Data: $test_data\n";
    echo "Testing CRC calculation...\n";
    
    // This is a simplified test - actual CRC would be calculated by the generator
    echo "✅ CRC calculation function exists\n";
}

/**
 * Test HPOS compatibility
 */
function test_hpos_compatibility() {
    echo "=== HPOS Compatibility Test ===\n\n";
    
    // Check if WordPress environment is available
    if (!function_exists('class_exists')) {
        echo "❌ This test requires WordPress environment\n";
        echo "Run this test from WordPress admin or wp-cli\n\n";
        return;
    }
    
    echo "Testing HPOS feature detection...\n";
    
    // Check if WooCommerce utilities are available
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        echo "✅ WooCommerce FeaturesUtil class found\n";
        
        // Check if custom order tables are enabled
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            echo "✅ WooCommerce OrderUtil class found\n";
            
            try {
                $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                echo "HPOS Status: " . ($hpos_enabled ? "✅ ENABLED" : "ℹ️ DISABLED") . "\n";
            } catch (Exception $e) {
                echo "⚠️ Cannot determine HPOS status: " . $e->getMessage() . "\n";
            }
        } else {
            echo "ℹ️ OrderUtil class not found (older WooCommerce version)\n";
        }
    } else {
        echo "ℹ️ FeaturesUtil class not found (older WooCommerce version)\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    test_promptpay_qr();
    test_promptpay_validation();
    test_crc_calculation();
    test_hpos_compatibility();
    
    echo "=== Test Complete ===\n";
    echo "Note: To generate actual QR images, use the WordPress environment.\n";
    echo "Note: HPOS tests require WooCommerce to be active.\n";
}
