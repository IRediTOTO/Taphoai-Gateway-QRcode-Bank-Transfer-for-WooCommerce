<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger class for Bank Notify plugin
 * Handles debug logging to bank-notify.log file
 */
class Taphoai_BankNotify_Logger
{
    /**
     * Log file name
     */
    const LOG_FILENAME = 'bank-notify';

    /**
     * WooCommerce logger instance
     *
     * @var WC_Logger
     */
    private static $logger = null;

    /**
     * Check if debug logging is enabled
     *
     * @return bool
     */
    public static function is_enabled()
    {
        $gateway = self::get_gateway();
        if (!$gateway) {
            return false;
        }

        return $gateway->get_option('debug_log_enabled', 'no') === 'yes';
    }

    /**
     * Get gateway instance
     *
     * @return Taphoai_Gateway_BankNotify|null
     */
    private static function get_gateway()
    {
        if (!function_exists('WC')) {
            return null;
        }

        $gateways = WC()->payment_gateways()->payment_gateways();
        return isset($gateways['bank_notify']) ? $gateways['bank_notify'] : null;
    }

    /**
     * Get logger instance
     *
     * @return WC_Logger
     */
    private static function get_logger()
    {
        if (self::$logger === null) {
            self::$logger = wc_get_logger();
        }

        return self::$logger;
    }

    /**
     * Log debug message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function debug($message, $context = [])
    {
        self::log('debug', $message, $context);
    }

    /**
     * Log info message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function info($message, $context = [])
    {
        self::log('info', $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function warning($message, $context = [])
    {
        self::log('warning', $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function error($message, $context = [])
    {
        self::log('error', $message, $context);
    }

    /**
     * Log message with level
     *
     * @param string $level Log level (debug, info, warning, error)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private static function log($level, $message, $context = [])
    {
        if (!self::is_enabled()) {
            return;
        }

        $logger = self::get_logger();
        if (!$logger) {
            return;
        }

        // Format context data
        $context_string = '';
        if (!empty($context)) {
            $context_string = ' | Context: ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $formatted_message = sprintf('[Bank Notify] %s%s', $message, $context_string);

        // Log with appropriate level
        $logger->log($level, $formatted_message, ['source' => self::LOG_FILENAME]);
    }

    /**
     * Get log file path
     *
     * @return string|null
     */
    public static function get_log_file_path()
    {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wc-logs/';

        if (!file_exists($log_dir)) {
            return null;
        }

        // Find the latest log file
        $files = glob($log_dir . self::LOG_FILENAME . '-*.log');
        if (empty($files)) {
            return null;
        }

        // Sort by modification time, newest first
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $files[0];
    }
}

if (!class_exists('WC_BankNotify_Logger', false)) {
    class_alias('Taphoai_BankNotify_Logger', 'WC_BankNotify_Logger');
}
