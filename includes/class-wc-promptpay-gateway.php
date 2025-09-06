<?php
/**
 * WooCommerce PromptPay Gateway Class
 *
 * @package WC_PromptPay_Gateway
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_PromptPay_Gateway class extends WC_Payment_Gateway
 */
class WC_PromptPay_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'promptpay';
        $this->icon               = WC_PROMPTPAY_PLUGIN_URL . 'assets/images/promptpay-logo.svg';
        $this->has_fields         = false;
        $this->method_title       = __('PromptPay', 'wc-promptpay-gateway');
        $this->method_description = __('Accept payments via PromptPay using QR code generation following Bank of Thailand EMVCo specification.', 'wc-promptpay-gateway');
        $this->supports           = array(
            'products'
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');
        $this->enabled            = $this->get_option('enabled');
        $this->promptpay_id       = $this->get_option('promptpay_id');
        $this->instructions       = $this->get_option('instructions');
        $this->order_status       = $this->get_option('order_status', 'on-hold');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'wc-promptpay-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable PromptPay Payment', 'wc-promptpay-gateway'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'wc-promptpay-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wc-promptpay-gateway'),
                'default'     => __('PromptPay', 'wc-promptpay-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'wc-promptpay-gateway'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'wc-promptpay-gateway'),
                'default'     => __('Pay securely using PromptPay QR code. Scan the QR code with your mobile banking app.', 'wc-promptpay-gateway'),
                'desc_tip'    => true,
            ),
            'promptpay_id' => array(
                'title'       => __('PromptPay ID', 'wc-promptpay-gateway'),
                'type'        => 'text',
                'description' => __('Enter your PromptPay ID (10-digit phone number or 13-digit National ID).', 'wc-promptpay-gateway'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => '0812345678 or 1234567890123'
            ),
            'instructions' => array(
                'title'       => __('Instructions', 'wc-promptpay-gateway'),
                'type'        => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'wc-promptpay-gateway'),
                'default'     => __('Please scan the QR code below with your mobile banking app to complete the payment. The order will be processed after payment confirmation.', 'wc-promptpay-gateway'),
                'desc_tip'    => true,
            ),
            'order_status' => array(
                'title'       => __('Order Status After Checkout', 'wc-promptpay-gateway'),
                'type'        => 'select',
                'description' => __('Order status after customer completes checkout.', 'wc-promptpay-gateway'),
                'default'     => 'on-hold',
                'desc_tip'    => true,
                'options'     => array(
                    'on-hold'    => __('On Hold', 'wc-promptpay-gateway'),
                    'processing' => __('Processing', 'wc-promptpay-gateway'),
                    'pending'    => __('Pending Payment', 'wc-promptpay-gateway'),
                )
            ),
        );
    }

    /**
     * Validate PromptPay ID field
     */
    public function validate_promptpay_id_field($key, $value) {
        $value = sanitize_text_field($value);
        
        // Remove any spaces or dashes
        $clean_value = preg_replace('/[\s\-]/', '', $value);
        
        if (!empty($clean_value)) {
            // Check if it's a valid phone number (10 digits starting with 0)
            if (preg_match('/^0[0-9]{9}$/', $clean_value)) {
                return $clean_value;
            }
            // Check if it's a valid National ID (13 digits)
            elseif (preg_match('/^[0-9]{13}$/', $clean_value)) {
                return $clean_value;
            } else {
                WC_Admin_Settings::add_error(__('PromptPay ID must be a 10-digit phone number (starting with 0) or 13-digit National ID.', 'wc-promptpay-gateway'));
                return '';
            }
        }
        
        return $clean_value;
    }

    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__('Order not found.', 'wc-promptpay-gateway'), 'error');
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }

        // Validate PromptPay ID
        if (empty($this->promptpay_id)) {
            wc_add_notice(__('PromptPay ID is not configured. Please contact the store administrator.', 'wc-promptpay-gateway'), 'error');
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }

        // Mark order as on-hold
        $order->update_status($this->order_status, __('Awaiting PromptPay payment confirmation.', 'wc-promptpay-gateway'));

        // Add PromptPay specific meta data
        $order->update_meta_data('_promptpay_id', $this->promptpay_id);
        $order->update_meta_data('_promptpay_payment_method', 'qr_code');
        $order->update_meta_data('_promptpay_order_reference', WC_PromptPay_Helper::get_order_reference($order));
        $order->save();

        // Add order note
        $order->add_order_note(
            sprintf(
                __('PromptPay payment initiated. QR code generated for amount: %s THB. PromptPay ID: %s', 'wc-promptpay-gateway'),
                $order->get_total(),
                $this->mask_promptpay_id($this->promptpay_id)
            )
        );

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);

        // Remove cart
        WC()->cart->empty_cart();

        // Return success and redirect to the thank you page
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page($order_id) {
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }

        $this->display_promptpay_qr($order_id);
    }

    /**
     * Add content to the WC emails.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false) {
        if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
            if ($plain_text) {
                echo esc_html(wp_strip_all_tags($this->instructions)) . "\n\n";
                echo esc_html(sprintf(__('PromptPay ID: %s', 'wc-promptpay-gateway'), $this->promptpay_id)) . "\n";
                echo esc_html(sprintf(__('Amount: %s THB', 'wc-promptpay-gateway'), $order->get_total())) . "\n";
            } else {
                echo wp_kses_post(wpautop(wptexturize($this->instructions)));
                echo '<p><strong>' . esc_html(sprintf(__('PromptPay ID: %s', 'wc-promptpay-gateway'), $this->promptpay_id)) . '</strong></p>';
                echo '<p><strong>' . esc_html(sprintf(__('Amount: %s THB', 'wc-promptpay-gateway'), $order->get_total())) . '</strong></p>';
            }
        }
    }

    /**
     * Display PromptPay QR code
     */
    private function display_promptpay_qr($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || empty($this->promptpay_id)) {
            return;
        }

        $amount = $order->get_total();
        $qr_generator = new WC_PromptPay_QR_Generator();
        
        try {
            $qr_data = $qr_generator->generate_qr_data($this->promptpay_id, $amount);
            $qr_image_url = $qr_generator->generate_qr_image($qr_data, $order_id);
            
            echo '<div class="promptpay-payment-info">';
            echo '<h3>' . esc_html__('PromptPay Payment', 'wc-promptpay-gateway') . '</h3>';
            
            echo '<div class="promptpay-qr-container">';
            if ($qr_image_url) {
                echo '<div class="promptpay-qr-code">';
                echo '<img src="' . esc_url($qr_image_url) . '" alt="' . esc_attr__('PromptPay QR Code', 'wc-promptpay-gateway') . '" />';
                echo '</div>';
            }
            
            echo '<div class="promptpay-details">';
            echo '<p><strong>' . esc_html__('PromptPay ID:', 'wc-promptpay-gateway') . '</strong></p>';
            echo '<p class="promptpay-id-display">';
            echo '<span class="promptpay-id">' . esc_html($this->promptpay_id) . '</span>';
            echo '<button type="button" class="promptpay-copy-btn" data-copy="' . esc_attr($this->promptpay_id) . '">' . esc_html__('Copy', 'wc-promptpay-gateway') . '</button>';
            echo '</p>';
            
            echo '<p><strong>' . esc_html__('Amount:', 'wc-promptpay-gateway') . '</strong> ' . wc_price($amount) . '</p>';
            echo '<p><strong>' . esc_html__('Order Reference:', 'wc-promptpay-gateway') . '</strong> ' . esc_html($order->get_order_number()) . '</p>';
            echo '</div>';
            echo '</div>';
            
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="woocommerce-error">';
            echo esc_html__('Unable to generate QR code. Please use the PromptPay ID for manual transfer.', 'wc-promptpay-gateway');
            echo '</div>';
            
            echo '<div class="promptpay-manual-info">';
            echo '<p><strong>' . esc_html__('PromptPay ID:', 'wc-promptpay-gateway') . '</strong> ' . esc_html($this->promptpay_id) . '</p>';
            echo '<p><strong>' . esc_html__('Amount:', 'wc-promptpay-gateway') . '</strong> ' . wc_price($amount) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Mask PromptPay ID for security (show only first 3 and last 2 digits)
     */
    private function mask_promptpay_id($promptpay_id) {
        if (strlen($promptpay_id) <= 5) {
            return $promptpay_id;
        }
        
        $first_part = substr($promptpay_id, 0, 3);
        $last_part = substr($promptpay_id, -2);
        $middle_length = strlen($promptpay_id) - 5;
        
        return $first_part . str_repeat('*', $middle_length) . $last_part;
    }

    /**
     * Check if this gateway is available
     */
    public function is_available() {
        // Check parent availability first
        if (!parent::is_available()) {
            return false;
        }

        // Check if WooCommerce currency is supported
        if (!WC_PromptPay_Helper::is_currency_supported()) {
            return false;
        }

        // For admin settings page, always show the gateway
        if (is_admin() && !wp_doing_ajax()) {
            return true;
        }

        // For frontend, check if PromptPay ID is configured
        if (empty($this->promptpay_id)) {
            // Log for debugging
            if (WC_PromptPay_Helper::is_development()) {
                WC_PromptPay_Helper::log('PromptPay gateway not available: PromptPay ID not configured', 'debug');
            }
            return false;
        }

        return true;
    }
}
