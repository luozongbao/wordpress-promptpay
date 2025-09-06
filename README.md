# WooCommerce PromptPay Gateway

A WordPress plugin that integrates PromptPay payment gateway with WooCommerce for the Thailand market.

## Features

- **EMVCo Compliant**: Follows Bank of Thailand EMVCo QR specification
- **Flexible PromptPay ID**: Supports both phone numbers (10 digits) and National ID (13 digits)
- **QR Code Generation**: Automatic QR code generation with amount and order details
- **Responsive Design**: Mobile-friendly QR code display
- **Copy Functionality**: One-click copy PromptPay ID for manual transfer
- **Order Management**: Integrates with WooCommerce order status workflow
- **Multi-language**: Thai and English language support
- **Security**: Proper sanitization, validation, and nonce verification

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.4 or higher
- SSL certificate (recommended for security)

## Installation

1. **Download the Plugin**
   ```bash
   cd /path/to/wordpress/wp-content/plugins/
   git clone https://github.com/luozongbao/wordpress-promptpay.git
   ```

2. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "WooCommerce PromptPay Gateway" 
   - Click "Activate"

3. **Configure PromptPay Settings**
   - Go to WooCommerce → Settings → Payments
   - Click on "PromptPay" 
   - Configure your settings:
     - Enable the payment method
     - Enter your PromptPay ID (phone number or National ID)
     - Customize title and description
     - Set payment instructions

## Configuration

### Basic Settings

| Setting | Description | Example |
|---------|-------------|---------|
| **Enable/Disable** | Toggle PromptPay payment method | ✓ Enabled |
| **Title** | Payment method name shown to customers | "PromptPay" |
| **Description** | Payment method description | "Pay with PromptPay QR code" |
| **PromptPay ID** | Your phone number or National ID | `0812345678` or `1234567890123` |
| **Instructions** | Payment instructions for customers | Custom message |
| **Order Status** | Order status after checkout | On Hold |

### PromptPay ID Formats

**Phone Number Format:**
- Must be 10 digits starting with 0
- Example: `0812345678`
- Will be converted to international format in QR code

**National ID Format:**
- Must be 13 digits
- Example: `1234567890123`
- Includes checksum validation

### Supported Currency

- **THB (Thai Baht)** - Required for PromptPay transactions

## Usage

### For Customers

1. **Select PromptPay** at checkout
2. **Complete Order** - Order status becomes "On Hold"
3. **Scan QR Code** with mobile banking app
4. **Transfer Payment** - Amount is pre-filled
5. **Await Confirmation** - Merchant confirms payment manually

### For Merchants

1. **Receive Order** with "On Hold" status
2. **Check Bank Account** for incoming payment
3. **Update Order Status** to "Processing" after payment confirmation
4. **Process Order** normally

## Technical Details

### EMVCo QR Code Structure

The plugin generates QR codes following Bank of Thailand EMVCo specification:

```
00: Payload Format Indicator (01)
01: Point of Initiation Method (11=static, 12=dynamic)
29: Merchant Account Information (PromptPay)
  00: Globally Unique Identifier (A000000677010111)
  01: PromptPay ID (formatted)
53: Transaction Currency (764=THB)
54: Transaction Amount (if specified)
58: Country Code (TH)
63: CRC16-CCITT Checksum
```

### File Structure

```
woocommerce-promptpay-gateway/
├── woocommerce-promptpay-gateway.php  # Main plugin file
├── includes/
│   ├── class-wc-promptpay-gateway.php      # Gateway class
│   ├── class-wc-promptpay-qr-generator.php # QR generator
│   └── class-wc-promptpay-helper.php       # Helper functions
├── assets/
│   ├── css/
│   │   ├── promptpay.css    # Frontend styles
│   │   └── admin.css        # Admin styles
│   ├── js/
│   │   └── promptpay.js     # Frontend JavaScript
│   └── images/
│       └── promptpay-logo.png
├── languages/
│   └── wc-promptpay-gateway.pot
└── README.md
```

### Hooks and Filters

**Actions:**
- `woocommerce_update_options_payment_gateways_promptpay`
- `woocommerce_thankyou_promptpay`
- `woocommerce_email_before_order_table`

**Filters:**
- `wc_promptpay_supported_currencies`
- `wc_promptpay_order_reference_prefix`

## Customization

### Custom QR Code Size

```php
add_filter('wc_promptpay_qr_size', function($size) {
    return 400; // Custom size in pixels
});
```

### Custom Order Reference

```php
add_filter('wc_promptpay_order_reference_prefix', function($prefix) {
    return 'SHOP'; // Custom prefix
});
```

### Additional Currency Support

```php
add_filter('wc_promptpay_supported_currencies', function($currencies) {
    $currencies[] = 'USD'; // Add USD support
    return $currencies;
});
```

## Security Features

- **Input Sanitization**: All user inputs are sanitized
- **Nonce Verification**: CSRF protection for all forms
- **Validation**: PromptPay ID format validation
- **File Protection**: QR code directory protection
- **SQL Injection Prevention**: Prepared statements

## Troubleshooting

### Common Issues

**QR Code Not Generating:**
- Check internet connection
- Verify PromptPay ID format
- Check file permissions for uploads directory

**Payment Method Not Showing:**
- Ensure WooCommerce is active
- Check currency is set to THB
- Verify PromptPay ID is configured

**Invalid PromptPay ID:**
- Phone: Must be 10 digits starting with 0
- National ID: Must be 13 digits with valid checksum

### Debug Mode

Enable WordPress debug mode in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs in `/wp-content/debug.log`

## Development

### Local Development Setup

1. **Clone Repository**
   ```bash
   git clone https://github.com/luozongbao/wordpress-promptpay.git
   cd wordpress-promptpay
   ```

2. **Install Dependencies**
   ```bash
   composer install  # If using Composer
   npm install       # If using Node.js
   ```

3. **Development Tools**
   - PHP_CodeSniffer for WordPress coding standards
   - PHPUnit for testing
   - Webpack for asset building

### Testing

```bash
# PHP Unit Tests
phpunit

# Code Standards
phpcs --standard=WordPress .

# Code Fixing
phpcbf --standard=WordPress .
```

## Changelog

### Version 1.0.0
- Initial release
- EMVCo QR code generation
- WooCommerce integration
- Mobile-responsive design
- Thai/English localization

## Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature/new-feature`
3. Commit changes: `git commit -am 'Add new feature'`
4. Push to branch: `git push origin feature/new-feature`
5. Submit pull request

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Support

- **Issues**: [GitHub Issues](https://github.com/luozongbao/wordpress-promptpay/issues)
- **Documentation**: [Wiki](https://github.com/luozongbao/wordpress-promptpay/wiki)
- **Email**: support@example.com

## Credits

- **Bank of Thailand** - EMVCo QR specification
- **WooCommerce** - Payment gateway framework
- **WordPress** - Plugin platform

---

**Note**: This plugin is for educational and development purposes. For production use, ensure thorough testing and compliance with local regulations.
