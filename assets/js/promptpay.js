/**
 * WooCommerce PromptPay Gateway Frontend JavaScript
 */

(function($) {
    'use strict';

    var PromptPayGateway = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initCopyButtons();
            this.initQRRefresh();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            $(document).ready(function() {
                PromptPayGateway.onReady();
            });
        },

        /**
         * On document ready
         */
        onReady: function() {
            // Initialize copy functionality
            this.initCopyButtons();
            
            // Auto-refresh QR code if needed
            this.initQRRefresh();
            
            // Mobile optimization
            this.optimizeForMobile();
        },

        /**
         * Initialize copy buttons
         */
        initCopyButtons: function() {
            $('.promptpay-copy-btn').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var textToCopy = $button.data('copy');
                var originalText = $button.text();
                
                if (!textToCopy) {
                    return;
                }

                // Try to copy to clipboard
                if (PromptPayGateway.copyToClipboard(textToCopy)) {
                    // Success feedback
                    $button.addClass('copied').text(promptpay_params.copied_text);
                    
                    // Reset button after 2 seconds
                    setTimeout(function() {
                        $button.removeClass('copied').text(originalText);
                    }, 2000);
                } else {
                    // Fallback for older browsers
                    PromptPayGateway.showCopyFallback(textToCopy);
                }
            });
        },

        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard && window.isSecureContext) {
                // Modern async clipboard API
                navigator.clipboard.writeText(text).then(function() {
                    console.log('PromptPay ID copied to clipboard');
                }).catch(function(err) {
                    console.error('Failed to copy PromptPay ID: ', err);
                });
                return true;
            } else {
                // Fallback for older browsers
                return this.copyToClipboardFallback(text);
            }
        },

        /**
         * Fallback copy method
         */
        copyToClipboardFallback: function(text) {
            try {
                var textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                var result = document.execCommand('copy');
                document.body.removeChild(textArea);
                
                return result;
            } catch (err) {
                console.error('Fallback copy failed: ', err);
                return false;
            }
        },

        /**
         * Show copy fallback modal/alert
         */
        showCopyFallback: function(text) {
            var message = 'Please copy the PromptPay ID manually:\n\n' + text;
            
            if (window.prompt) {
                window.prompt(message, text);
            } else {
                alert(message);
            }
        },

        /**
         * Initialize QR code refresh functionality
         */
        initQRRefresh: function() {
            var $refreshBtn = $('.promptpay-qr-refresh');
            
            if ($refreshBtn.length) {
                $refreshBtn.on('click', function(e) {
                    e.preventDefault();
                    PromptPayGateway.refreshQRCode($(this));
                });
            }

            // Auto-refresh QR code every 5 minutes to prevent expiration
            var autoRefreshInterval = 5 * 60 * 1000; // 5 minutes
            if ($('.promptpay-qr-code').length) {
                setInterval(function() {
                    PromptPayGateway.refreshQRCode();
                }, autoRefreshInterval);
            }
        },

        /**
         * Refresh QR code
         */
        refreshQRCode: function($button) {
            var $qrContainer = $('.promptpay-qr-code');
            var $qrImage = $qrContainer.find('img');
            
            if (!$qrImage.length) {
                return;
            }

            // Show loading state
            if ($button) {
                $button.prop('disabled', true).html('<span class="promptpay-loading"></span> Refreshing...');
            }

            // Add timestamp to force refresh
            var currentSrc = $qrImage.attr('src');
            var separator = currentSrc.indexOf('?') > -1 ? '&' : '?';
            var newSrc = currentSrc + separator + 'refresh=' + Date.now();
            
            // Preload new image
            var newImage = new Image();
            newImage.onload = function() {
                $qrImage.attr('src', newSrc);
                
                // Reset button
                if ($button) {
                    $button.prop('disabled', false).text('Refresh QR Code');
                }
            };
            
            newImage.onerror = function() {
                console.error('Failed to refresh QR code');
                
                // Reset button
                if ($button) {
                    $button.prop('disabled', false).text('Refresh QR Code');
                }
            };
            
            newImage.src = newSrc;
        },

        /**
         * Mobile optimization
         */
        optimizeForMobile: function() {
            if (window.innerWidth <= 768) {
                // Adjust QR code size for mobile
                var $qrImage = $('.promptpay-qr-code img');
                if ($qrImage.length) {
                    $qrImage.css({
                        'max-width': '250px',
                        'width': '100%'
                    });
                }

                // Make copy button more touch-friendly
                $('.promptpay-copy-btn').css({
                    'min-height': '44px',
                    'padding': '12px 20px'
                });
            }
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            type = type || 'info';
            
            var $notification = $('<div class="promptpay-notification promptpay-' + type + '">')
                .text(message)
                .hide();
            
            $('.promptpay-payment-info').prepend($notification);
            $notification.slideDown().delay(3000).slideUp(function() {
                $(this).remove();
            });
        },

        /**
         * Validate PromptPay ID format (client-side helper)
         */
        validatePromptPayId: function(promptpayId) {
            // Remove spaces and dashes
            var cleanId = promptpayId.replace(/[\s\-]/g, '');
            
            // Check phone number format (10 digits starting with 0)
            if (/^0[0-9]{9}$/.test(cleanId)) {
                return { valid: true, type: 'phone' };
            }
            
            // Check National ID format (13 digits)
            if (/^[0-9]{13}$/.test(cleanId)) {
                return { valid: true, type: 'national_id' };
            }
            
            return { valid: false, type: 'unknown' };
        },

        /**
         * Format PromptPay ID for display
         */
        formatPromptPayId: function(promptpayId) {
            var cleanId = promptpayId.replace(/[\s\-]/g, '');
            
            if (cleanId.length === 10) {
                // Phone number: 0XX-XXX-XXXX
                return cleanId.substring(0, 3) + '-' + 
                       cleanId.substring(3, 6) + '-' + 
                       cleanId.substring(6);
            } else if (cleanId.length === 13) {
                // National ID: X-XXXX-XXXXX-XX-X
                return cleanId.substring(0, 1) + '-' + 
                       cleanId.substring(1, 5) + '-' + 
                       cleanId.substring(5, 10) + '-' + 
                       cleanId.substring(10, 12) + '-' + 
                       cleanId.substring(12);
            }
            
            return cleanId;
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        PromptPayGateway.init();
    });

    // Expose to global scope for external access
    window.PromptPayGateway = PromptPayGateway;

})(jQuery);
