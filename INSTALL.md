# WooCommerce PromptPay Gateway - Installation Guide

## Quick Installation

### Method 1: WordPress Admin (Recommended)

1. **Download the Plugin**
   - Download `woocommerce-promptpay-gateway.zip` from releases
   - Or clone: `git clone https://github.com/luozongbao/wordpress-promptpay.git`

2. **Install via WordPress Admin**
   - Go to **Plugins → Add New → Upload Plugin**
   - Choose the ZIP file and click **Install Now**
   - Click **Activate Plugin**

3. **Configure PromptPay**
   - Navigate to **WooCommerce → Settings → Payments**
   - Find **PromptPay** and click **Manage**
   - Configure your settings (see Configuration section below)

### Method 2: FTP/Manual Installation

1. **Upload Files**
   ```bash
   # Extract plugin to WordPress plugins directory
   cd /path/to/wordpress/wp-content/plugins/
   unzip woocommerce-promptpay-gateway.zip
   # or
   git clone https://github.com/luozongbao/wordpress-promptpay.git
   ```

2. **Set Permissions**
   ```bash
   chmod -R 755 wordpress-promptpay/
   chown -R www-data:www-data wordpress-promptpay/
   ```

3. **Activate Plugin**
   - Go to WordPress Admin → **Plugins**
   - Find "WooCommerce PromptPay Gateway"
   - Click **Activate**

## Prerequisites

### System Requirements

- **WordPress**: 5.0 or higher
- **WooCommerce**: 3.0 or higher  
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **SSL Certificate**: Recommended for production

### PHP Extensions Required

- `gd` or `imagick` (for QR code generation)
- `curl` (for external API calls)
- `json` (for data processing)
- `mbstring` (for string handling)

### Check Requirements

Add this to your functions.php to check requirements:

```php
function check_promptpay_requirements() {
    $requirements = array(
        'PHP Version' => version_compare(PHP_VERSION, '7.4', '>='),
        'WooCommerce' => class_exists('WooCommerce'),
        'GD Extension' => extension_loaded('gd'),
        'cURL Extension' => extension_loaded('curl'),
        'JSON Extension' => extension_loaded('json'),
        'mbstring Extension' => extension_loaded('mbstring'),
    );
    
    foreach ($requirements as $requirement => $status) {
        echo $requirement . ': ' . ($status ? '✅ OK' : '❌ Missing') . "\n";
    }
}
```

## Configuration

### Basic Setup

1. **Enable PromptPay**
   - ✅ Check "Enable PromptPay Payment"

2. **Payment Method Details**
   - **Title**: `PromptPay` (customer-facing name)
   - **Description**: `Pay securely with PromptPay QR code`

3. **PromptPay ID** (Required)
   - **Phone Number**: `0812345678` (10 digits, starts with 0)
   - **National ID**: `1234567890123` (13 digits)

4. **Payment Instructions**
   ```
   Please scan the QR code with your mobile banking app to complete payment. 
   Your order will be processed after payment confirmation.
   ```

5. **Order Status**
   - **Recommended**: `On Hold` (awaiting payment confirmation)

### Advanced Configuration

#### Currency Settings

Ensure WooCommerce currency is set to **Thai Baht (THB)**:

```php
// In wp-config.php or functions.php
add_filter('woocommerce_currency', function() {
    return 'THB';
});
```

#### QR Code Customization

```php
// Custom QR code size
add_filter('wc_promptpay_qr_size', function($size) {
    return 400; // 400x400 pixels
});

// Custom order reference prefix
add_filter('wc_promptpay_order_reference_prefix', function($prefix) {
    return 'MYSHOP';
});
```

#### File Permissions

Ensure the uploads directory is writable:

```bash
chmod 755 /path/to/wordpress/wp-content/uploads/
chmod 755 /path/to/wordpress/wp-content/uploads/promptpay-qr/
```

## Testing Setup

### Test Environment

1. **Test PromptPay ID**
   - Use a test phone number: `0123456789`
   - Or test National ID: `1234567890123`

2. **Test Mode Configuration**
   ```php
   // Enable debug mode
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   
   // PromptPay test mode
   add_filter('wc_promptpay_test_mode', '__return_true');
   ```

