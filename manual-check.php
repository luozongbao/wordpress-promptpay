<?php
/**
 * Manual Gateway Check
 * 
 * Create a temporary admin page to check gateway status
 */

// Add to functions.php temporarily
add_action('admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'PromptPay Check',
        'PromptPay Check',
        'manage_options',
        'promptpay-check',
        function() {
            echo '<div class="wrap"><h1>PromptPay Gateway Check</h1>';
            
            // 1. Check if WooCommerce exists
            if (!function_exists('WC')) {
                echo '<p style="color: red;">❌ WooCommerce not found!</p>';
                echo '</div>';
                return;
            }
            
            // 2. Check currency
            $currency = get_woocommerce_currency();
            echo '<p><strong>Currency:</strong> ' . $currency . ' ' . ($currency === 'THB' ? '✅' : '❌ Should be THB') . '</p>';
            
            // 3. Check if payment gateways are loaded
            $payment_gateways = WC()->payment_gateways();
            if (!$payment_gateways) {
                echo '<p style="color: red;">❌ Payment gateways not loaded!</p>';
                echo '</div>';
                return;
            }
            
            // 4. Get all gateways
            $gateways = $payment_gateways->payment_gateways();
            echo '<p><strong>Total Gateways:</strong> ' . count($gateways) . '</p>';
            
            // 5. Check for PromptPay
            if (isset($gateways['promptpay'])) {
                echo '<p>✅ <strong>PromptPay gateway found!</strong></p>';
                
                $gateway = $gateways['promptpay'];
                echo '<ul>';
                echo '<li>Enabled: ' . ($gateway->enabled === 'yes' ? '✅ Yes' : '❌ No') . '</li>';
                echo '<li>PromptPay ID: ' . (!empty($gateway->promptpay_id) ? '✅ Configured' : '❌ Not set') . '</li>';
                echo '<li>Available: ' . ($gateway->is_available() ? '✅ Yes' : '❌ No') . '</li>';
                echo '</ul>';
            } else {
                echo '<p style="color: red;">❌ <strong>PromptPay gateway NOT found!</strong></p>';
                echo '<p>Available gateways:</p><ul>';
                foreach ($gateways as $id => $gateway) {
                    echo '<li>' . $id . ' - ' . $gateway->get_title() . '</li>';
                }
                echo '</ul>';
            }
            
            // 6. Check available gateways for frontend
            $available = $payment_gateways->get_available_payment_gateways();
            echo '<h3>Available for Frontend:</h3>';
            if (isset($available['promptpay'])) {
                echo '<p>✅ PromptPay is available for customers</p>';
            } else {
                echo '<p style="color: red;">❌ PromptPay is NOT available for customers</p>';
                echo '<p>Available: ' . implode(', ', array_keys($available)) . '</p>';
            }
            
            echo '</div>';
        }
    );
});
?>
