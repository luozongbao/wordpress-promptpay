# WooCommerce PromptPay Gateway - Usage Guide

## For Store Administrators

### Initial Setup

1. **Install and Activate Plugin**
   - Follow the [Installation Guide](INSTALL.md)
   - Ensure WooCommerce is active and configured

2. **Configure PromptPay Settings**
   - Go to **WooCommerce → Settings → Payments**
   - Click **PromptPay** → **Manage**
   - Fill in required settings:

   ```
   ✅ Enable PromptPay Payment: Checked
   Title: PromptPay
   Description: Pay securely with PromptPay QR code
   PromptPay ID: 0812345678 (your phone/National ID)
   Instructions: Custom payment instructions
   Order Status: On Hold
   ```

3. **Test the Setup**
   - Place a test order
   - Verify QR code appears
   - Test with mobile banking app

### Daily Operations

#### Processing PromptPay Orders

1. **New Order Notification**
   - Orders appear with "On Hold" status
   - Email notification sent to admin
   - Order contains PromptPay payment details

2. **Payment Verification**
   - Check your bank account for incoming transfer
   - Verify amount matches order total
   - Confirm PromptPay ID used for transfer

3. **Update Order Status**
   - Change from "On Hold" to "Processing"
   - Add order note confirming payment received
   - Customer receives confirmation email

#### Order Management Workflow

```
Customer Places Order → On Hold Status → Check Bank Account → 
Confirm Payment → Update to Processing → Fulfill Order
```

### Monitoring and Reports

#### View PromptPay Orders

```php
// Get all PromptPay orders
$orders = wc_get_orders(array(
    'payment_method' => 'promptpay',
    'status' => 'on-hold',
    'limit' => -1
));

foreach ($orders as $order) {
    echo "Order #" . $order->get_order_number() . " - " . $order->get_total() . " THB\n";
}
```

#### Generate Reports

- **WooCommerce → Reports → Orders**
- Filter by payment method: "PromptPay"
- Export data for accounting

### Troubleshooting Common Issues

#### QR Code Not Generating
- Check internet connection
- Verify PromptPay ID format
- Test QR generation manually

#### Orders Stuck "On Hold"
- Reminder: Manual payment confirmation required
- Check bank account regularly
- Set up notifications for new transfers

#### Customer Complaints
- Verify QR code scans correctly
- Check PromptPay ID is active
- Ensure sufficient account balance for transfers

---

## For Customers

### How to Pay with PromptPay

#### Step 1: Select PromptPay at Checkout
1. Add items to cart
2. Go to checkout
3. Select **PromptPay** as payment method
4. Click **Place Order**

#### Step 2: Scan QR Code
1. Order confirmation page displays QR code
2. Open your mobile banking app
3. Select **PromptPay** or **QR Payment**
4. Scan the QR code displayed

#### Step 3: Confirm Payment
1. Banking app shows:
   - Merchant PromptPay ID
   - Order amount
   - Order reference
2. Confirm the transfer
3. Keep receipt for your records

#### Step 4: Wait for Confirmation
- Order status: "On Hold" (awaiting confirmation)
- Merchant will confirm payment manually
- You'll receive email when order is processed

### Alternative: Manual Transfer

If QR scanning doesn't work:

1. **Copy PromptPay ID** (click copy button)
2. **Open banking app manually**
3. **Select PromptPay transfer**
4. **Enter PromptPay ID**
5. **Enter exact amount** from order
6. **Add order number** in reference
7. **Confirm transfer**

### Supported Banking Apps

PromptPay works with all major Thai banks:
- **Bangkok Bank** (Bualuang mBanking)
- **Kasikorn Bank** (K PLUS)
- **Siam Commercial Bank** (SCB Easy)
- **Krung Thai Bank** (Krungthai NEXT)
- **TMB Bank** (TMB Touch)
- **Government Savings Bank** (GSB Mobile)
- **Bank of Ayudhya** (Krungsri Mobile)
- And more...

### FAQ for Customers

**Q: Is PromptPay payment secure?**
A: Yes, PromptPay uses Bank of Thailand's secure system with end-to-end encryption.

**Q: Are there any fees?**
A: Most banks don't charge fees for PromptPay transfers, but check with your bank.

**Q: Can I pay from any bank?**
A: Yes, any Thai bank that supports PromptPay can be used.

**Q: What if I paid wrong amount?**
A: Contact the merchant immediately with your transfer receipt.

**Q: How long until order is processed?**
A: Depends on merchant's verification process, usually within 24 hours.

**Q: Can I get a refund?**
A: Yes, refunds are processed manually by the merchant to your bank account.

---

## For Developers

### Customization Examples

#### Custom Order Processing

