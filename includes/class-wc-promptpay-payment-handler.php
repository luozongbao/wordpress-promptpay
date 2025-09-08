<?php
/**
 * WooCommerce PromptPay Payment Handler Class
 *
 * @package WC_PromptPay_Gateway
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_PromptPay_Payment_Handler class
 * Handles payment page routing and verification
 */
class WC_PromptPay_Payment_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_payment_page'));
        add_action('wp_ajax_promptpay_check_payment', array($this, 'ajax_check_payment'));
        add_action('wp_ajax_nopriv_promptpay_check_payment', array($this, 'ajax_check_payment'));
        add_action('wp_ajax_promptpay_manual_confirm', array($this, 'ajax_manual_confirm'));
        add_action('wp_ajax_nopriv_promptpay_manual_confirm', array($this, 'ajax_manual_confirm'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_payment_page_styles'));
    }

    /**
     * Add rewrite rules for payment page
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^promptpay-payment/?$',
            'index.php?promptpay_payment=1',
            'top'
        );
        add_rewrite_tag('%promptpay_payment%', '([^&]+)');
    }

    /**
     * Handle payment page template
     */
    public function handle_payment_page() {
        global $wp_query;
        
        if (!empty($wp_query->query_vars['promptpay_payment'])) {
            $template_path = plugin_dir_path(__FILE__) . '../templates/payment-page.php';
            
            if (file_exists($template_path)) {
                include $template_path;
                exit;
            } else {
                wp_die(__('Payment page template not found.', 'wc-promptpay-gateway'));
            }
        }
    }

    /**
     * Enqueue payment page styles
     */
    public function enqueue_payment_page_styles() {
        global $wp_query;
        
        if (!empty($wp_query->query_vars['promptpay_payment'])) {
            wp_enqueue_style(
                'promptpay-payment-page',
                WC_PROMPTPAY_PLUGIN_URL . 'assets/css/payment-page.css',
                array(),
                WC_PROMPTPAY_VERSION
            );
        }
    }

    /**
     * AJAX handler for checking payment status
     */
    public function ajax_check_payment() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'promptpay_check_payment')) {
            wp_die(__('Security check failed.', 'wc-promptpay-gateway'));
        }

        $order_id = intval($_POST['order_id']);
        $token = sanitize_text_field($_POST['token']);

        if (!$order_id || !$token) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
            return;
        }

        // Verify token
        $stored_token = $order->get_meta('_promptpay_payment_token');
        if (!$stored_token || !hash_equals($stored_token, $token)) {
            wp_send_json_error(array('message' => 'Invalid token'));
            return;
        }

        // Check if payment is completed
        $is_paid = $order->is_paid() || $order->has_status('processing') || $order->has_status('completed');

        if ($is_paid) {
            wp_send_json_success(array(
                'paid' => true,
                'redirect_url' => $order->get_checkout_order_received_url()
            ));
        } else {
            // Here you would integrate with bank API to check payment status
            // For now, we'll check manually confirmed payments
            $manual_confirmed = $order->get_meta('_promptpay_manual_confirmed');
            
            if ($manual_confirmed) {
                // Mark order as processing if manually confirmed
                $order->update_status('processing', __('Payment manually confirmed via PromptPay.', 'wc-promptpay-gateway'));
                
                wp_send_json_success(array(
                    'paid' => true,
                    'redirect_url' => $order->get_checkout_order_received_url()
                ));
            } else {
                // TODO: Integrate with actual bank API here
                // $payment_verified = $this->verify_payment_with_bank_api($order);
                
                wp_send_json_success(array(
                    'paid' => false,
                    'message' => 'Payment not yet confirmed'
                ));
            }
        }
    }

    /**
     * AJAX handler for manual payment confirmation
     */
    public function ajax_manual_confirm() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'promptpay_manual_confirm')) {
            wp_die(__('Security check failed.', 'wc-promptpay-gateway'));
        }

        $order_id = intval($_POST['order_id']);
        $token = sanitize_text_field($_POST['token']);

        if (!$order_id || !$token) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
            return;
        }

        // Verify token
        $stored_token = $order->get_meta('_promptpay_payment_token');
        if (!$stored_token || !hash_equals($stored_token, $token)) {
            wp_send_json_error(array('message' => 'Invalid token'));
            return;
        }

        // Mark as manually confirmed (pending admin verification)
        $order->update_meta_data('_promptpay_manual_confirmed', time());
        $order->update_status('on-hold', __('Customer confirmed PromptPay payment. Awaiting admin verification.', 'wc-promptpay-gateway'));
        $order->add_order_note(__('Customer manually confirmed payment completion.', 'wc-promptpay-gateway'));
        $order->save();

        // Send admin notification email
        $this->send_admin_payment_notification($order);

        wp_send_json_success(array(
            'message' => 'Payment confirmation received',
            'redirect_url' => $order->get_checkout_order_received_url()
        ));
    }

    /**
     * Send admin notification about manual payment confirmation
     */
    private function send_admin_payment_notification($order) {
        $admin_email = get_option('admin_email');
        $subject = sprintf(__('[%s] PromptPay Payment Confirmation Required - Order #%s', 'wc-promptpay-gateway'), 
                          get_bloginfo('name'), 
                          $order->get_order_number());
        
        $message = sprintf(
            __("A customer has confirmed completing a PromptPay payment that requires verification:\n\nOrder: #%s\nAmount: %s\nPromptPay ID: %s\nCustomer: %s\n\nPlease verify the payment in your banking app and update the order status accordingly.\n\nView Order: %s", 'wc-promptpay-gateway'),
            $order->get_order_number(),
            wc_price($order->get_total()),
            $order->get_meta('_promptpay_id'),
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            admin_url('post.php?post=' . $order->get_id() . '&action=edit')
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Verify payment with bank API (placeholder for future implementation)
     */
    private function verify_payment_with_bank_api($order) {
        // TODO: Implement actual bank API integration
        // This would involve:
        // 1. Getting transaction details from bank API
        // 2. Matching transaction amount and reference
        // 3. Confirming payment status
        
        return false; // Placeholder return
    }

    /**
     * Flush rewrite rules on activation
     */
    public static function flush_rewrite_rules() {
        add_rewrite_rule(
            '^promptpay-payment/?$',
            'index.php?promptpay_payment=1',
            'top'
        );
        add_rewrite_tag('%promptpay_payment%', '([^&]+)');
        flush_rewrite_rules();
    }
}
