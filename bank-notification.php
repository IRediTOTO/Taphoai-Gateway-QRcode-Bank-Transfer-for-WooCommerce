<?php
/*
 * Plugin Name: Taphoai Gateway - QRcode Bank Transfer for WooCommerce
 * Description: Taphoai Gateway - Giải pháp tự động xác nhận thanh toán chuyển khoản ngân hàng qua QR code cho WooCommerce.
 * Author: Taphoai
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: taphoai-gateway-qrcode-bank-transfer-for-woocommerce
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

if (!defined('ABSPATH')) {
    exit;
}


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'bank_notify_add_action_links');

function bank_notify_add_action_links($links): array
{
    $plugin_links = array(
        '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=bank_notify')) . '">Cài đặt</a>',
    );

    return array_merge($plugin_links, $links);
}

add_action('plugins_loaded', 'bank_notify_init_gateway_class');

function bank_notify_missing_wc_notice()
{
    $install_url = wp_nonce_url(
        add_query_arg(
            [
                'action' => 'install-plugin',
                'plugin' => 'woocommerce',
            ],
            admin_url('update.php')
        ),
        'install-plugin_woocommerce'
    );

    $admin_notice_content = sprintf(
        '%1$sWooCommerce chưa được kích hoạt.%2$s Plugin %3$sWooCommerce%4$s phải được kích hoạt để Taphoai Gateway có thể hoạt động. Vui lòng %5$scài đặt & kích hoạt WooCommerce &raquo;%6$s',
        '<strong>',
        '</strong>',
        '<a href="https://wordpress.org/plugins/woocommerce/">',
        '</a>',
        '<a href="' . esc_url($install_url) . '">',
        '</a>'
    );

    echo '<div class="error">';
    echo '<p>' . wp_kses_post($admin_notice_content) . '</p>';
    echo '</div>';
}

add_filter('woocommerce_payment_gateways', 'bank_notify_add_gateway_class');

function bank_notify_add_gateway_class($gateways)
{
    $gateways[] = 'Taphoai_Gateway_BankNotify';
    return $gateways;
}

function bank_notify_current_user_can_manage(): bool
{
    return current_user_can('manage_woocommerce') || current_user_can('manage_options');
}

function bank_notify_init_gateway_class()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'bank_notify_missing_wc_notice');
        return;
    }

    require_once dirname(__FILE__) . '/includes/class-wc-bank-notify-logger.php';
    require_once dirname(__FILE__) . '/includes/class-wc-gateway-bank-notify.php';
    require_once dirname(__FILE__) . '/includes/class-wc-bank-notify-db.php';
    require_once dirname(__FILE__) . '/includes/class-wc-bank-notify-payment-code-manager.php';
    require_once dirname(__FILE__) . '/includes/class-wc-bank-notify-payment-codes-list-table.php';

    // Load parsers
    require_once dirname(__FILE__) . '/includes/parsers/abstract-wc-bank-notify-parser.php';
    require_once dirname(__FILE__) . '/includes/parsers/class-wc-bank-notify-parser-tpbank.php';
    require_once dirname(__FILE__) . '/includes/parsers/class-wc-bank-notify-parser-mbbank.php';
    require_once dirname(__FILE__) . '/includes/parsers/class-wc-bank-notify-parser-factory.php';

    require_once dirname(__FILE__) . '/includes/class-wc-bank-notify-webhook-handler.php';

    // Initialize webhook handler
    new Taphoai_BankNotify_Webhook_Handler();
}

add_action('before_woocommerce_init', 'bank_notify_declare_woocommerce_support');



function bank_notify_declare_woocommerce_support()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__);
    }
}

add_action('woocommerce_blocks_loaded', 'bank_notify_woocommerce_block_support');

function bank_notify_woocommerce_block_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once dirname(__FILE__) . '/includes/class-wc-bank-notify-blocks-support.php';

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new Taphoai_BankNotify_Blocks_Support());
            }
        );
    }
}

add_action('admin_enqueue_scripts', 'bank_notify_add_scripts');

function bank_notify_add_scripts($hook)
{
    if ($hook !== 'woocommerce_page_wc-settings') {
        return;
    }

    $page = filter_input(INPUT_GET, 'page', FILTER_UNSAFE_RAW);
    $tab = filter_input(INPUT_GET, 'tab', FILTER_UNSAFE_RAW);
    $section = filter_input(INPUT_GET, 'section', FILTER_UNSAFE_RAW);

    if ($page === null || $tab === null || $section === null) {
        return;
    }

    if (
        sanitize_key(wp_unslash($page)) !== 'wc-settings' ||
        sanitize_key(wp_unslash($tab)) !== 'checkout' ||
        sanitize_key(wp_unslash($section)) !== 'bank_notify'
    ) {
        return;
    }

    $script_path = plugin_dir_path(__FILE__) . 'assets/js/main.js';

    if (file_exists($script_path)) {
        $script_version = filemtime($script_path);
    } else {
        $script_version = '';
    }

    wp_register_script(
        'bank-notify-option-js',
        set_url_scheme(plugin_dir_url(__FILE__) . 'assets/js/main.js'),
        ['jquery', 'wc-enhanced-select'],
        $script_version,
        true
    );

    $admin_style_path = plugin_dir_path(__FILE__) . 'assets/css/admin-settings.css';
    $admin_style_version = file_exists($admin_style_path) ? filemtime($admin_style_path) : '1.0.0';

    wp_enqueue_style(
        'bank-notify-admin-settings',
        set_url_scheme(plugin_dir_url(__FILE__) . 'assets/css/admin-settings.css'),
        [],
        $admin_style_version
    );

    wp_enqueue_media();
    wp_enqueue_script('bank-notify-option-js');
}

register_activation_hook(__FILE__, 'bank_notify_activate');

function bank_notify_activate()
{
    // Create database tables
    require_once dirname(__FILE__) . '/includes/class-wc-bank-notify-db.php';
    $db = new Taphoai_BankNotify_DB();
    $db->create_tables();

    // Schedule cron job for releasing expired codes
    if (!wp_next_scheduled('bank_notify_release_expired_codes')) {
        wp_schedule_event(time(), 'hourly', 'bank_notify_release_expired_codes');
    }

    // Set flag to check for payment codes after activation
    set_transient('wc_bank_notify_check_codes', true, 60);
}

register_deactivation_hook(__FILE__, 'bank_notify_deactivate');

function bank_notify_deactivate()
{
    // Clear scheduled cron job
    $timestamp = wp_next_scheduled('bank_notify_release_expired_codes');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'bank_notify_release_expired_codes');
    }
}

// Check for payment codes after activation and show warnings
add_action('admin_notices', 'bank_notify_check_codes_notice');

function bank_notify_check_codes_notice()
{
    // Only show on admin pages
    if (!is_admin()) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $screen_id = $screen ? $screen->id : '';
    $allowed_screens = [
        'woocommerce_page_wc-settings',
        'woocommerce_page_bank-notify-payment-codes',
        'toplevel_page_bank-notify-payment-codes',
    ];

    if (!in_array($screen_id, $allowed_screens, true)) {
        return;
    }

    // Check if we should show the notice after activation
    $show_activation_notice = get_transient('wc_bank_notify_check_codes');
    if ($show_activation_notice) {
        delete_transient('wc_bank_notify_check_codes');
    }

    // Load required classes
    if (!class_exists('Taphoai_BankNotify_Payment_Code_Manager')) {
        require_once dirname(__FILE__) . '/includes/class-wc-bank-notify-db.php';
        require_once dirname(__FILE__) . '/includes/class-wc-bank-notify-payment-code-manager.php';
    }

    $manager = new Taphoai_BankNotify_Payment_Code_Manager();
    $stats = $manager->get_stats(true); // Use cache for performance

    // Check if gateway is enabled
    $gateway_enabled = false;
    if (class_exists('WooCommerce')) {
        $gateways = WC()->payment_gateways()->payment_gateways();
        if (isset($gateways['bank_notify'])) {
            $gateway_enabled = $gateways['bank_notify']->enabled === 'yes';
        }
    }

    // Only show warnings if gateway is enabled
    if (!$gateway_enabled) {
        return;
    }

    $import_url = admin_url('admin.php?page=bank-notify-payment-codes&tab=import');

    // Warning: No codes at all
    if ($stats['total'] == 0) {
        $message = sprintf(
            '<strong>Taphoai Gateway:</strong> Chưa có mã thanh toán nào trong hệ thống. ' .
            'Vui lòng <a href="%s">thêm mã thanh toán</a> để có thể sử dụng chức năng thanh toán.',
            esc_url($import_url)
        );

        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>' . wp_kses_post($message) . '</p>';
        echo '</div>';
        return;
    }

    // Warning: Less than 50 codes
    if ($stats['total'] < 50) {
        $message = sprintf(
            '<strong>Taphoai Gateway:</strong> Hệ thống chỉ còn <strong>%d mã</strong> thanh toán. ' .
            'Nên có ít nhất 50 mã để đảm bảo hoạt động ổn định. ' .
            'Vui lòng <a href="%s">thêm mã mới</a>.',
            $stats['total'],
            esc_url($import_url)
        );

        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>' . wp_kses_post($message) . '</p>';
        echo '</div>';
        return;
    }

    // Info: Less than 10 available codes
    if ($stats['available'] < 10 && $stats['total'] >= 50) {
        $message = sprintf(
            '<strong>Taphoai Gateway:</strong> Chỉ còn <strong>%d mã available</strong>. ' .
            'Nên <a href="%s">thêm mã mới</a> hoặc giải phóng các mã đã hết hạn để tránh gián đoạn.',
            $stats['available'],
            esc_url($import_url)
        );

        echo '<div class="notice notice-info is-dismissible">';
        echo '<p>' . wp_kses_post($message) . '</p>';
        echo '</div>';
        return;
    }
}

// Handle log viewing and clearing
add_action('admin_init', 'bank_notify_handle_log_actions');

function bank_notify_handle_log_actions()
{
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';
    $view_log = isset($_GET['view_log']) ? sanitize_text_field(wp_unslash($_GET['view_log'])) : '';
    $clear_log = isset($_GET['clear_log']) ? sanitize_text_field(wp_unslash($_GET['clear_log'])) : '';

    // View log
    if ($page === 'wc-settings' && $section === 'bank_notify' && $view_log === '1') {
        if (!bank_notify_current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to view this log.', 'taphoai-gateway-qrcode-bank-transfer-for-woocommerce'));
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'view_bank_notify_log')) {
            wp_die(esc_html__('Security check failed.', 'taphoai-gateway-qrcode-bank-transfer-for-woocommerce'));
        }

        $log_file_path = Taphoai_BankNotify_Logger::get_log_file_path();

        if ($log_file_path && file_exists($log_file_path)) {
            global $wp_filesystem;

            if (!function_exists('WP_Filesystem')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            WP_Filesystem();

            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: inline; filename="' . sanitize_file_name(basename($log_file_path)) . '"');
            echo esc_html($wp_filesystem->get_contents($log_file_path));
            exit;
        } else {
            wp_die(esc_html__('Log file not found.', 'taphoai-gateway-qrcode-bank-transfer-for-woocommerce'));
        }
    }

    // Clear log
    if ($page === 'wc-settings' && $section === 'bank_notify' && $clear_log === '1') {
        if (!bank_notify_current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to clear this log.', 'taphoai-gateway-qrcode-bank-transfer-for-woocommerce'));
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'clear_bank_notify_log')) {
            wp_die(esc_html__('Security check failed.', 'taphoai-gateway-qrcode-bank-transfer-for-woocommerce'));
        }

        $log_file_path = Taphoai_BankNotify_Logger::get_log_file_path();

        if ($log_file_path && file_exists($log_file_path)) {
            wp_delete_file($log_file_path);
            wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=bank_notify'));
            exit;
        }
    }
}

add_action('upgrader_process_complete', 'bank_notify_clear_cache_after_update', 10, 2);

function bank_notify_clear_cache_after_update($upgrader_object, $options)
{
    if (
        isset($options['action'], $options['type'], $options['plugins']) &&
        $options['action'] === 'update' &&
        $options['type'] === 'plugin'
    ) {
        foreach ($options['plugins'] as $plugin) {
            if (plugin_basename(__FILE__) === $plugin) {
                delete_transient('wc_bank_notify_bank_accounts');
                break;
            }
        }
    }
}

// Add admin menu for payment code management
add_action('admin_menu', 'bank_notify_add_admin_menu', 99);

function bank_notify_add_admin_menu()
{
    add_menu_page(
        'Quản lý mã thanh toán',
        'Mã thanh toán',
        'read',
        'bank-notify-payment-codes',
        'bank_notify_payment_codes_page',
        '',
        null
    );

    remove_menu_page('bank-notify-payment-codes');
}

function bank_notify_payment_codes_page()
{
    require_once dirname(__FILE__) . '/includes/views/payment-code-manager.php';
}

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'bank_notify_enqueue_admin_scripts');

function bank_notify_enqueue_admin_scripts($hook)
{
    // Only load on payment codes page
    if (!in_array($hook, ['woocommerce_page_bank-notify-payment-codes', 'toplevel_page_bank-notify-payment-codes'], true)) {
        return;
    }

    wp_enqueue_script(
        'bank-notify-admin',
        plugins_url('assets/js/admin-payment-codes.js', __FILE__),
        ['jquery'],
        '1.0.0',
        true
    );

    wp_localize_script('bank-notify-admin', 'bankNotifyAdmin', [
        'nonce' => wp_create_nonce('bank_notify_admin_action'),
    ]);
}

// Hook to release code when order is cancelled or failed
add_action('woocommerce_order_status_cancelled', 'bank_notify_release_code_on_cancel');
add_action('woocommerce_order_status_failed', 'bank_notify_release_code_on_cancel');

function bank_notify_release_code_on_cancel($order_id)
{
    $order = wc_get_order($order_id);
    if ($order && $order->get_payment_method() === 'bank_notify') {
        $code = $order->get_meta('_bank_notify_payment_code');
        $mode = $order->get_meta('_bank_notify_payment_mode');

        if ($code && $mode === 'natural') {
            $manager = new Taphoai_BankNotify_Payment_Code_Manager();
            if ($manager->get_code_status($code) === 'assigned') {
                $manager->release_code($code);
            }
        }
    }
}

// AJAX: Delete single code
add_action('wp_ajax_bank_notify_delete_single_code', 'bank_notify_delete_single_code_handler');

function bank_notify_delete_single_code_handler()
{
    check_ajax_referer('bank_notify_admin_action', 'nonce');

    if (!bank_notify_current_user_can_manage()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';

    if (empty($code)) {
        wp_send_json_error(['message' => 'Code is required']);
    }

    $manager = new Taphoai_BankNotify_Payment_Code_Manager();
    $deleted = $manager->delete_code($code);

    if ($deleted !== false) {
        delete_transient('bank_notify_stats_cache');
        wp_send_json_success(['message' => 'Code deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete code']);
    }
}

// AJAX: Release single code
add_action('wp_ajax_bank_notify_release_single_code', 'bank_notify_release_single_code_handler');

function bank_notify_release_single_code_handler()
{
    check_ajax_referer('bank_notify_admin_action', 'nonce');

    if (!bank_notify_current_user_can_manage()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';

    if (empty($code)) {
        wp_send_json_error(['message' => 'Code is required']);
    }

    $manager = new Taphoai_BankNotify_Payment_Code_Manager();
    if ($manager->get_code_status($code) !== 'assigned') {
        wp_send_json_error(['message' => 'Only assigned codes can be released']);
    }

    $result = $manager->release_code($code);

    if ($result) {
        wp_send_json_success(['message' => 'Code released successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to release code']);
    }
}

// AJAX: Check order status (called from order-received page)
add_action('wp_ajax_bank_notify_check_order_status', 'bank_notify_check_order_status_handler');
add_action('wp_ajax_nopriv_bank_notify_check_order_status', 'bank_notify_check_order_status_handler');

function bank_notify_check_order_status_handler()
{
    check_ajax_referer('submit_order', 'order_nonce');

    $order_id = isset($_POST['orderID']) ? absint(wp_unslash($_POST['orderID'])) : 0;
    $order_key = isset($_POST['orderKey']) ? sanitize_text_field(wp_unslash($_POST['orderKey'])) : '';

    if (!$order_id) {
        wp_send_json_error(['message' => 'Order ID is required']);
    }

    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error(['message' => 'Order not found']);
    }

    if (!$order_key || !hash_equals($order->get_order_key(), $order_key)) {
        wp_send_json_error(['message' => 'Invalid order key'], 403);
    }

    $order_status = $order->get_status();
    $downloads = [];

    // Get downloadable products if order is completed or processing
    if (in_array($order_status, ['processing', 'completed'])) {
        $downloads = bank_notify_prepare_downloads_for_response($order->get_downloadable_items());
    }

    wp_send_json([
        'status' => true,
        'order_status' => $order_status,
        'downloads' => $downloads,
    ]);
}

function bank_notify_prepare_downloads_for_response($downloads)
{
    $prepared = [];

    foreach ($downloads as $download) {
        $access_expires = '';

        if (!empty($download['access_expires'])) {
            if ($download['access_expires'] instanceof WC_DateTime || $download['access_expires'] instanceof DateTimeInterface) {
                $access_expires = $download['access_expires']->date('c');
            } else {
                $access_expires = sanitize_text_field((string) $download['access_expires']);
            }
        }

        $downloads_remaining = $download['downloads_remaining'] ?? '';
        $downloads_remaining = $downloads_remaining === '' ? '' : absint($downloads_remaining);

        $prepared[] = [
            'id' => sanitize_text_field((string) ($download['download_id'] ?? '')),
            'name' => sanitize_text_field((string) ($download['download_name'] ?? '')),
            'product_name' => sanitize_text_field((string) ($download['product_name'] ?? '')),
            'download_url' => esc_url_raw((string) ($download['download_url'] ?? '')),
            'downloads_remaining' => $downloads_remaining,
            'access_expires' => $access_expires,
        ];
    }

    return $prepared;
}

// Schedule cron job for releasing expired payment codes
add_action('wp', 'bank_notify_schedule_cron');

function bank_notify_schedule_cron()
{
    if (!wp_next_scheduled('bank_notify_release_expired_codes')) {
        wp_schedule_event(time(), 'hourly', 'bank_notify_release_expired_codes');
    }
}

// Cron job: Release codes for orders based on expiry settings
add_action('bank_notify_release_expired_codes', 'bank_notify_release_expired_codes_handler');

function bank_notify_release_expired_codes_handler()
{
    $manager = new Taphoai_BankNotify_Payment_Code_Manager();
    $released_count = $manager->release_expired_codes();

    if ($released_count > 0) {
        Taphoai_BankNotify_Logger::info('Released expired payment codes', [
            'released_count' => $released_count,
        ]);
    }
}
