You are an expert WordPress plugin developer. I need you to create a complete WordPress plugin that integrates PromptPay payment gateway with WooCommerce for Thailand market.

**Plugin Requirements:**

1. **Plugin Structure:**
   - Plugin Name: "WooCommerce PromptPay Gateway"
   - Plugin Folder: `woocommerce-promptpay-gateway`
   - Main File: `woocommerce-promptpay-gateway.php`
   - Include proper WordPress plugin headers
   - Follow WordPress coding standards

2. **Core Functionality:**
   - Extend WC_Payment_Gateway class
   - Register as WooCommerce payment method
   - Admin settings page with fields:
     - Enable/Disable toggle
     - Title (default: "PromptPay")
     - Description for customers
     - PromptPay ID (phone number or national ID)
     - Instructions text
   - Generate PromptPay QR code following Bank of Thailand EMVCo specification

3. **PromptPay QR Generation:**
   - Follow Thailand Bank EMVCo QR standard
   - Support phone number (10 digits) and national ID (13 digits) formats
   - Include order amount in QR payload
   - Calculate CRC16-CCITT checksum correctly
   - Generate QR code image using PHP (suggest library)

4. **Payment Flow:**
   - Display PromptPay option in checkout
   - Show QR code on order confirmation page
   - Set order status to "on-hold" after checkout
   - Display payment instructions to customer
   - Include order reference number

5. **Additional Features:**
   - Responsive QR code display
   - Copy PromptPay ID button for manual transfer
   - Order notes with payment instructions
   - Admin order management integration
   - Proper error handling and validation

6. **Technical Requirements:**
   - WordPress 5.0+ compatibility
   - WooCommerce 3.0+ compatibility
   - PHP 7.4+ support
   - Proper sanitization and security
   - Internationalization ready (Thai/English)
   - Mobile-friendly QR display

**Deliverables:**
- Complete plugin file structure
- All PHP files with proper documentation
- Installation and setup instructions
- Usage guide for merchants

**PromptPay QR Specification Reference:**
Use Bank of Thailand EMVCo QR standard with these data objects:
- 00: Payload Format Indicator
- 01: Point of Initiation Method  
- 29: Merchant Account Information (PromptPay)
- 54: Transaction Amount
- 58: Country Code (TH)
- 63: CRC (calculated using CRC16-CCITT)

Please provide complete, production-ready code with proper error handling and WordPress best practices.
```

---

## 🔧 **Extended Prompt (สำหรับ Advanced Features)**

```
ADDITIONAL REQUIREMENTS (Phase 2):

7. **Enhanced Features:**
   - QR code expiration timer (optional)
   - Multiple PromptPay accounts support
   - Transaction reference generation
   - Email notifications with QR code
   - Admin dashboard for payment tracking
   - Bulk order status updates
   - Payment slip upload functionality

8. **Integration Options:**
   - Webhook endpoint for bank API integration (future)
   - REST API endpoints for mobile app integration
   - Shortcode support for custom pages
   - Widget for displaying PromptPay info

9. **Security & Performance:**
   - Nonce verification for all forms
   - Rate limiting for QR generation
   - QR code caching mechanism
   - Proper data validation and sanitization
   - SQL injection prevention

10. **Localization:**
    - Thai language support
    - RTL text support
    - Currency formatting (THB)
    - Date/time localization
```

---

## 📋 **Quick Prompt (สำหรับ MVP Version)**

```
Create a minimal WordPress plugin for WooCommerce PromptPay payment gateway:

1. Extend WC_Payment_Gateway
2. Admin settings: enable/disable, title, PromptPay ID, instructions
3. Generate PromptPay QR code following Thailand EMVCo standard
4. Display QR on checkout confirmation
5. Set order status to "on-hold"
6. Include CRC16-CCITT calculation
7. Mobile-responsive QR display

Provide complete plugin code with installation instructions.
```

---