3. **QR Code Testing**
   - Place a test order
   - Verify QR code generates correctly
   - Test QR code with banking app (use small amount)

### Validation Checklist

- [ ] Plugin activates without errors
- [ ] PromptPay appears in payment methods
- [ ] QR code generates on checkout
- [ ] Copy button works for PromptPay ID
- [ ] Mobile display is responsive
- [ ] Order status updates correctly
- [ ] Email notifications work

## Troubleshooting

### Common Issues

**1. Plugin Won't Activate**
```bash
# Check PHP error logs
tail -f /var/log/php_errors.log

# Check WordPress debug log
tail -f /path/to/wordpress/wp-content/debug.log
```

**2. QR Code Not Generating**
- Check internet connection
- Verify uploads directory permissions
- Test with different QR service

**3. PromptPay ID Validation Fails**
```php
// Test PromptPay ID validation
$helper = new WC_PromptPay_Helper();
$result = $helper->validate_promptpay_id('0812345678');
var_dump($result);
```

**4. Payment Method Not Showing**
- Ensure WooCommerce is active
- Check currency is THB
- Verify PromptPay is enabled

### Debug Mode

Enable comprehensive debugging:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);

// PromptPay specific debugging
add_filter('wc_promptpay_debug', '__return_true');
```

### Log Files

Check these log files for issues:

- `/wp-content/debug.log` - WordPress debug log
- `/wp-content/uploads/wc-logs/` - WooCommerce logs
- Server error logs (location varies by hosting)

## Security Considerations

### File Permissions

```bash
# Plugin files
chmod 644 *.php
chmod 644 *.css
chmod 644 *.js
chmod 755 includes/
chmod 755 assets/

# WordPress uploads
chmod 755 wp-content/uploads/
chmod 755 wp-content/uploads/promptpay-qr/
```

### Security Headers

Add to `.htaccess` in uploads/promptpay-qr/:

```apache
# Prevent direct access to QR files
Options -Indexes
<FilesMatch "\.(php|phtml|pl|py|jsp|asp|sh|cgi)$">
    Deny from all
</FilesMatch>
```

### SSL Certificate

Ensure SSL is properly configured:

```php
// Force SSL on checkout
add_filter('woocommerce_force_ssl_checkout', '__return_true');
```

## Performance Optimization

### QR Code Caching

```php
// Enable QR code caching
add_filter('wc_promptpay_enable_cache', '__return_true');

// Cache duration (in seconds)
add_filter('wc_promptpay_cache_duration', function() {
    return 3600; // 1 hour
});
```

### Cleanup Scheduled Task

```php
// Schedule QR file cleanup
if (!wp_next_scheduled('wc_promptpay_cleanup')) {
    wp_schedule_event(time(), 'daily', 'wc_promptpay_cleanup');
}
```

## Multi-language Setup

### Thai Language Support

1. **Install Thai Language Pack**
   - Go to **Settings → General → Site Language**
   - Select **ไทย (Thai)**

2. **Configure PromptPay in Thai**
   ```php
   // Thai translations
   add_filter('wc_promptpay_thai_translations', function($translations) {
       return array(
           'title' => 'พร้อมเพย์',
           'description' => 'ชำระเงินด้วย QR Code พร้อมเพย์',
           'instructions' => 'กรุณาสแกน QR Code ด้วยแอปธนาคารของคุณ'
       );
   });
   ```

## Staging to Production

### Migration Checklist

- [ ] Test all functionality on staging
- [ ] Update PromptPay ID to production account
- [ ] Verify SSL certificate
- [ ] Test with real bank account (small amount)
- [ ] Update DNS settings
- [ ] Monitor error logs

### Production Settings

```php
// Production configuration
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

// Cache optimization
define('WP_CACHE', true);
```

## Support

If you encounter issues during installation:

1. **Check Requirements** - Ensure all prerequisites are met
2. **Review Logs** - Check WordPress and server error logs
3. **Test Environment** - Try on a clean WordPress installation
4. **GitHub Issues** - Report bugs at [GitHub Issues](https://github.com/luozongbao/wordpress-promptpay/issues)
5. **Documentation** - Refer to plugin documentation

---

**Next Steps**: After installation, proceed to [Usage Guide](USAGE.md) for detailed instructions on using the plugin.
