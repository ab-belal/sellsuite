/**
 * SellSuite Point Redemption
 * 
 * Handles real-time point redemption calculation on checkout page
 * 
 * @package SellSuite
 */

(function($) {
    'use strict';

    const PointRedemption = {
        // Configuration
        pointsPerCurrencyUnit: 0,
        maxRedeemablePercentage: 0,
        currency: '$',
        currencySymbol: '$',
        cartSubtotal: 0,  // Product subtotal (without shipping, taxes, fees) - used for points
        orderTotal: 0,    // Full order total (including shipping) - used for max redeemable calc
        maxRedeemable: 0,
        availablePoints: 0,
        redemptionApplied: false,
        redemptionId: null,
        
        /**
         * Initialize point redemption
         */
        init: function() {
            if (!window.sellsuiteRedemptionData) {
                console.warn('SellSuite redemption data not loaded');
                return;
            }

            // Get data from localized script
            this.pointsPerCurrencyUnit = parseFloat(window.sellsuiteRedemptionData.conversion_rate) || 1;
            this.maxRedeemablePercentage = parseFloat(window.sellsuiteRedemptionData.max_redeemable_percentage) || 20;
            this.currency = window.sellsuiteRedemptionData.currency || 'USD';
            this.currencySymbol = window.sellsuiteRedemptionData.currency_symbol || '$';
            this.currencyPosition = window.sellsuiteRedemptionData.currency_position || 'right';
            this.availablePoints = parseInt(window.sellsuiteRedemptionData.available_points) || 0;
            this.cartSubtotal = parseFloat(window.sellsuiteRedemptionData.cart_subtotal) || 0;  // Product subtotal for points
            this.orderTotal = parseFloat(window.sellsuiteRedemptionData.order_total) || 0;      // Full total for max redeemable
            
            // Calculate max redeemable amount based on full order total (including shipping)
            this.maxRedeemable = (this.orderTotal * this.maxRedeemablePercentage) / 100;
            
            // Bind events
            this.bindEvents();
            this.updateDisplay();

            // Listen for order total changes
            $(document.body).on('updated_checkout', function() {
                PointRedemption.onCheckoutUpdate();
            });
        },

        /**
         * Bind input and button events
         */
        bindEvents: function() {
            const self = this;

            // Point input change
            $(document).on('input', '#sellsuite-redeem-points-input', function() {
                self.onPointsInput($(this));
            });

            // Apply button
            $(document).on('click', '#sellsuite-apply-redemption-btn', function(e) {
                e.preventDefault();
                self.applyRedemption();
            });

            // Cancel button
            $(document).on('click', '.sellsuite-cancel-redemption-btn', function(e) {
                e.preventDefault();
                self.cancelRedemption();
            });
        },

        /**
         * Handle point input change
         */
        onPointsInput: function($input) {
            const value = $input.val().trim();
            
            // Clear if empty
            if (!value) {
                this.updateCalculation(0);
                return;
            }

            // Parse and validate
            let points = parseInt(value);
            if (isNaN(points) || points < 0) {
                points = 0;
            }

            // Validate against available points
            if (points > this.availablePoints) {
                points = this.availablePoints;
                $input.val(points);
            }

            this.updateCalculation(points);
        },

        /**
         * Format currency amount based on position
         */
        formatCurrency: function(amount) {
            const formatted = amount.toFixed(2);
            if (this.currencyPosition === 'left') {
                return `${this.currencySymbol}${formatted}`;
            } else if (this.currencyPosition === 'left_space') {
                return `${this.currencySymbol} ${formatted}`;
            } else if (this.currencyPosition === 'right_space') {
                return `${formatted} ${this.currencySymbol}`;
            } else {
                // 'right' or default
                return `${formatted}${this.currencySymbol}`;
            }
        },

        /**
         * Update real-time calculation display
         */
        updateCalculation: function(points) {
            const discountValue = points / this.pointsPerCurrencyUnit;
            const remainingPoints = this.availablePoints - points;
            const newTotal = Math.max(0, this.orderTotal - discountValue);
            const $displayArea = $('#sellsuite-redemption-calculation');

            if (!$displayArea.length) {
                return;
            }

            // Check against max redeemable
            let warning = '';
            if (discountValue > this.maxRedeemable) {
                const maxPoints = Math.floor(this.maxRedeemable * this.pointsPerCurrencyUnit);
                warning = `<div class="sellsuite-redemption-warning">
                    <span class="dashicons dashicons-warning"></span>
                    Maximum redeemable is ${this.formatCurrency(this.maxRedeemable)} (${maxPoints} points) for this order.
                </div>`;
            }

            // Update display
            let html = '';
            if (points > 0) {
                html = `
                    <div class="sellsuite-calculation-row">
                        <span class="label">${points} points ÷ ${this.pointsPerCurrencyUnit} = <strong>${this.formatCurrency(discountValue)} discount</strong></span>
                    </div>
                    <div class="sellsuite-calculation-row">
                        <span class="label">Subtotal: <strong>${this.formatCurrency(this.orderTotal)}</strong></span>
                    </div>
                    <div class="sellsuite-calculation-row">
                        <span class="label">Discount: <strong>-${this.formatCurrency(discountValue)}</strong></span>
                    </div>
                    <div class="sellsuite-calculation-row" style="border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px;">
                        <span class="label">New Total: <strong style="color: #28a745; font-size: 16px;">${this.formatCurrency(newTotal)}</strong></span>
                    </div>
                    <div class="sellsuite-calculation-row">
                        <span class="label">Available after: <strong>${remainingPoints} points</strong></span>
                    </div>
                    ${warning}
                `;
            } else {
                html = '<p class="sellsuite-no-selection">Enter points amount to see discount calculation</p>';
            }

            $displayArea.html(html);
        },

        /**
         * Apply redemption via AJAX
         */
        applyRedemption: function() {
            const points = parseInt($('#sellsuite-redeem-points-input').val()) || 0;

            // Validate
            if (points <= 0) {
                this.showError('Please enter a valid point amount');
                return;
            }

            if (points > this.availablePoints) {
                this.showError('Insufficient points available');
                return;
            }

            const discountValue = points / this.pointsPerCurrencyUnit;
            if (discountValue > this.maxRedeemable) {
                this.showError(`Maximum redeemable is ${this.formatCurrency(this.maxRedeemable)} for this order`);
                return;
            }

            // Show loading
            const $btn = $('#sellsuite-apply-redemption-btn');
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Applying...');

            // Get nonce and order ID
            const nonce = window.sellsuiteRedemptionData.nonce || '';
            const orderId = this.getOrderId();

            // Send AJAX request
            $.ajax({
                url: '/wp-json/sellsuite/v1/redeem',
                type: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                headers: {
                    'X-WP-Nonce': nonce
                },
                data: JSON.stringify({
                    points: points,
                    order_id: orderId,
                    options: {
                        conversion_rate: this.pointsPerCurrencyUnit,
                        currency: this.currency
                    }
                }),
                success: (response) => {
                    console.log('SellSuite Redemption Response:', response);
                    if (response.success) {
                        this.onRedemptionSuccess(response);
                        this.showSuccess(response.message);
                    } else {
                        this.showError(response.message || 'Redemption failed');
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: (xhr) => {
                    const errorMsg = xhr.responseJSON?.message || 'Server error. Please try again.';
                    this.showError(errorMsg);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Handle successful redemption
         */
        onRedemptionSuccess: function(response) {
            
            this.redemptionApplied = true;
            this.redemptionId = response.redemption_id;

            // Store in session/form
            $('input[name="sellsuite_redemption_id"]').val(this.redemptionId);

            // Hide input box
            $('#sellsuite-redemption-box').slideUp(300);

            // Show redemption in order review if table exists
            this.addRedemptionToOrderReview(response);

            // Update available points display
            this.availablePoints = response.remaining_balance;
            $('#sellsuite-available-points').text(this.availablePoints);

            // Get nonce for AJAX
            const nonce = $('input[name="woocommerce-process-checkout-nonce"]').val();
            const ajaxurl = window.sellsuiteRedemptionData.ajaxurl;
            
            console.log('SellSuite: Preparing checkout update AJAX', {
                'ajaxurl': ajaxurl,
                'nonce': nonce,
                'nonce_field_found': nonce ? true : false,
                'redemptionId': this.redemptionId
            });

            // Trigger WooCommerce checkout refresh to recalculate fees and totals
            // This ensures the discount is applied to cart fees and order total is updated
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'woocommerce_update_order_review',
                    post_data: $('form.checkout').serialize(),
                    security: nonce
                },
                success: function(response) {
                    console.log('SellSuite: Checkout updated after redemption, recalculating points');
                    console.log('AJAX Response:', response);
                    
                    // Wait a moment for DOM to update, then recalculate points
                    setTimeout(function() {
                        PointRedemption.onCheckoutUpdate();
                    }, 100); // 100ms delay to ensure DOM is fully updated
                },
                error: function(xhr, status, error) {
                    console.error('SellSuite: AJAX Error updating checkout', {
                        'status': status,
                        'error': error,
                        'responseText': xhr.responseText,
                        'statusCode': xhr.status,
                        'url': ajaxurl
                    });
                    // Still try to update points even if AJAX fails
                    setTimeout(function() {
                        PointRedemption.onCheckoutUpdate();
                    }, 100);
                }
            });
        },

        /**
         * Add redemption row to order review table
         */
        addRedemptionToOrderReview: function(response) {
            const discountValue = response.discount_value || (response.points_redeemed / this.pointsPerCurrencyUnit);
            
            // Try multiple selectors to find the order review table
            let $table = $('table.woocommerce-review-order-table');
            if (!$table.length) {
                $table = $('.woocommerce-checkout-review-order table');
            }
            if (!$table.length) {
                $table = $('table.shop_table');
            }

            if (!$table.length) {
                console.warn('SellSuite: Could not find order review table');
                return;
            }

            // Remove existing redemption row if any
            $table.find('tr.sellsuite-redemption-row').remove();

            // Create redemption row with proper WooCommerce structure
            const html = `
                <tr class="sellsuite-redemption-row">
                    <td class="redemption-label">
                        <strong>Points Used</strong><br/>
                        <small style="color: #999;">${response.points_redeemed} points</small>
                    </td>
                    <td class="discount-amount">
                        ${this.formatCurrencyForTable(-discountValue)}
                        <button type="button" class="sellsuite-cancel-redemption-btn" title="Cancel redemption" style="margin-left: 10px; background: none; border: none; color: #dc3545; cursor: pointer; padding: 0; font-size: 16px;">
                            <span class="dashicons dashicons-no" style="width: auto; height: auto; font-size: 16px;"></span>
                            <span class="dashicons dashicons-update" style="width: auto; height: auto; font-size: 16px; display: none;"></span>
                        </button>
                    </td>
                </tr>
            `;

            // Insert AFTER total row
            const $totalRow = $table.find('tr.order-total, tr.cart-total, tr.order-subtotal');
            if ($totalRow.length) {
                $totalRow.before(html);
            } else {
                $table.append(html);
            }

            // Update the total with the discount applied
            this.updateOrderTotal(discountValue);

            console.log('SellSuite: Redemption row added successfully');
        },

        /**
         * Format currency for table display
         */
        formatCurrencyForTable: function(amount) {
            const formatted = Math.abs(amount).toFixed(2);
            const prefix = amount < 0 ? '-' : '';
            
            if (this.currencyPosition === 'left') {
                return `<span class="woocommerce-Price-amount amount">${prefix}${this.currencySymbol}${formatted}</span>`;
            } else if (this.currencyPosition === 'left_space') {
                return `<span class="woocommerce-Price-amount amount">${prefix}${this.currencySymbol} ${formatted}</span>`;
            } else if (this.currencyPosition === 'right_space') {
                return `<span class="woocommerce-Price-amount amount">${prefix}${formatted} ${this.currencySymbol}</span>`;
            } else {
                // 'right' or default
                return `<span class="woocommerce-Price-amount amount">${prefix}${formatted}${this.currencySymbol}</span>`;
            }
        },

        /**
         * Update order total in checkout
         */
        updateOrderTotal: function(discountValue) {
            // Find all total amount displays
            const $totals = $('tr.order-total .woocommerce-Price-amount, tr.cart-total .woocommerce-Price-amount');
            
            if ($totals.length > 0) {
                $totals.each((index, element) => {
                    const $el = $(element);
                    const text = $el.text();
                    
                    // Extract numeric value from current total
                    const currentTotal = parseFloat(text.replace(/[^\d.-]/g, '')) || 0;
                    const newTotal = currentTotal - discountValue;
                    
                    // Update display with new total
                    $el.html(this.formatCurrencyForTable(newTotal).replace(/<[^>]*>/g, ''));
                });
            }
        },

        /**
         * Cancel redemption
         */
        cancelRedemption: function() {
            if (!this.redemptionId) {
                this.showError('No redemption to cancel');
                return;
            }

            $('.sellsuite-cancel-redemption-btn .dashicons-no').hide();
            $('.sellsuite-cancel-redemption-btn .dashicons-update').show();

            // If redemption is only in user meta (pending, not DB), use new endpoint
            const nonce = window.sellsuiteRedemptionData.nonce || '';
            $.ajax({
                url: '/wp-json/sellsuite/v1/cancel-pending-redemption',
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-WP-Nonce': nonce
                },
                success: (response) => {
                    if (response.success) {
                        window.location.reload();
                        this.showSuccess('Redemption cancelled');
                    } else {
                        this.showError(response.message || 'Cancellation failed');
                    }
                },
                error: (xhr) => {
                    const errorMsg = xhr.responseJSON?.message || 'Server error';
                    this.showError(errorMsg);
                }
            });
        },

        /**
         * Handle successful cancellation
         */
        onCancellationSuccess: function(response) {
            this.redemptionApplied = false;
            this.redemptionId = null;

            // Clear session
            $('input[name="sellsuite_redemption_id"]').val('');

            // Show input box again
            $('#sellsuite-redemption-box').slideDown(300);
            $('#sellsuite-redeem-points-input').val('');

            // Remove redemption row from order table (try multiple selectors)
            $('table.woocommerce-review-order-table tbody tr.sellsuite-redemption-row').remove();
            $('.woocommerce-checkout-review-order table tbody tr.sellsuite-redemption-row').remove();
            $('table.shop_table tbody tr.sellsuite-redemption-row').remove();
            $('tr.sellsuite-redemption-row').remove();

            // Update available points
            this.availablePoints = response.remaining_balance || this.availablePoints;
            $('#sellsuite-available-points').text(this.availablePoints);

            // Reset calculation display
            this.updateCalculation(0);

            // Trigger WooCommerce checkout refresh to recalculate fees and totals
            // This ensures the discount fee is removed and order total is restored
            $.ajax({
                url: window.sellsuiteRedemptionData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'woocommerce_update_order_review',
                    post_data: $('form.checkout').serialize(),
                    security: $('input[name="woocommerce-process-checkout-nonce"]').val()
                },
                success: function(response) {
                    console.log('SellSuite: Checkout updated after cancellation, recalculating points');
                    console.log('AJAX Response:', response);
                    
                    // Wait a moment for DOM to update, then recalculate points
                    setTimeout(function() {
                        PointRedemption.onCheckoutUpdate();
                    }, 100); // 100ms delay to ensure DOM is fully updated
                },
                error: function(xhr, status, error) {
                    console.error('SellSuite: AJAX Error updating checkout after cancellation', {
                        'status': status,
                        'error': error,
                        'responseText': xhr.responseText,
                        'statusCode': xhr.status
                    });
                    // Still try to update points even if AJAX fails
                    setTimeout(function() {
                        PointRedemption.onCheckoutUpdate();
                    }, 100);
                }
            });
        },

        /**
         * Handle checkout updates (e.g., shipping method change)
         */
        onCheckoutUpdate: function() {
            // Get the updated cart subtotal from WooCommerce checkout
            // This should only include product prices, NOT shipping, taxes, or fees
            // When checkout updates, we need to find and update the subtotal for points calculation
            
            let newSubtotal = 0;
            
            // Method 1: Look for the subtotal row in the order review table
            const $subtotalElements = $('tr.woocommerce-checkout-review-order__subtotal .woocommerce-Price-amount, tr.cart-subtotal .woocommerce-Price-amount');
            if ($subtotalElements.length) {
                const $lastSubtotal = $subtotalElements.last();
                const subtotalText = $lastSubtotal.text().trim();
                const numericValue = subtotalText.replace(/[^\d.,]/g, '').replace(/,/g, '');
                newSubtotal = parseFloat(numericValue) || 0;
                console.log('SellSuite: Found subtotal from subtotal row:', subtotalText, '→', newSubtotal);
            }
            
            // Method 2: If not found, try to extract subtotal before shipping/fees
            if (!newSubtotal || newSubtotal <= 0) {
                // Look for all rows in the order review table
                const $rows = $('table.woocommerce-review-order-table tbody tr, .woocommerce-checkout-review-order table tbody tr');
                $rows.each((index, element) => {
                    const $row = $(element);
                    const text = $row.text().toLowerCase();
                    
                    // Look for subtotal-like rows
                    if (text.includes('subtotal') && !text.includes('tax') && !text.includes('shipping')) {
                        const $amount = $row.find('.woocommerce-Price-amount');
                        if ($amount.length) {
                            const amountText = $amount.text().trim();
                            const numericValue = amountText.replace(/[^\d.,]/g, '').replace(/,/g, '');
                            newSubtotal = parseFloat(numericValue) || newSubtotal;
                        }
                    }
                });
                if (newSubtotal > 0) {
                    console.log('SellSuite: Found subtotal from table scan:', newSubtotal);
                }
            }
            
            // Update if we found a new subtotal
            if (newSubtotal > 0) {
                console.log('SellSuite: Previous subtotal:', this.cartSubtotal, '→ New subtotal:', newSubtotal);
                this.cartSubtotal = newSubtotal;
            } else {
                console.warn('SellSuite: Could not find updated cart subtotal on page');
            }

            // Recalculate and update earned points display based on subtotal
            this.updateEarnedPointsDisplay();
        },

        /**
         * Update the earned points display based on cart subtotal only
         * Earned points = Cart Subtotal (1:1 ratio, excluding shipping/taxes/fees)
         */
        updateEarnedPointsDisplay: function() {
            // Get the earned points element
            const $pointsRow = $('tr.sellsuite-points-row .points-amount');
            
            if (!$pointsRow.length) {
                console.warn('SellSuite: Points row (.sellsuite-points-row .points-amount) not found on page');
                console.log('Available rows:', $('tr').map(function() { return $(this).attr('class'); }).get());
                return; // No points row to update
            }

            // Earned points = Cart Subtotal only (1:1 calculation, no shipping/taxes/fees)
            const earnedPoints = Math.floor(this.cartSubtotal);
            const currentDisplay = $pointsRow.text().trim();
            
            // Update the display with the calculated points based on subtotal
            $pointsRow.html('<i class="fas fa-star"></i> ' + earnedPoints);
            
            console.log('SellSuite: Earned Points Updated (based on cart subtotal):', {
                'previousValue': currentDisplay,
                'cartSubtotal': this.cartSubtotal,
                'earnedPoints': earnedPoints,
                'elementFound': true,
                'note': 'Points exclude shipping, taxes, and fees'
            });
        },

        /**
         * Get order ID from checkout (if available)
         */
        getOrderId: function() {
            // Try to get from post data or form
            const $orderIdInput = $('input[name="post_id"]');
            if ($orderIdInput.length) {
                return parseInt($orderIdInput.val()) || 0;
            }
            return 0;
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotification(message, 'error');
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.showNotification(message, 'success');
        },

        /**
         * Show notification
         */
        showNotification: function(message, type = 'info') {
            const $container = $('#sellsuite-redemption-messages');
            
            if (!$container.length) {
                // Create container if doesn't exist
                $('<div id="sellsuite-redemption-messages" class="woocommerce-notices-wrapper"></div>')
                    .insertBefore('#sellsuite-redemption-box');
            }

            const classes = `woocommerce-message woocommerce-${type}`;
            const html = `<div class="${classes}"><p>${message}</p></div>`;
            
            const $notification = $(html);
            $('#sellsuite-redemption-messages').html($notification);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Update display on page load
         */
        updateDisplay: function() {
            // Update available points display
            $('#sellsuite-available-points').text(this.availablePoints);
            
            // Update max redeemable display with proper currency formatting
            $('#sellsuite-max-redeemable').text(this.formatCurrency(this.maxRedeemable));
            
            // Update earned points display on initialization
            this.updateEarnedPointsDisplay();
        }
    };

    // Initialize on document ready
    $(function() {
        PointRedemption.init();
    });

})(jQuery);
