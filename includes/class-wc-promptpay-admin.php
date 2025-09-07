<?php
/**
 * WooCommerce PromptPay Admin Class
 *
 * @package WC_PromptPay_Gateway
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_PromptPay_Admin class
 * Handles admin functionality for PromptPay payments
 */
class WC_PromptPay_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_order_meta_boxes'));
        add_action('wp_ajax_promptpay_verify_payment', array($this, 'ajax_verify_payment'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * Add meta boxes to order edit page
     */
    public function add_order_meta_boxes() {
        global $post, $theorder;
        
        $order = $theorder ? $theorder : wc_get_order($post->ID);
        
        if ($order && $order->get_payment_method() === 'promptpay') {
            add_meta_box(
                'promptpay-payment-details',
                __('PromptPay Payment Details', 'wc-promptpay-gateway'),
                array($this, 'order_meta_box_content'),
                'shop_order',
                'side',
                'high'
            );
        }
    }

    /**
     * Content for order meta box
     */
    public function order_meta_box_content($post) {
        $order = wc_get_order($post->ID);
        $promptpay_id = $order->get_meta('_promptpay_id');
        $payment_token = $order->get_meta('_promptpay_payment_token');
        $manual_confirmed = $order->get_meta('_promptpay_manual_confirmed');
        $verified_payment = $order->get_meta('_promptpay_verified_payment');
        
        ?>
        <div class="promptpay-payment-details">
            <p><strong><?php esc_html_e('PromptPay ID:', 'wc-promptpay-gateway'); ?></strong><br>
            <code><?php echo esc_html($promptpay_id); ?></code></p>
            
            <p><strong><?php esc_html_e('Amount:', 'wc-promptpay-gateway'); ?></strong><br>
            <?php echo wc_price($order->get_total()); ?></p>
            
            <p><strong><?php esc_html_e('Order Reference:', 'wc-promptpay-gateway'); ?></strong><br>
            <?php echo esc_html($order->get_order_number()); ?></p>
            
            <?php if ($manual_confirmed): ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('Customer manually confirmed payment completion.', 'wc-promptpay-gateway'); ?></p>
                    <p><small><?php printf(__('Confirmed on: %s', 'wc-promptpay-gateway'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $manual_confirmed)); ?></small></p>
                </div>
            <?php endif; ?>
            
            <?php if ($verified_payment): ?>
                <div class="notice notice-success inline">
                    <p><?php esc_html_e('Payment verified via bank API.', 'wc-promptpay-gateway'); ?></p>
                    <p><small><?php printf(__('Verified on: %s', 'wc-promptpay-gateway'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $verified_payment['timestamp'])); ?></small></p>
                </div>
            <?php endif; ?>
            
            <div class="promptpay-actions" style="margin-top: 15px;">
                <?php if (!$order->is_paid()): ?>
                    <button type="button" id="verify-payment-btn" class="button button-primary" data-order-id="<?php echo $order->get_id(); ?>">
                        <?php esc_html_e('Verify Payment', 'wc-promptpay-gateway'); ?>
                    </button>
                    <button type="button" id="mark-paid-btn" class="button" data-order-id="<?php echo $order->get_id(); ?>">
                        <?php esc_html_e('Mark as Paid', 'wc-promptpay-gateway'); ?>
                    </button>
                <?php endif; ?>
                
                <button type="button" id="regenerate-qr-btn" class="button" data-order-id="<?php echo $order->get_id(); ?>">
                    <?php esc_html_e('Regenerate QR Code', 'wc-promptpay-gateway'); ?>
                </button>
            </div>
            
            <div id="payment-verification-result" style="margin-top: 15px;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#verify-payment-btn').on('click', function() {
                var orderId = $(this).data('order-id');
                var button = $(this);
                
                button.prop('disabled', true).text('<?php esc_html_e('Verifying...', 'wc-promptpay-gateway'); ?>');
                
                $.post(ajaxurl, {
                    action: 'promptpay_verify_payment',
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('promptpay_verify_payment'); ?>'
                }, function(response) {
                    $('#payment-verification-result').html(response.data.message);
                    
                    if (response.success && response.data.verified) {
                        location.reload();
                    } else {
                        button.prop('disabled', false).text('<?php esc_html_e('Verify Payment', 'wc-promptpay-gateway'); ?>');
                    }
                });
            });
            
            $('#mark-paid-btn').on('click', function() {
                if (confirm('<?php esc_html_e('Are you sure you want to mark this order as paid?', 'wc-promptpay-gateway'); ?>')) {
                    var orderId = $(this).data('order-id');
                    
                    $.post(ajaxurl, {
                        action: 'promptpay_verify_payment',
                        order_id: orderId,
                        force_paid: true,
                        nonce: '<?php echo wp_create_nonce('promptpay_verify_payment'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for verifying payment
     */
    public function ajax_verify_payment() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'promptpay_verify_payment')) {
            wp_die(__('Security check failed.', 'wc-promptpay-gateway'));
        }

        $order_id = intval($_POST['order_id']);
        $force_paid = isset($_POST['force_paid']) && $_POST['force_paid'];

        if (!$order_id) {
            wp_send_json_error(array('message' => 'Invalid order ID'));
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
            return;
        }

        if ($force_paid) {
            // Manually mark as paid
            $order->payment_complete();
            $order->add_order_note(__('Payment manually verified by administrator.', 'wc-promptpay-gateway'));
            $order->update_meta_data('_promptpay_manually_verified', time());
            $order->save();
            
            wp_send_json_success(array(
                'verified' => true,
                'message' => '<div class="notice notice-success inline"><p>' . __('Order marked as paid successfully.', 'wc-promptpay-gateway') . '</p></div>'
            ));
            return;
        }

        // TODO: Implement actual bank API verification
        // For now, we'll simulate the process
        $payment_verified = $this->verify_with_bank_api($order);
        
        if ($payment_verified) {
            $order->payment_complete();
            $order->add_order_note(__('Payment verified via bank API.', 'wc-promptpay-gateway'));
            $order->update_meta_data('_promptpay_verified_payment', array(
                'timestamp' => time(),
                'method' => 'bank_api',
                'transaction_id' => $payment_verified['transaction_id'] ?? '',
            ));
            $order->save();
            
            wp_send_json_success(array(
                'verified' => true,
                'message' => '<div class="notice notice-success inline"><p>' . __('Payment verified successfully via bank API.', 'wc-promptpay-gateway') . '</p></div>'
            ));
        } else {
            wp_send_json_success(array(
                'verified' => false,
                'message' => '<div class="notice notice-warning inline"><p>' . __('No matching payment found in bank records.', 'wc-promptpay-gateway') . '</p></div>'
            ));
        }
    }

    /**
     * Verify payment with bank API
     */
    private function verify_with_bank_api($order) {
        // Load the bank API class if not already loaded
        if (!class_exists('WC_PromptPay_Bank_API')) {
            require_once WC_PROMPTPAY_PLUGIN_PATH . 'includes/class-wc-promptpay-bank-api.php';
        }

        $bank_api = new WC_PromptPay_Bank_API();
        return $bank_api->verify_payment($order);
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            global $post;
            if ($post && $post->post_type === 'shop_order') {
                wp_enqueue_script('jquery');
            }
        }
    }

    /**
     * Add admin menu for PromptPay settings
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('PromptPay Payments', 'wc-promptpay-gateway'),
            __('PromptPay Payments', 'wc-promptpay-gateway'),
            'manage_woocommerce',
            'promptpay-payments',
            array($this, 'admin_page_content')
        );
    }

    /**
     * Admin page content
     */
    public function admin_page_content() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('PromptPay Payments', 'wc-promptpay-gateway'); ?></h1>
            
            <div class="card">
                <h2><?php esc_html_e('Bank API Integration', 'wc-promptpay-gateway'); ?></h2>
                <p><?php esc_html_e('Configure bank API settings for automatic payment verification.', 'wc-promptpay-gateway'); ?></p>
                
                <form method="post" action="options.php">
                    <?php settings_fields('promptpay_bank_api_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Bank API Provider', 'wc-promptpay-gateway'); ?></th>
                            <td>
                                <select name="promptpay_bank_api_provider">
                                    <option value=""><?php esc_html_e('Select Bank API', 'wc-promptpay-gateway'); ?></option>
                                    <option value="scb" <?php selected(get_option('promptpay_bank_api_provider'), 'scb'); ?>>SCB Easy API</option>
                                    <option value="bbl" <?php selected(get_option('promptpay_bank_api_provider'), 'bbl'); ?>>Bangkok Bank API</option>
                                    <option value="ktb" <?php selected(get_option('promptpay_bank_api_provider'), 'ktb'); ?>>Krung Thai Bank API</option>
                                    <option value="custom" <?php selected(get_option('promptpay_bank_api_provider'), 'custom'); ?>>Custom API</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('API Endpoint', 'wc-promptpay-gateway'); ?></th>
                            <td>
                                <input type="url" name="promptpay_bank_api_endpoint" value="<?php echo esc_attr(get_option('promptpay_bank_api_endpoint')); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('Bank API endpoint URL for payment verification.', 'wc-promptpay-gateway'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('API Key', 'wc-promptpay-gateway'); ?></th>
                            <td>
                                <input type="password" name="promptpay_bank_api_key" value="<?php echo esc_attr(get_option('promptpay_bank_api_key')); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('Your bank API key for authentication.', 'wc-promptpay-gateway'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Auto Verification', 'wc-promptpay-gateway'); ?></th>
                            <td>
                                <input type="checkbox" name="promptpay_auto_verification" value="1" <?php checked(get_option('promptpay_auto_verification'), 1); ?> />
                                <p class="description"><?php esc_html_e('Automatically verify payments using bank API every 5 minutes.', 'wc-promptpay-gateway'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e('Payment Status Overview', 'wc-promptpay-gateway'); ?></h2>
                <?php $this->display_payment_overview(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display payment overview
     */
    private function display_payment_overview() {
        global $wpdb;
        
        // Get PromptPay orders statistics
        $orders = $wpdb->get_results("
            SELECT p.ID, p.post_status, pm.meta_value as payment_method
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_payment_method'
            WHERE p.post_type = 'shop_order' AND pm.meta_value = 'promptpay'
            ORDER BY p.post_date DESC
            LIMIT 50
        ");
        
        $pending_count = 0;
        $completed_count = 0;
        $failed_count = 0;
        
        foreach ($orders as $order_data) {
            switch ($order_data->post_status) {
                case 'wc-on-hold':
                    $pending_count++;
                    break;
                case 'wc-processing':
                case 'wc-completed':
                    $completed_count++;
                    break;
                case 'wc-failed':
                case 'wc-cancelled':
                    $failed_count++;
                    break;
            }
        }
        
        ?>
        <div class="promptpay-stats">
            <div class="stat-box">
                <h3><?php echo $pending_count; ?></h3>
                <p><?php esc_html_e('Pending Payments', 'wc-promptpay-gateway'); ?></p>
            </div>
            <div class="stat-box">
                <h3><?php echo $completed_count; ?></h3>
                <p><?php esc_html_e('Completed Payments', 'wc-promptpay-gateway'); ?></p>
            </div>
            <div class="stat-box">
                <h3><?php echo $failed_count; ?></h3>
                <p><?php esc_html_e('Failed/Cancelled', 'wc-promptpay-gateway'); ?></p>
            </div>
        </div>
        
        <style>
        .promptpay-stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            flex: 1;
            border: 1px solid #dee2e6;
        }
        .stat-box h3 {
            font-size: 2em;
            margin: 0 0 10px;
            color: #495057;
        }
        .stat-box p {
            margin: 0;
            color: #6c757d;
            font-weight: 500;
        }
        </style>
        <?php
    }
}
