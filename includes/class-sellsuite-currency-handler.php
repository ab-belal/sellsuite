<?php
/**
 * SellSuite Multi-Currency Handler
 *
 * Manages currency conversion, exchange rates, and multi-currency reporting
 *
 * @package    SellSuite
 * @subpackage SellSuite/includes
 * @author     AB Belal <info@ab-belal.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Multi-Currency Handler Class
 *
 * Handles currency conversions, manages exchange rates,
 * and provides multi-currency analytics and reporting.
 */
class SellSuite_Currency_Handler {

    /**
     * Convert points from one currency to another
     *
     * @param float  $amount     Amount to convert
     * @param string $from_currency Source currency code (e.g., 'USD')
     * @param string $to_currency   Target currency code (e.g., 'EUR')
     * @return float|WP_Error Converted amount or error
     */
    public static function convert_currency($amount, $from_currency, $to_currency) {
        try {
            $amount = floatval($amount);
            $from_currency = strtoupper(sanitize_text_field($from_currency));
            $to_currency = strtoupper(sanitize_text_field($to_currency));

            if ($amount < 0) {
                return new WP_Error(
                    'invalid_amount',
                    'Amount cannot be negative',
                    array('status' => 400)
                );
            }

            // If same currency, return same amount
            if ($from_currency === $to_currency) {
                return $amount;
            }

            $rate = self::get_exchange_rate($from_currency, $to_currency);

            if (is_wp_error($rate)) {
                return $rate;
            }

            $converted = $amount * floatval($rate);

            do_action(
                'sellsuite_currency_converted',
                $amount,
                $from_currency,
                $converted,
                $to_currency,
                $rate
            );

            return round($converted, 2);

        } catch (Exception $e) {
            error_log('SellSuite Currency Conversion Error: ' . $e->getMessage());
            return new WP_Error(
                'conversion_error',
                'Error converting currency: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get exchange rate between two currencies
     *
     * @param string $from_currency Source currency code
     * @param string $to_currency   Target currency code
     * @return float|WP_Error Exchange rate or error
     */
    public static function get_exchange_rate($from_currency, $to_currency) {
        try {
            global $wpdb;

            $from_currency = strtoupper(sanitize_text_field($from_currency));
            $to_currency = strtoupper(sanitize_text_field($to_currency));

            if ($from_currency === $to_currency) {
                return 1.0;
            }

            $rates_table = $wpdb->prefix . 'sellsuite_exchange_rates';

            // Get rate from database
            $rate = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT rate FROM {$rates_table}
                    WHERE from_currency = %s AND to_currency = %s
                    AND status = 'active'
                    ORDER BY updated_at DESC LIMIT 1",
                    $from_currency,
                    $to_currency
                )
            );

            if ($wpdb->last_error) {
                error_log('Database Error: ' . $wpdb->last_error);
                return new WP_Error(
                    'db_error',
                    'Database error retrieving rate',
                    array('status' => 500)
                );
            }

            if ($rate) {
                return floatval($rate);
            }

            // Try reverse rate
            $reverse_rate = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT rate FROM {$rates_table}
                    WHERE from_currency = %s AND to_currency = %s
                    AND status = 'active'
                    ORDER BY updated_at DESC LIMIT 1",
                    $to_currency,
                    $from_currency
                )
            );

            if ($reverse_rate) {
                $rate = 1 / floatval($reverse_rate);
                return round($rate, 6);
            }

            // Default to 1:1 if no rate found
            return 1.0;

        } catch (Exception $e) {
            error_log('SellSuite Get Rate Error: ' . $e->getMessage());
            return new WP_Error(
                'rate_error',
                'Error retrieving exchange rate',
                array('status' => 500)
            );
        }
    }

    /**
     * Update exchange rate
     *
     * @param string $from_currency Source currency
     * @param string $to_currency   Target currency
     * @param float  $rate          Exchange rate
     * @return bool|WP_Error True on success
     */
    public static function update_exchange_rate($from_currency, $to_currency, $rate) {
        try {
            if (!current_user_can('manage_woocommerce')) {
                return new WP_Error(
                    'insufficient_permissions',
                    'You do not have permission to perform this action',
                    array('status' => 403)
                );
            }

            global $wpdb;

            $from_currency = strtoupper(sanitize_text_field($from_currency));
            $to_currency = strtoupper(sanitize_text_field($to_currency));
            $rate = floatval($rate);

            if ($rate <= 0) {
                return new WP_Error(
                    'invalid_rate',
                    'Exchange rate must be positive',
                    array('status' => 400)
                );
            }

            $rates_table = $wpdb->prefix . 'sellsuite_exchange_rates';

            // Check if rate exists
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$rates_table}
                    WHERE from_currency = %s AND to_currency = %s",
                    $from_currency,
                    $to_currency
                )
            );

            if ($existing) {
                // Update existing rate
                $result = $wpdb->update(
                    $rates_table,
                    array(
                        'rate' => $rate,
                        'updated_at' => current_time('mysql')
                    ),
                    array(
                        'from_currency' => $from_currency,
                        'to_currency' => $to_currency
                    ),
                    array('%f', '%s'),
                    array('%s', '%s')
                );
            } else {
                // Insert new rate
                $result = $wpdb->insert(
                    $rates_table,
                    array(
                        'from_currency' => $from_currency,
                        'to_currency' => $to_currency,
                        'rate' => $rate,
                        'status' => 'active',
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%f', '%s', '%s', '%s')
                );
            }

            if ($result === false) {
                error_log('Error updating rate: ' . $wpdb->last_error);
                return new WP_Error(
                    'rate_update_error',
                    'Error updating exchange rate',
                    array('status' => 500)
                );
            }

            do_action(
                'sellsuite_exchange_rate_updated',
                $from_currency,
                $to_currency,
                $rate
            );

            return true;

        } catch (Exception $e) {
            error_log('SellSuite Update Rate Error: ' . $e->getMessage());
            return new WP_Error(
                'update_error',
                'Error updating rate: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Record currency conversion transaction
     *
     * @param int    $user_id       User ID
     * @param float  $original_amount Original amount
     * @param string $original_currency Original currency
     * @param float  $converted_amount Converted amount
     * @param string $converted_currency Converted currency
     * @param float  $rate          Exchange rate used
     * @param string $reason        Reason for conversion
     * @return int|WP_Error Conversion ID or error
     */
    public static function record_conversion(
        $user_id,
        $original_amount,
        $original_currency,
        $converted_amount,
        $converted_currency,
        $rate,
        $reason = 'redemption'
    ) {
        try {
            global $wpdb;

            $user_id = intval($user_id);
            $original_amount = floatval($original_amount);
            $converted_amount = floatval($converted_amount);
            $rate = floatval($rate);

            if ($user_id <= 0) {
                return new WP_Error(
                    'invalid_user',
                    'Invalid user ID',
                    array('status' => 400)
                );
            }

            if ($original_amount < 0 || $converted_amount < 0) {
                return new WP_Error(
                    'invalid_amount',
                    'Amounts cannot be negative',
                    array('status' => 400)
                );
            }

            $conversions_table = $wpdb->prefix . 'sellsuite_currency_conversions';

            $result = $wpdb->insert(
                $conversions_table,
                array(
                    'user_id' => $user_id,
                    'original_amount' => $original_amount,
                    'original_currency' => strtoupper(sanitize_text_field($original_currency)),
                    'converted_amount' => $converted_amount,
                    'converted_currency' => strtoupper(sanitize_text_field($converted_currency)),
                    'exchange_rate' => $rate,
                    'reason' => sanitize_text_field($reason),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%f', '%s', '%f', '%s', '%f', '%s', '%s')
            );

            if (!$result) {
                error_log('Error recording conversion: ' . $wpdb->last_error);
                return new WP_Error(
                    'record_error',
                    'Error recording conversion',
                    array('status' => 500)
                );
            }

            $conversion_id = $wpdb->insert_id;

            do_action(
                'sellsuite_currency_conversion_recorded',
                $conversion_id,
                $user_id,
                $original_amount,
                $original_currency,
                $converted_amount,
                $converted_currency
            );

            return $conversion_id;

        } catch (Exception $e) {
            error_log('SellSuite Record Conversion Error: ' . $e->getMessage());
            return new WP_Error(
                'record_error',
                'Error recording conversion: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get user conversion history
     *
     * @param int $user_id User ID
     * @param int $limit   Number of records (default 50)
     * @param int $offset  Offset for pagination (default 0)
     * @return array|WP_Error Array of conversions or error
     */
    public static function get_user_conversions($user_id, $limit = 50, $offset = 0) {
        try {
            global $wpdb;

            $user_id = intval($user_id);
            $limit = intval($limit);
            $offset = intval($offset);

            if ($user_id <= 0) {
                return new WP_Error(
                    'invalid_user',
                    'Invalid user ID',
                    array('status' => 400)
                );
            }

            if ($limit < 1 || $limit > 500) {
                $limit = 50;
            }

            if ($offset < 0) {
                $offset = 0;
            }

            $conversions_table = $wpdb->prefix . 'sellsuite_currency_conversions';

            $conversions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$conversions_table}
                    WHERE user_id = %d
                    ORDER BY created_at DESC
                    LIMIT %d OFFSET %d",
                    $user_id,
                    $limit,
                    $offset
                )
            );

            if ($wpdb->last_error) {
                error_log('Database Error: ' . $wpdb->last_error);
                return new WP_Error(
                    'db_error',
                    'Database error retrieving conversions',
                    array('status' => 500)
                );
            }

            // Get total count
            $total = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$conversions_table}
                    WHERE user_id = %d",
                    $user_id
                )
            );

            return array(
                'conversions' => $conversions ?: array(),
                'total' => intval($total),
                'limit' => $limit,
                'offset' => $offset
            );

        } catch (Exception $e) {
            error_log('SellSuite Get Conversions Error: ' . $e->getMessage());
            return new WP_Error(
                'fetch_error',
                'Error retrieving conversions: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get currency support configuration
     *
     * Returns list of supported currencies and their settings
     *
     * @return array|WP_Error Array of supported currencies
     */
    public static function get_supported_currencies() {
        try {
            global $wpdb;

            $currencies_table = $wpdb->prefix . 'sellsuite_currencies';

            // Check if table exists
            $table_exists = $wpdb->get_var(
                "SHOW TABLES LIKE '{$currencies_table}'"
            );

            if (!$table_exists) {
                // Return default currencies
                return array(
                    array(
                        'code' => 'USD',
                        'symbol' => '$',
                        'name' => 'US Dollar',
                        'status' => 'active'
                    ),
                    array(
                        'code' => 'EUR',
                        'symbol' => '€',
                        'name' => 'Euro',
                        'status' => 'active'
                    ),
                    array(
                        'code' => 'GBP',
                        'symbol' => '£',
                        'name' => 'British Pound',
                        'status' => 'active'
                    ),
                    array(
                        'code' => 'JPY',
                        'symbol' => '¥',
                        'name' => 'Japanese Yen',
                        'status' => 'active'
                    ),
                    array(
                        'code' => 'AUD',
                        'symbol' => 'A$',
                        'name' => 'Australian Dollar',
                        'status' => 'active'
                    )
                );
            }

            $currencies = $wpdb->get_results(
                "SELECT * FROM {$currencies_table} WHERE status = 'active' ORDER BY code ASC"
            );

            if ($wpdb->last_error) {
                error_log('Database Error: ' . $wpdb->last_error);
                return new WP_Error(
                    'db_error',
                    'Database error retrieving currencies',
                    array('status' => 500)
                );
            }

            return $currencies ?: self::get_supported_currencies();

        } catch (Exception $e) {
            error_log('SellSuite Get Currencies Error: ' . $e->getMessage());
            return new WP_Error(
                'fetch_error',
                'Error retrieving currencies: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get multi-currency analytics
     *
     * Provides analytics data across multiple currencies
     *
     * @param string $currency Currency code (optional, default all)
     * @return array|WP_Error Analytics data
     */
    public static function get_currency_analytics($currency = null) {
        try {
            global $wpdb;

            $conversions_table = $wpdb->prefix . 'sellsuite_currency_conversions';
            $ledger_table = $wpdb->prefix . 'sellsuite_points_ledger';

            $analytics = array(
                'total_conversions' => 0,
                'total_converted' => 0,
                'by_currency_pair' => array(),
                'by_reason' => array(),
                'average_rate' => 0
            );

            if ($currency) {
                $currency = strtoupper(sanitize_text_field($currency));

                // Get conversions for specific currency
                $conversions = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT 
                            COUNT(*) as count,
                            original_currency,
                            converted_currency,
                            SUM(original_amount) as total_original,
                            SUM(converted_amount) as total_converted,
                            AVG(exchange_rate) as avg_rate
                        FROM {$conversions_table}
                        WHERE original_currency = %s OR converted_currency = %s
                        GROUP BY original_currency, converted_currency
                        ORDER BY count DESC",
                        $currency,
                        $currency
                    )
                );
            } else {
                // Get all conversions
                $conversions = $wpdb->get_results(
                    "SELECT 
                        COUNT(*) as count,
                        original_currency,
                        converted_currency,
                        SUM(original_amount) as total_original,
                        SUM(converted_amount) as total_converted,
                        AVG(exchange_rate) as avg_rate
                    FROM {$conversions_table}
                    GROUP BY original_currency, converted_currency
                    ORDER BY count DESC"
                );
            }

            if ($wpdb->last_error) {
                error_log('Database Error: ' . $wpdb->last_error);
                return new WP_Error(
                    'db_error',
                    'Database error retrieving analytics',
                    array('status' => 500)
                );
            }

            if (!empty($conversions)) {
                foreach ($conversions as $pair) {
                    $analytics['total_conversions'] += intval($pair->count);
                    $analytics['total_converted'] += floatval($pair->total_converted);

                    $pair_key = $pair->original_currency . ' → ' . $pair->converted_currency;
                    $analytics['by_currency_pair'][$pair_key] = array(
                        'count' => intval($pair->count),
                        'total_original' => floatval($pair->total_original),
                        'total_converted' => floatval($pair->total_converted),
                        'avg_rate' => floatval($pair->avg_rate)
                    );
                }

                if ($analytics['total_conversions'] > 0) {
                    $total_rate = 0;
                    foreach ($conversions as $pair) {
                        $total_rate += floatval($pair->avg_rate);
                    }
                    $analytics['average_rate'] = round(
                        $total_rate / count($conversions),
                        6
                    );
                }
            }

            // Get reason breakdown
            $reasons = $wpdb->get_results(
                "SELECT reason, COUNT(*) as count, SUM(converted_amount) as total
                FROM {$conversions_table}
                GROUP BY reason"
            );

            if (!$wpdb->last_error && !empty($reasons)) {
                foreach ($reasons as $reason) {
                    $analytics['by_reason'][$reason->reason] = array(
                        'count' => intval($reason->count),
                        'total_amount' => floatval($reason->total)
                    );
                }
            }

            return $analytics;

        } catch (Exception $e) {
            error_log('SellSuite Currency Analytics Error: ' . $e->getMessage());
            return new WP_Error(
                'analytics_error',
                'Error retrieving analytics: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get currency conversion summary for user
     *
     * @param int $user_id User ID
     * @return array|WP_Error Summary data
     */
    public static function get_user_conversion_summary($user_id) {
        try {
            global $wpdb;

            $user_id = intval($user_id);

            if ($user_id <= 0) {
                return new WP_Error(
                    'invalid_user',
                    'Invalid user ID',
                    array('status' => 400)
                );
            }

            $conversions_table = $wpdb->prefix . 'sellsuite_currency_conversions';

            $summary = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT 
                        COUNT(*) as total_conversions,
                        SUM(original_amount) as total_original,
                        SUM(converted_amount) as total_converted,
                        AVG(exchange_rate) as avg_rate
                    FROM {$conversions_table}
                    WHERE user_id = %d",
                    $user_id
                )
            );

            if ($wpdb->last_error) {
                error_log('Database Error: ' . $wpdb->last_error);
                return new WP_Error(
                    'db_error',
                    'Database error retrieving summary',
                    array('status' => 500)
                );
            }

            return array(
                'total_conversions' => intval($summary->total_conversions ?? 0),
                'total_original_amount' => floatval($summary->total_original ?? 0),
                'total_converted_amount' => floatval($summary->total_converted ?? 0),
                'average_rate' => floatval($summary->avg_rate ?? 1.0)
            );

        } catch (Exception $e) {
            error_log('SellSuite Conversion Summary Error: ' . $e->getMessage());
            return new WP_Error(
                'summary_error',
                'Error retrieving summary: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Convert user's total balance to different currency
     *
     * @param int    $user_id User ID
     * @param string $target_currency Target currency code
     * @return array|WP_Error Array with original and converted balance
     */
    public static function get_balance_in_currency($user_id, $target_currency) {
        try {
            global $wpdb;

            $user_id = intval($user_id);
            $target_currency = strtoupper(sanitize_text_field($target_currency));

            if ($user_id <= 0) {
                return new WP_Error(
                    'invalid_user',
                    'Invalid user ID',
                    array('status' => 400)
                );
            }

            // Get base currency (from WooCommerce settings)
            $base_currency = get_option('woocommerce_currency', 'USD');

            // Get user's current balance
            $table = $wpdb->prefix . 'sellsuite_points_ledger';

            $balance = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(CASE 
                        WHEN action_type = 'redemption' THEN -points
                        ELSE points
                    END) as total_points
                    FROM {$table}
                    WHERE user_id = %d AND status IN ('earned', 'pending')",
                    $user_id
                )
            );

            if ($wpdb->last_error) {
                error_log('Database Error: ' . $wpdb->last_error);
                return new WP_Error(
                    'db_error',
                    'Database error retrieving balance',
                    array('status' => 500)
                );
            }

            $balance = intval($balance ?? 0);

            // Convert to target currency
            if ($base_currency === $target_currency) {
                $converted = $balance;
            } else {
                $converted = self::convert_currency($balance, $base_currency, $target_currency);

                if (is_wp_error($converted)) {
                    return $converted;
                }
            }

            return array(
                'user_id' => $user_id,
                'base_currency' => $base_currency,
                'base_balance' => $balance,
                'target_currency' => $target_currency,
                'converted_balance' => $converted
            );

        } catch (Exception $e) {
            error_log('SellSuite Balance Conversion Error: ' . $e->getMessage());
            return new WP_Error(
                'conversion_error',
                'Error converting balance: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}
