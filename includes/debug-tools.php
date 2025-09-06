<?php
/**
 * PromptPay Gateway Troubleshooting Tools
 *
 * @package WC_PromptPay_Gateway
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add troubleshooting shortcode
 */
add_shortcode('promptpay_debug', 'promptpay_debug_shortcode');

function promptpay_debug_shortcode($atts) {
    // Only show for administrators
    if (!current_user_can('manage_woocommerce')) {
        return '<p>Access denied. Administrator privileges required.</p>';
    }

    $debug_info = WC_PromptPay_Helper::debug_gateway_availability();
    
    ob_start();
    ?>
    <div class="promptpay-debug-info" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 6px; font-family: monospace;">
        <h3>PromptPay Gateway Debug Information</h3>
        
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #007cba; color: white;">
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Check</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Status</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($debug_info as $check => $status): 
                    $status_icon = '❓';
                    $action = '';
                    
                    if (is_bool($status)) {
                        $status_icon = $status ? '✅' : '❌';
                        $status_text = $status ? 'OK' : 'FAIL';
                    } else {
                        $status_text = $status;
                        $status_icon = '📋';
                    }
                    
                    // Add specific actions for failed checks
                    switch ($check) {
                        case 'woocommerce_active':
                            if (!$status) $action = 'Install and activate WooCommerce plugin';
                            break;
                        case 'currency_supported':
                            if (!$status) $action = 'Go to WooCommerce → Settings → General and set currency to THB';
                            break;
                        case 'gateway_enabled':
                            if (!$status) $action = 'Go to WooCommerce → Settings → Payments → PromptPay and enable the gateway';
                            break;
                        case 'promptpay_id_configured':
                            if (!$status) $action = 'Configure your PromptPay ID in gateway settings';
                            break;
                    }
                ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html(str_replace('_', ' ', ucfirst($check))); ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $status_icon . ' ' . esc_html($status_text); ?></td>
                    <td style="padding: 10px; border: 1px solid #ddd; color: #666;"><?php echo esc_html($action); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #007cba;">
            <h4>Quick Fixes:</h4>
            <ol>
                <li><strong>Gateway not showing:</strong> Check currency is set to THB and PromptPay ID is configured</li>
                <li><strong>Settings not saving:</strong> Check file permissions and WordPress memory limit</li>
                <li><strong>QR not generating:</strong> Ensure server has internet access and GD extension installed</li>
            </ol>
        </div>
        
        <div style="margin-top: 15px;">
            <strong>Debug Mode:</strong> <?php echo defined('WP_DEBUG') && WP_DEBUG ? '✅ Enabled' : '❌ Disabled'; ?><br>
            <strong>Plugin Version:</strong> <?php echo WC_PROMPTPAY_VERSION; ?><br>
            <strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?><br>
            <strong>WooCommerce Version:</strong> <?php echo defined('WC_VERSION') ? WC_VERSION : 'Not detected'; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Add admin menu for troubleshooting
 */
add_action('admin_menu', 'promptpay_add_debug_menu');

function promptpay_add_debug_menu() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        add_submenu_page(
            'woocommerce',
            'PromptPay Debug',
            'PromptPay Debug',
            'manage_woocommerce',
            'promptpay-debug',
            'promptpay_debug_page'
        );
    }
}

function promptpay_debug_page() {
    echo '<div class="wrap">';
    echo '<h1>PromptPay Gateway Debug</h1>';
    echo do_shortcode('[promptpay_debug]');
    echo '</div>';
}
