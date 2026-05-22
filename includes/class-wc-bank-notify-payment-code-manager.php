<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_BankNotify_Payment_Code_Manager
{
    private $db;
    private $table_name;

    public function __construct()
    {
        $this->db = new WC_BankNotify_DB();
        $this->table_name = $this->db->get_table_name();
    }

    /**
     * Get an available code and assign it to an order
     * Uses transaction to prevent race conditions
     */
    public function get_available_code($order_id)
    {
        global $wpdb;

        WC_BankNotify_Logger::debug('Attempting to get available payment code', ['order_id' => $order_id]);

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Lock and get first available code
            $code_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, code FROM %i
                    WHERE status = %s
                    LIMIT 1
                    FOR UPDATE",
                    $this->table_name,
                    'available'
                )
            );

            if (!$code_row) {
                $wpdb->query('ROLLBACK');
                WC_BankNotify_Logger::warning('No available payment codes; caller should fall back to prefix mode', ['order_id' => $order_id]);
                return false;
            }

            // Update code status to assigned
            $updated = $wpdb->update(
                $this->table_name,
                [
                    'status' => 'assigned',
                    'order_id' => $order_id,
                    'assigned_at' => current_time('mysql'),
                ],
                ['id' => $code_row->id],
                ['%s', '%d', '%s'],
                ['%d']
            );

            if ($updated === false) {
                $wpdb->query('ROLLBACK');
                WC_BankNotify_Logger::error('Failed to update payment code status', [
                    'order_id' => $order_id,
                    'code' => $code_row->code,
                ]);
                return false;
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            WC_BankNotify_Logger::info('Payment code assigned successfully', [
                'order_id' => $order_id,
                'code' => $code_row->code,
            ]);

            return $code_row->code;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            WC_BankNotify_Logger::error('Exception while getting available code', [
                'order_id' => $order_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Release a code back to available status
     */
    public function release_code($code)
    {
        global $wpdb;

        WC_BankNotify_Logger::debug('Releasing payment code', ['code' => $code]);

        $order_id = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT order_id FROM %i WHERE code = %s',
                $this->table_name,
                $code
            )
        );

        $updated = $wpdb->update(
            $this->table_name,
            [
                'status' => 'available',
                'order_id' => null,
                'assigned_at' => null,
            ],
            ['code' => $code],
            ['%s', '%d', '%s'],
            ['%s']
        );

        if ($updated !== false) {
            delete_transient('bank_notify_stats_cache');
            WC_BankNotify_Logger::info('Payment code released successfully', ['code' => $code]);

            if ($order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $this->clear_order_payment_code($order);
                    $order->save();
                }
            }
        } else {
            WC_BankNotify_Logger::warning('Failed to release payment code', ['code' => $code]);
        }

        return $updated !== false;
    }

    /**
     * Delete a payment code from the custom payment-code table.
     */
    public function delete_code($code)
    {
        global $wpdb;

        $deleted = $wpdb->delete($this->table_name, ['code' => $code], ['%s']);

        if ($deleted !== false) {
            delete_transient('bank_notify_stats_cache');
        }

        return $deleted !== false;
    }

    /**
     * Get current status for a payment code.
     */
    public function get_code_status($code)
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                'SELECT status FROM %i WHERE code = %s',
                $this->table_name,
                $code
            )
        );
    }

    /**
     * Release expired codes based on settings
     * Returns number of codes released
     */
    public function release_expired_codes()
    {
        global $wpdb;

        // Get gateway settings
        $gateway = WC()->payment_gateways()->payment_gateways()['bank_notify'] ?? null;
        if (!$gateway) {
            return 0;
        }

        // Get expiry settings
        $expiry_value = absint($gateway->get_option('payment_code_expiry_value', 24));
        $expiry_unit = $gateway->get_option('payment_code_expiry_unit', 'hours');

        if ($expiry_value < 1) {
            $expiry_value = 24;
        }

        if (!in_array($expiry_unit, ['minutes', 'hours', 'days', 'weeks', 'months'], true)) {
            $expiry_unit = 'hours';
        }

        // Calculate expiry time in seconds
        $expiry_seconds = $this->calculate_expiry_seconds($expiry_value, $expiry_unit);

        // Calculate cutoff datetime
        $cutoff_time = gmdate('Y-m-d H:i:s', current_time('timestamp') - $expiry_seconds);

        // For natural mode: release codes from pool
        $expired_codes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT code, order_id FROM %i
                WHERE status = %s
                AND assigned_at IS NOT NULL
                AND assigned_at < %s",
                $this->table_name,
                'assigned',
                $cutoff_time
            )
        );

        $released_count = 0;
        foreach ($expired_codes as $row) {
            if ($row->order_id) {
                $order = wc_get_order($row->order_id);
                if ($order && !$order->has_status('on-hold')) {
                    if ($order->has_status(['processing', 'completed'])) {
                        $this->mark_code_as_used($row->code);
                    }

                    continue;
                }
            }

            if ($this->release_code($row->code)) {
                $released_count++;

                if ($row->order_id) {
                    $order = wc_get_order($row->order_id);
                    if ($order) {
                        $this->clear_order_payment_code($order);
                        $order->add_order_note(
                            sprintf('Mã thanh toán "%s" đã hết hạn và được giải phóng khỏi đơn hàng.', $row->code)
                        );
                        $order->save();
                    }
                }
            }
        }

        return $released_count;
    }

    /**
     * Remove natural payment code metadata from an order after the code expires or is released.
     */
    public function clear_order_payment_code($order)
    {
        $order->delete_meta_data('_bank_notify_payment_code');
        $order->delete_meta_data('_bank_notify_payment_mode');
        $order->delete_meta_data('_bank_notify_code_assigned_at');
    }

    /**
     * Calculate expiry time in seconds
     */
    private function calculate_expiry_seconds($value, $unit)
    {
        switch ($unit) {
            case 'minutes':
                return $value * 60;
            case 'hours':
                return $value * 3600;
            case 'days':
                return $value * 86400;
            case 'weeks':
                return $value * 604800;
            case 'months':
                return $value * 2592000; // 30 days
            default:
                return $value * 3600; // Default to hours
        }
    }

    /**
     * Mark a code as used (payment completed)
     */
    public function mark_code_as_used($code)
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table_name,
            [
                'status' => 'used',
                'used_at' => current_time('mysql'),
            ],
            ['code' => $code],
            ['%s', '%s'],
            ['%s']
        );

        if ($updated !== false) {
            delete_transient('bank_notify_stats_cache');
        }

        return $updated !== false;
    }

    /**
     * Import codes from array
     * Returns statistics about import
     */
    public function import_codes($codes_array)
    {
        global $wpdb;

        $stats = [
            'total' => count($codes_array),
            'imported' => 0,
            'duplicates' => 0,
            'errors' => 0,
        ];

        foreach ($codes_array as $code) {
            // Sanitize code - allow Vietnamese characters and spaces
            $code = trim($code);

            // Skip empty codes
            if (empty($code)) {
                continue;
            }

            // Check if code already exists
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE code = %s',
                    $this->table_name,
                    $code
                )
            );

            if ($exists > 0) {
                $stats['duplicates']++;
                continue;
            }

            // Insert code
            $inserted = $wpdb->insert(
                $this->table_name,
                [
                    'code' => $code,
                    'status' => 'available',
                    'created_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s']
            );

            if ($inserted) {
                $stats['imported']++;
            } else {
                $stats['errors']++;
                WC_BankNotify_Logger::error('Failed to insert payment code', [
                    'code' => $code,
                    'db_error' => $wpdb->last_error,
                ]);
            }
        }

        // Clear stats cache
        delete_transient('bank_notify_stats_cache');

        return $stats;
    }

    /**
     * Get statistics (with caching)
     */
    public function get_stats($use_cache = true)
    {
        if ($use_cache) {
            $cached = get_transient('bank_notify_stats_cache');
            if ($cached !== false) {
                return $cached;
            }
        }

        $stats = $this->db->get_stats();

        // Cache for 5 minutes
        set_transient('bank_notify_stats_cache', $stats, 5 * MINUTE_IN_SECONDS);

        return $stats;
    }

    /**
     * Delete all codes
     */
    public function delete_all_codes()
    {
        $result = $this->db->truncate_table();
        delete_transient('bank_notify_stats_cache');
        return $result !== false;
    }

    /**
     * Get code by order ID
     */
    public function get_code_by_order_id($order_id)
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                'SELECT code FROM %i WHERE order_id = %d',
                $this->table_name,
                $order_id
            )
        );
    }

    /**
     * Get all codes
     */
    public function get_all_codes($status = null, $limit = null, $offset = 0)
    {
        return $this->db->get_all_codes($status, $limit, $offset);
    }

    /**
     * Get codes as array of strings
     */
    public function get_codes_array($status = null)
    {
        return $this->db->get_codes_array($status);
    }

    /**
     * Update codes from text input
     * Compares with existing codes and adds/removes as needed
     */
    public function update_codes_from_text($codes_text)
    {
        // Parse new codes from text
        $new_codes = array_filter(array_map('trim', explode("\n", $codes_text)));
        $new_codes = array_unique($new_codes);

        // Get existing codes
        $existing_codes = $this->get_codes_array();

        // Find codes to add and remove
        $codes_to_add = array_diff($new_codes, $existing_codes);
        $codes_to_remove = array_diff($existing_codes, $new_codes);

        $stats = [
            'added' => 0,
            'removed' => 0,
            'kept' => count(array_intersect($new_codes, $existing_codes)),
            'errors' => 0,
        ];

        // Remove codes that are no longer in the list
        if (!empty($codes_to_remove)) {
            global $wpdb;
            foreach ($codes_to_remove as $code) {
                $deleted = $wpdb->delete(
                    $this->table_name,
                    ['code' => $code],
                    ['%s']
                );
                if ($deleted !== false) {
                    $stats['removed']++;
                } else {
                    $stats['errors']++;
                }
            }
        }

        // Add new codes
        if (!empty($codes_to_add)) {
            $result = $this->import_codes($codes_to_add);
            $stats['added'] = $result['imported'];
            $stats['errors'] += $result['errors'];
        }

        // Clear cache
        delete_transient('bank_notify_stats_cache');

        return $stats;
    }

    /**
     * Check if there are any codes in the system
     */
    public function has_codes()
    {
        $stats = $this->get_stats();
        return $stats['total'] > 0;
    }

    /**
     * Check if there are available codes
     */
    public function has_available_codes()
    {
        $stats = $this->get_stats();
        return $stats['available'] > 0;
    }
}
