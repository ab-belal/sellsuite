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
        conversionRate: 0,
        maxRedeemablePercentage: 0,
        currency: '$',
        currencySymbol: '$',
        orderTotal: 0,
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
            this.conversionRate = parseFloat(window.sellsuiteRedemptionData.conversion_rate) || 1;
            this.maxRedeemablePercentage = parseFloat(window.sellsuiteRedemptionData.max_redeemable_percentage) || 20;
            this.currency = window.sellsuiteRedemptionData.currency || 'USD';
            this.currencySymbol = window.sellsuiteRedemptionData.currency_symbol || '$';
            this.availablePoints = parseInt(window.sellsuiteRedemptionData.available_points) || 0;
            this.orderTotal = parseFloat(window.sellsuiteRedemptionData.order_total) || 0;
            
            // Calculate max redeemable amount
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
         * Update real-time calculation display
         */
        updateCalculation: function(points) {
            const discountValue = points * this.conversionRate;
            const remainingPoints = this.availablePoints - points;
            const $displayArea = $('#sellsuite-redemption-calculation');

            if (!$displayArea.length) {
                return;
            }

            // Check against max redeemable
            let warning = '';
            if (discountValue > this.maxRedeemable) {
                const maxPoints = Math.floor(this.maxRedeemable / this.conversionRate);
                warning = `<div class="sellsuite-redemption-warning">
                    <span class="dashicons dashicons-warning"></span>
                    Maximum redeemable is ${this.currencySymbol}${this.maxRedeemable.toFixed(2)} (${maxPoints} points) for this order.
                </div>`;
            }

            // Update display
            let html = '';
            if (points > 0) {
                html = `
                    <div class="sellsuite-calculation-row">
                        <span class="label">${points} points × ${this.conversionRate} = <strong>${this.currencySymbol}${discountValue.toFixed(2)} discount</strong></span>
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

            const discountValue = points * this.conversionRate;
            if (discountValue > this.maxRedeemable) {
                this.showError(`Maximum redeemable is ${this.currencySymbol}${this.maxRedeemable.toFixed(2)} for this order`);
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
                        conversion_rate: this.conversionRate,
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

            // Trigger checkout update
            $('body').trigger('updated_checkout');
        },

        /**
         * Add redemption row to order review table
         */
        addRedemptionToOrderReview: function(response) {
            const discountValue = response.discount_value || (response.points_redeemed * this.conversionRate);
            
            // Try multiple selectors to find the order review table
            let $table = $('table.woocommerce-review-order-table tbody');
            if (!$table.length) {
                $table = $('.woocommerce-checkout-review-order table tbody');
            }
            if (!$table.length) {
                $table = $('table.shop_table tbody');
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
                    <td class="product-name">
                        <strong>Point Redemption</strong><br/>
                        <small style="color: #999;">${response.points_redeemed} points</small>
                    </td>
                    <td class="product-total" style="text-align: right;">
                        <span class="woocommerce-Price-amount amount">
                            <span class="woocommerce-price-currency-symbol">${this.currencySymbol}</span>${Math.abs(discountValue).toFixed(2)}
                        </span>
                        <button type="button" class="sellsuite-cancel-redemption-btn" title="Cancel redemption" style="margin-left: 10px; background: none; border: none; color: #dc3545; cursor: pointer; padding: 0; font-size: 16px;">
                            <span class="dashicons dashicons-no" style="width: auto; height: auto; font-size: 16px;"></span>
                        </button>
                    </td>
                </tr>
            `;

            // Insert before total row
            const $totalRow = $table.find('tr.order-total, tr.cart-total');
            if ($totalRow.length) {
                $totalRow.before(html);
            } else {
                // Insert before last row if no total row found
                $table.append(html);
            }

            console.log('SellSuite: Redemption row added successfully');
        },

        /**
         * Cancel redemption
         */
        cancelRedemption: function() {
            if (!this.redemptionId) {
                this.showError('No redemption to cancel');
                return;
            }

            const nonce = window.sellsuiteRedemptionData.nonce || '';

            $.ajax({
                url: `/wp-json/sellsuite/v1/redemptions/${this.redemptionId}/cancel`,
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-WP-Nonce': nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.onCancellationSuccess(response);
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
            this.availablePoints = response.remaining_balance;
            $('#sellsuite-available-points').text(this.availablePoints);

            // Reset calculation display
            this.updateCalculation(0);

            // Trigger checkout update
            $('body').trigger('updated_checkout');
        },

        /**
         * Handle checkout updates (e.g., shipping method change)
         */
        onCheckoutUpdate: function() {
            // Re-fetch order total from page
            const $totalAmount = $('tr.order-total .woocommerce-Price-amount');
            if ($totalAmount.length) {
                const totalText = $totalAmount.text().replace(/[^\d.-]/g, '');
                this.orderTotal = parseFloat(totalText) || 0;
                this.maxRedeemable = (this.orderTotal * this.maxRedeemablePercentage) / 100;
            }
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
            
            // Update max redeemable display
            $('#sellsuite-max-redeemable').text(this.currencySymbol + this.maxRedeemable.toFixed(2));
        }
    };

    // Initialize on document ready
    $(function() {
        PointRedemption.init();
    });

})(jQuery);