```php
// Auto-confirm PromptPay payments (with webhook integration)
add_action('woocommerce_thankyou', function($order_id) {
    $order = wc_get_order($order_id);
    
    if ($order->get_payment_method() === 'promptpay') {
        // Add custom processing logic
        // e.g., integrate with bank API to verify payment
    }
});
```

#### Custom QR Code Styling

```php
// Modify QR code appearance
add_filter('wc_promptpay_qr_size', function($size) {
    return 350; // Custom size
});

add_action('wp_head', function() {
    ?>
    <style>
    .promptpay-qr-code {
        border: 3px solid #007cba;
        border-radius: 10px;
    }
    </style>
    <?php
});
```

#### Custom Payment Instructions

```php
// Dynamic payment instructions based on order
add_filter('wc_promptpay_payment_instructions', function($instructions, $order) {
    $total = $order->get_total();
    
    if ($total > 1000) {
        return 'Large order: Please double-check amount before transfer.';
    }
    
    return $instructions;
}, 10, 2);
```

#### Webhook Integration

```php
// Add webhook endpoint for bank notifications
add_action('rest_api_init', function() {
    register_rest_route('promptpay/v1', '/webhook', array(
        'methods' => 'POST',
        'callback' => 'handle_promptpay_webhook',
        'permission_callback' => '__return_true',
    ));
});

function handle_promptpay_webhook($request) {
    $data = $request->get_json_params();
    
    // Verify webhook signature
    // Parse payment data
    // Update order status automatically
    
    return new WP_REST_Response('OK', 200);
}
```

### API Reference

#### Available Hooks

**Actions:**
- `wc_promptpay_payment_complete` - After successful payment
- `wc_promptpay_qr_generated` - After QR code generation
- `wc_promptpay_order_hold` - When order placed on hold

**Filters:**
- `wc_promptpay_qr_data` - Modify QR code data
- `wc_promptpay_payment_instructions` - Custom instructions
- `wc_promptpay_supported_currencies` - Add currency support

#### Helper Functions

```php
// Get PromptPay gateway instance
$gateway = WC_PromptPay_Helper::get_gateway();

// Validate PromptPay ID
$is_valid = WC_PromptPay_Helper::validate_promptpay_id('0812345678');

// Format PromptPay ID for display
$formatted = WC_PromptPay_Helper::format_promptpay_id('0812345678');

// Generate QR code data
$qr_generator = new WC_PromptPay_QR_Generator();
$qr_data = $qr_generator->generate_qr_data('0812345678', 150.00);
```

---

## Best Practices

### For Merchants

1. **Check Payments Regularly**
   - Monitor bank account multiple times daily
   - Set up mobile banking notifications
   - Use order management system efficiently

2. **Customer Communication**
   - Respond to payment queries quickly
   - Provide clear payment instructions
   - Send confirmation emails promptly

3. **Security**
   - Use SSL certificate
   - Keep plugin updated
   - Monitor for suspicious orders

4. **Testing**
   - Test payment flow regularly
   - Verify QR codes scan correctly
   - Train staff on order processing

### For Developers

1. **Code Quality**
   - Follow WordPress coding standards
   - Implement proper error handling
   - Use WordPress hooks and filters

2. **Security**
   - Sanitize all inputs
   - Use nonces for forms
   - Implement rate limiting

3. **Performance**
   - Cache QR codes when possible
   - Optimize database queries
   - Use efficient image generation

4. **Testing**
   - Unit test critical functions
   - Test on multiple devices
   - Validate EMVCo compliance

---

## Advanced Features

### Multi-Store Setup

For managing multiple PromptPay accounts:

```php
// Different PromptPay IDs per store
add_filter('wc_promptpay_merchant_id', function($promptpay_id) {
    $current_site = get_current_blog_id();
    
    $store_accounts = array(
        1 => '0812345678', // Main store
        2 => '0823456789', // Branch store
    );
    
    return $store_accounts[$current_site] ?? $promptpay_id;
});
```

### Analytics Integration

```php
// Track PromptPay usage
add_action('wc_promptpay_payment_complete', function($order_id) {
    // Google Analytics
    gtag('event', 'purchase', array(
        'transaction_id' => $order_id,
        'payment_method' => 'promptpay',
    ));
    
    // Custom analytics
    update_option('promptpay_monthly_total', 
        get_option('promptpay_monthly_total', 0) + 1
    );
});
```

### Automation

```php
// Auto-update orders with bank API integration
wp_schedule_event(time(), 'hourly', 'check_promptpay_payments');

add_action('check_promptpay_payments', function() {
    // Check pending PromptPay orders
    // Query bank API for new transfers
    // Auto-update matching orders
});
```

---

**Need Help?** 
- Check the [Installation Guide](INSTALL.md)
- Review [README.md](README.md) for technical details
- Report issues on [GitHub](https://github.com/luozongbao/wordpress-promptpay/issues)
