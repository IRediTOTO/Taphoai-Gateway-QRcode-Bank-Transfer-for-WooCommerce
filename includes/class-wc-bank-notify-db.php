<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Custom plugin table operations require direct $wpdb calls.

class TaphGaqr_DB
{
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'taphgaqr_payment_codes';
    }

    /**
     * Get table name
     */
    public function get_table_name()
    {
        return $this->table_name;
    }

    /**
     * Create tables when plugin is activated
     */
    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(255) NOT NULL,
            status ENUM('available', 'assigned', 'used') DEFAULT 'available',
            order_id BIGINT(20) UNSIGNED NULL,
            assigned_at DATETIME NULL,
            used_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_order_id (order_id),
            INDEX idx_code (code),
            UNIQUE KEY unique_code (code)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Verify table was created
        if ($this->table_exists()) {
            // Store database version
            update_option('taphgaqr_db_version', '1.0.0');
        } else {
            do_action('taphgaqr_db_create_failed', $this->table_name);
        }
    }

    /**
     * Check if table exists
     */
    public function table_exists()
    {
        global $wpdb;
        $table = $this->table_name;
        
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /**
     * Drop tables (optional - for uninstall)
     */
    public function drop_tables()
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $this->table_name));
        delete_option('taphgaqr_db_version');
    }

    /**
     * Get database statistics
     */
    public function get_stats()
    {
        global $wpdb;

        $stats = [
            'total' => 0,
            'available' => 0,
            'assigned' => 0,
            'used' => 0,
        ];

        $results = $wpdb->get_results(
            $wpdb->prepare('SELECT status, COUNT(*) as count FROM %i GROUP BY status', $this->table_name),
            ARRAY_A
        );

        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Truncate table (delete all codes)
     */
    public function truncate_table()
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare('TRUNCATE TABLE %i', $this->table_name));
    }

    /**
     * Get all codes with optional filtering
     */
    public function get_all_codes($status = null, $limit = null, $offset = 0)
    {
        global $wpdb;

        if ($status && $limit) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d',
                    $this->table_name,
                    $status,
                    $limit,
                    $offset
                )
            );
        }

        if ($status) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE status = %s ORDER BY created_at DESC',
                    $this->table_name,
                    $status
                )
            );
        }

        if ($limit) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d',
                    $this->table_name,
                    $limit,
                    $offset
                )
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i ORDER BY created_at DESC', $this->table_name)
        );
    }

    /**
     * Get codes as simple array (just the code strings)
     */
    public function get_codes_array($status = null)
    {
        global $wpdb;

        if ($status) {
            return $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT code FROM %i WHERE status = %s ORDER BY code ASC',
                    $this->table_name,
                    $status
                )
            );
        }

        return $wpdb->get_col(
            $wpdb->prepare('SELECT code FROM %i ORDER BY code ASC', $this->table_name)
        );
    }
}

if (!class_exists('TaphGaqr_WC_DB', false)) {
    class_alias('TaphGaqr_DB', 'TaphGaqr_WC_DB');
}
