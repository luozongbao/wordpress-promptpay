<?php
/**
 * Quick PromptPay Gateway Diagnostic Script
 * 
 * Add this to your functions.php temporarily or run via wp-cli
 * Usage: Add to functions.php, then go to any admin page to see results
 */

// Only run in admin and for administrators
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Only show once per session
    if (isset($_SESSION['promptpay_debug_shown'])) {
        return;
    }
    $_SESSION['promptpay_debug_shown'] = true;
    
    echo '<div class="notice notice-info is-dismissible">';
    echo '<h3>🔍 PromptPay Gateway Diagnostic</h3>';
    
    // Check 1: WooCommerce
    if (!class_exists('WooCommerce')) {
        echo '<p>❌ <strong>WooCommerce not active!</strong> Install and activate WooCommerce first.</p>';
        echo '</div>';
        return;
    }
    echo '<p>✅ WooCommerce is active</p>';
    
    // Check 2: Currency
    $currency = get_woocommerce_currency();
    if ($currency !== 'THB') {
        echo '<p>❌ <strong>Currency issue!</strong> Current: ' . $currency . '. Required: THB</p>';
        echo '<p>👉 Fix: WooCommerce → Settings → General → Currency = Thai Baht (THB)</p>';
    } else {
        echo '<p>✅ Currency is THB</p>';
    }
    
    // Check 3: Gateway Class
    if (!class_exists('WC_PromptPay_Gateway')) {
        echo '<p>❌ <strong>PromptPay Gateway class not found!</strong> Plugin may not be loaded properly.</p>';
    } else {
        echo '<p>✅ PromptPay Gateway class loaded</p>';
    }
    
    // Check 4: Gateway Registration
    if (function_exists('WC')) {
        $gateways = WC()->payment_gateways()->payment_gateways();
        if (!isset($gateways['promptpay'])) {
            echo '<p>❌ <strong>PromptPay not registered!</strong> Gateway not added to WooCommerce.</p>';
        } else {
            echo '<p>✅ PromptPay gateway registered</p>';
            
            $gateway = $gateways['promptpay'];
            
            // Check enabled
            if ($gateway->enabled !== 'yes') {
                echo '<p>❌ <strong>Gateway disabled!</strong></p>';
                echo '<p>👉 Fix: WooCommerce → Settings → Payments → PromptPay → Enable</p>';
            } else {
                echo '<p>✅ Gateway enabled</p>';
            }
            
            // Check PromptPay ID
            if (empty($gateway->promptpay_id)) {
                echo '<p>❌ <strong>PromptPay ID not configured!</strong></p>';
                echo '<p>👉 Fix: WooCommerce → Settings → Payments → PromptPay → Add PromptPay ID</p>';
            } else {
                echo '<p>✅ PromptPay ID configured: ' . substr($gateway->promptpay_id, 0, 3) . '***</p>';
            }
            
            // Check availability
            if (!$gateway->is_available()) {
                echo '<p>❌ <strong>Gateway not available!</strong> Check logs for details.</p>';
            } else {
                echo '<p>✅ Gateway available</p>';
            }
        }
        
        // Check available gateways on frontend
        $available = WC()->payment_gateways()->get_available_payment_gateways();
        if (isset($available['promptpay'])) {
            echo '<p>✅ PromptPay appears in available gateways</p>';
        } else {
            echo '<p>❌ <strong>PromptPay NOT in available gateways!</strong></p>';
        }
    }
    
    echo '<p><em>Remove this diagnostic code from functions.php when done.</em></p>';
    echo '</div>';
});
?>
