/**
 * SellSuite Variation Points
 * 
 * Dynamically updates reward points display when a variation is selected
 * on variable product pages.
 * 
 * Requirements:
 * - jQuery
 * - sellsuitePoints object (localized via wp_localize_script)
 * - Product page with variations
 * 
 * @package SellSuite
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Initialize variation points handler
     */
    function init_variation_points() {
        // Get the form
        const $form = $('form.variations_form');
        
        if (!$form.length) {
            return;
        }

        // Listen for variation selection changes
        $form.on('change', '.variations select', function() {
            handle_variation_change();
        });

        // Also listen for woocommerce_variation_select event (more reliable)
        $form.on('woocommerce_variation_select', function() {
            handle_variation_change();
        });

        // Update points on page load if variation is already selected
        setTimeout(function() {
            if ($form.find('input[name="variation_id"]').val()) {
                handle_variation_change();
            }
        }, 0);
    }

    /**
     * Handle variation change and update points display
     */
    function handle_variation_change() {
        // Get the selected variation ID
        const $form = $('form.variations_form');
        const variation_id = $form.find('input[name="variation_id"]').val();

        if (!variation_id) {
            return;
        }

        // Fetch variation points from REST API
        fetch_variation_points(variation_id);
    }

    /**
     * Fetch variation points from REST API
     * 
     * @param {number} variation_id The variation product ID
     */
    function fetch_variation_points(variation_id) {
        if (!window.sellsuitePoints || !window.sellsuitePoints.restUrl) {
            return;
        }

        const url = window.sellsuitePoints.restUrl + '/' + variation_id + '/points';

        fetch(url, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': window.sellsuitePoints.nonce || '',
                'Content-Type': 'application/json',
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch variation points');
            }
            return response.json();
        })
        .then(data => {
            if (data.points !== undefined) {
                update_points_display(data.points);
            }
        })
        .catch(error => {
            console.error('Error fetching variation points:', error);
        });
    }

    /**
     * Update the displayed reward points
     * 
     * @param {number} points The number of reward points
     */
    function update_points_display(points) {
        // Find and update the sellsuite-product-points element
        const $pointsContainer = $('.sellsuite-product-points');

        if (!$pointsContainer.length) {
            return;
        }

        // If no points, hide the container
        if (points <= 0) {
            $pointsContainer.fadeOut(300);
            return;
        }

        // Update the points text
        const $pointsText = $pointsContainer.find('.points-badge');
        
        if ($pointsText.length) {
            // Create the new points text
            const newText = 'Earn <strong>' + points + ' Reward Points</strong> with this purchase';
            
            // Update with fade effect
            $pointsText.fadeOut(200, function() {
                $(this).html('<i class="fas fa-star"></i> ' + newText);
                $(this).fadeIn(200);
            });
        }

        // Show the container if it's hidden
        $pointsContainer.fadeIn(300);
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        init_variation_points();
    });

})(jQuery);
