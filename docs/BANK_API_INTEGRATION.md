# PromptPay Bank API Integration Guide

This document explains how to integrate with Thai bank APIs for automatic PromptPay payment verification.

## Overview

The PromptPay Gateway plugin supports integration with various Thai bank APIs to automatically verify payments. This eliminates the need for manual payment confirmation and provides real-time payment status updates.

## Supported Banks

### 1. SCB (Siam Commercial Bank) Easy API
- **Documentation**: https://developer.scb.co.th/
- **Features**: Transaction verification, real-time notifications
- **Requirements**: SCB Easy API credentials

### 2. Bangkok Bank Open API
- **Documentation**: https://developer.bangkokbank.com/
- **Features**: Payment verification, transaction history
- **Requirements**: Bangkok Bank API credentials

### 3. Krung Thai Bank Open API
- **Documentation**: https://openapi.ktb.co.th/
- **Features**: PromptPay transaction verification
- **Requirements**: KTB API credentials

### 4. Custom API
- **Features**: Integration with any custom payment verification service
- **Requirements**: Custom API endpoint and authentication

## Setup Instructions

### Step 1: Obtain Bank API Credentials

1. Register with your bank's developer portal
2. Apply for API access (may require business verification)
3. Obtain API credentials (endpoint, API key, secret)
4. Test the API using bank's documentation

### Step 2: Configure Plugin

1. Go to **WooCommerce → PromptPay Payments** in your WordPress admin
2. Select your bank API provider
3. Enter the API endpoint URL
4. Enter your API key/credentials
5. Enable auto-verification if desired
6. Save settings

### Step 3: Test Integration

1. Create a test order with PromptPay payment
2. Use the "Verify Payment" button in the order admin
3. Check that the API connection works correctly

## API Integration Details

### Request Format

Most bank APIs expect requests in this format:

```json
{
    "promptpay_id": "0899999999",
    "amount": "100.00",
    "date_from": "2024-01-01 00:00:00",
    "date_to": "2024-01-01 23:59:59",
    "order_reference": "PP-12345"
}
```

### Response Format

Successful verification responses typically include:

```json
{
    "status": "success",
    "verified": true,
    "transaction_id": "TXN123456789",
    "amount": "100.00",
    "timestamp": "2024-01-01T12:00:00Z",
    "reference": "REF987654321"
}
```

## Security Considerations

1. **API Keys**: Store API credentials securely
2. **HTTPS**: Always use HTTPS for API communication
3. **Rate Limiting**: Respect bank API rate limits
4. **Error Handling**: Implement proper error handling
5. **Logging**: Log API requests for debugging (exclude sensitive data)

## Custom API Integration

If you're using a custom payment verification service, ensure your API supports:

1. **Payment Verification Endpoint**: To check if payment was received
2. **Transaction Search**: To find payments by PromptPay ID and amount
3. **Webhook Support**: For real-time payment notifications (optional)

### Custom API Example

```php
// Custom API endpoint: POST /verify-payment
{
    "promptpay_id": "0899999999",
    "amount": "100.00",
    "order_id": "12345",
    "order_number": "PP-12345"
}

// Expected response:
{
    "verified": true,
    "transaction_id": "TXN123456789",
    "amount": "100.00",
    "reference": "REF987654321",
    "timestamp": "2024-01-01T12:00:00Z"
}
```

## Troubleshooting

### Common Issues

1. **API Connection Failed**
   - Check API endpoint URL
   - Verify API credentials
   - Ensure server can make outbound HTTPS requests

2. **Payment Not Found**
   - Verify PromptPay ID format
   - Check payment amount matches exactly
   - Ensure sufficient time between payment and verification

3. **Rate Limit Exceeded**
   - Reduce verification frequency
   - Implement exponential backoff
   - Contact bank for higher rate limits

### Debug Mode

Enable WordPress debug mode to see detailed API logs:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for API request/response details.

## Webhooks (Advanced)

For real-time payment notifications, some banks support webhooks:

1. Configure webhook URL in bank developer portal
2. Implement webhook handler in your plugin
3. Verify webhook signatures for security
4. Update order status immediately upon payment

### Webhook Example

```php
// Webhook endpoint: POST /wp-admin/admin-ajax.php?action=promptpay_webhook
add_action('wp_ajax_nopriv_promptpay_webhook', 'handle_promptpay_webhook');
add_action('wp_ajax_promptpay_webhook', 'handle_promptpay_webhook');

function handle_promptpay_webhook() {
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);
    
    // Verify webhook signature
    if (!verify_webhook_signature($payload)) {
        wp_die('Unauthorized', 401);
    }
    
    // Process payment notification
    if ($data['event'] === 'payment.completed') {
        $order_id = $data['order_id'];
        $order = wc_get_order($order_id);
        
        if ($order && !$order->is_paid()) {
            $order->payment_complete($data['transaction_id']);
            $order->add_order_note('Payment verified via bank webhook.');
        }
    }
    
    wp_die('OK', 200);
}
```

## Support

For technical support with bank API integration:

1. Check bank API documentation
2. Contact bank developer support
3. Review plugin debug logs
4. Submit issues on GitHub: https://github.com/luozongbao/wordpress-promptpay

## Legal Notice

- Ensure compliance with bank API terms of service
- Implement proper data protection measures
- Consider PCI DSS requirements if handling card data
- Consult legal counsel for regulatory compliance
