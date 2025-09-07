<?php
/**
 * PromptPay Payment Page Template
 * 
 * @package WC_PromptPay_Gateway
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get order ID from URL parameter
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$payment_token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

if (!$order_id || !$payment_token) {
    wp_die(__('Invalid payment request.', 'wc-promptpay-gateway'));
}

$order = wc_get_order($order_id);
if (!$order) {
    wp_die(__('Order not found.', 'wc-promptpay-gateway'));
}

// Verify token
$stored_token = $order->get_meta('_promptpay_payment_token');
if (!$stored_token || !hash_equals($stored_token, $payment_token)) {
    wp_die(__('Invalid payment token.', 'wc-promptpay-gateway'));
}

// Check if payment is already completed
if ($order->is_paid()) {
    wp_redirect($order->get_checkout_order_received_url());
    exit;
}

// Get PromptPay gateway instance
$gateway = new WC_PromptPay_Gateway();
$promptpay_id = $order->get_meta('_promptpay_id');

if (!$promptpay_id) {
    wp_die(__('PromptPay configuration error.', 'wc-promptpay-gateway'));
}

get_header();
?>

<div class="promptpay-payment-page">
    <div class="container">
        <div class="promptpay-payment-wrapper">
            <header class="payment-header">
                <h1><?php esc_html_e('Complete Your Payment', 'wc-promptpay-gateway'); ?></h1>
                <p class="order-info">
                    <?php 
                    printf(
                        __('Order #%s - Total: %s', 'wc-promptpay-gateway'),
                        $order->get_order_number(),
                        wc_price($order->get_total())
                    ); 
                    ?>
                </p>
            </header>

            <div class="payment-content">
                <div class="payment-instructions">
                    <h2><?php esc_html_e('How to Pay with PromptPay', 'wc-promptpay-gateway'); ?></h2>
                    <ol>
                        <li><?php esc_html_e('Open your mobile banking app', 'wc-promptpay-gateway'); ?></li>
                        <li><?php esc_html_e('Select PromptPay or QR Code payment', 'wc-promptpay-gateway'); ?></li>
                        <li><?php esc_html_e('Scan the QR code below or enter the PromptPay ID manually', 'wc-promptpay-gateway'); ?></li>
                        <li><?php esc_html_e('Confirm the payment amount and complete the transaction', 'wc-promptpay-gateway'); ?></li>
                    </ol>
                </div>

                <div class="promptpay-qr-section">
                    <?php
                    // Generate QR code
                    $qr_generator = new WC_PromptPay_QR_Generator();
                    $amount = $order->get_total();
                    
                    try {
                        $qr_data = $qr_generator->generate_qr_data($promptpay_id, $amount);
                        $qr_image_url = $qr_generator->generate_qr_image($qr_data, $order_id);
                        ?>
                        <div class="qr-code-container">
                            <h3><?php esc_html_e('Scan QR Code', 'wc-promptpay-gateway'); ?></h3>
                            <?php if ($qr_image_url): ?>
                                <div class="qr-image-wrapper">
                                    <img src="<?php echo esc_url($qr_image_url); ?>" 
                                         alt="<?php esc_attr_e('PromptPay QR Code', 'wc-promptpay-gateway'); ?>" 
                                         class="promptpay-qr-image" />
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="manual-payment-info">
                            <h3><?php esc_html_e('Or Pay Manually', 'wc-promptpay-gateway'); ?></h3>
                            <div class="payment-details">
                                <div class="detail-row">
                                    <span class="label"><?php esc_html_e('PromptPay ID:', 'wc-promptpay-gateway'); ?></span>
                                    <span class="value">
                                        <code id="promptpay-id"><?php echo esc_html($promptpay_id); ?></code>
                                        <button type="button" class="copy-btn" onclick="copyToClipboard('promptpay-id')">
                                            <?php esc_html_e('Copy', 'wc-promptpay-gateway'); ?>
                                        </button>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="label"><?php esc_html_e('Amount:', 'wc-promptpay-gateway'); ?></span>
                                    <span class="value amount"><?php echo wc_price($amount); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="label"><?php esc_html_e('Reference:', 'wc-promptpay-gateway'); ?></span>
                                    <span class="value"><?php echo esc_html($order->get_order_number()); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php
                    } catch (Exception $e) {
                        ?>
                        <div class="error-message">
                            <p><?php esc_html_e('Unable to generate QR code. Please use manual payment.', 'wc-promptpay-gateway'); ?></p>
                            <div class="manual-payment-info">
                                <div class="payment-details">
                                    <div class="detail-row">
                                        <span class="label"><?php esc_html_e('PromptPay ID:', 'wc-promptpay-gateway'); ?></span>
                                        <span class="value"><?php echo esc_html($promptpay_id); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label"><?php esc_html_e('Amount:', 'wc-promptpay-gateway'); ?></span>
                                        <span class="value"><?php echo wc_price($amount); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <div class="payment-status-section">
                    <div class="status-checker">
                        <h3><?php esc_html_e('Payment Status', 'wc-promptpay-gateway'); ?></h3>
                        <div id="payment-status" class="status-pending">
                            <span class="status-icon">⏳</span>
                            <span class="status-text"><?php esc_html_e('Waiting for payment...', 'wc-promptpay-gateway'); ?></span>
                        </div>
                        <button type="button" id="check-payment-btn" class="check-payment-btn">
                            <?php esc_html_e('Check Payment Status', 'wc-promptpay-gateway'); ?>
                        </button>
                    </div>
                </div>

                <div class="payment-actions">
                    <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="back-to-checkout">
                        <?php esc_html_e('← Back to Checkout', 'wc-promptpay-gateway'); ?>
                    </a>
                    <button type="button" id="manual-confirm-btn" class="manual-confirm-btn" style="display: none;">
                        <?php esc_html_e('I have completed the payment', 'wc-promptpay-gateway'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh payment status
let paymentCheckInterval;
let checkCount = 0;
const maxChecks = 60; // Check for 5 minutes (every 5 seconds)

function checkPaymentStatus() {
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'promptpay_check_payment',
            order_id: '<?php echo $order_id; ?>',
            token: '<?php echo $payment_token; ?>',
            nonce: '<?php echo wp_create_nonce('promptpay_check_payment'); ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.data.paid) {
                // Payment confirmed
                document.getElementById('payment-status').innerHTML = 
                    '<span class="status-icon">✅</span><span class="status-text"><?php esc_html_e('Payment confirmed!', 'wc-promptpay-gateway'); ?></span>';
                document.getElementById('payment-status').className = 'status-completed';
                clearInterval(paymentCheckInterval);
                
                // Redirect to order received page
                setTimeout(() => {
                    window.location.href = data.data.redirect_url;
                }, 2000);
            }
        } else {
            console.error('Payment check failed:', data.data);
        }
    })
    .catch(error => {
        console.error('Error checking payment status:', error);
    });

    checkCount++;
    if (checkCount >= maxChecks) {
        clearInterval(paymentCheckInterval);
        // Show manual confirmation button after timeout
        document.getElementById('manual-confirm-btn').style.display = 'inline-block';
    }
}

// Start auto-checking payment status
paymentCheckInterval = setInterval(checkPaymentStatus, 5000);

// Manual check button
document.getElementById('check-payment-btn').addEventListener('click', checkPaymentStatus);

// Copy to clipboard function
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.textContent;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            alert('<?php esc_html_e('Copied to clipboard!', 'wc-promptpay-gateway'); ?>');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('<?php esc_html_e('Copied to clipboard!', 'wc-promptpay-gateway'); ?>');
    }
}

// Manual payment confirmation
document.getElementById('manual-confirm-btn').addEventListener('click', function() {
    if (confirm('<?php esc_html_e('Please confirm that you have completed the payment.', 'wc-promptpay-gateway'); ?>')) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'promptpay_manual_confirm',
                order_id: '<?php echo $order_id; ?>',
                token: '<?php echo $payment_token; ?>',
                nonce: '<?php echo wp_create_nonce('promptpay_manual_confirm'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('<?php esc_html_e('Thank you! Your payment will be verified soon.', 'wc-promptpay-gateway'); ?>');
                window.location.href = data.data.redirect_url;
            } else {
                alert('<?php esc_html_e('Error processing request. Please try again.', 'wc-promptpay-gateway'); ?>');
            }
        });
    }
});
</script>

<?php get_footer(); ?>
