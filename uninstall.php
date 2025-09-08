<?php
/**
 * Uninstall script for WooCommerce PromptPay Gateway
 * 
 * @package WC_PromptPay_Gateway
 * @since 1.0.0
 */

// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('woocommerce_promptpay_settings');

// Delete any transients
delete_transient('wc_promptpay_qr_cache');

// Clean up QR code files
$upload_dir = wp_upload_dir();
$promptpay_dir = $upload_dir['basedir'] . '/promptpay-qr';

if (file_exists($promptpay_dir)) {
    // Remove all QR code files
    $files = glob($promptpay_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    
    // Remove directory
    rmdir($promptpay_dir);
}

// Clean up any custom database tables if created in future versions
global $wpdb;

// Clean up order meta data - HPOS compatible
if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
    \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
    // HPOS is enabled - clean meta from orders table
    $wpdb->query("DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key LIKE '_promptpay_%'");
} else {
    // Traditional post meta
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_promptpay_%'");
}

// Example: Drop custom table (if exists in future versions)
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}promptpay_transactions");

// Clean up any scheduled events
wp_clear_scheduled_hook('wc_promptpay_cleanup_qr_files');

// Remove any custom user meta related to PromptPay
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wc_promptpay_%'");

// Clean up order meta data - HPOS compatible
if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
    \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
    // HPOS is enabled - clean meta from orders table
    $wpdb->query("DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key LIKE '_promptpay_%'");
} else {
    // Traditional post meta
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_promptpay_%'");
}

// Clear any cached data
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}
