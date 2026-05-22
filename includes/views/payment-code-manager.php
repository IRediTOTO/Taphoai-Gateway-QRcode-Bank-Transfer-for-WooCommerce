<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!bank_notify_current_user_can_manage()) {
    wp_die(esc_html__('You do not have permission to access this page.', 'taphoai-gateway-qrcode-bank-transfer-for-woocommerce'));
}

$bank_notify_manager = new Taphoai_BankNotify_Payment_Code_Manager();
$bank_notify_stats = $bank_notify_manager->get_stats(false);

// Handle form submissions
$bank_notify_message = '';
$bank_notify_message_type = '';

if (isset($_POST['bank_notify_action'])) {
    check_admin_referer('bank_notify_payment_codes_action');

    $bank_notify_action = sanitize_key(wp_unslash($_POST['bank_notify_action']));

    if ($bank_notify_action === 'import' && !empty($_POST['payment_codes'])) {
        $bank_notify_codes_text = sanitize_textarea_field(wp_unslash($_POST['payment_codes']));
        $bank_notify_codes_array = array_filter(array_map('trim', explode("\n", $bank_notify_codes_text)));

        $bank_notify_result = $bank_notify_manager->import_codes($bank_notify_codes_array);

        $bank_notify_message = sprintf(
            'Đã import thành công %d mã. Trùng lặp: %d. Lỗi: %d.',
            $bank_notify_result['imported'],
            $bank_notify_result['duplicates'],
            $bank_notify_result['errors']
        );
        $bank_notify_message_type = 'success';

        // Refresh stats
        $bank_notify_stats = $bank_notify_manager->get_stats(false);
    } elseif ($bank_notify_action === 'release_expired') {
        $bank_notify_released_count = $bank_notify_manager->release_expired_codes();

        if ($bank_notify_released_count > 0) {
            $bank_notify_message = sprintf('Đã giải phóng %d mã hết hạn.', $bank_notify_released_count);
            $bank_notify_message_type = 'success';
        } else {
            $bank_notify_message = 'Không có mã nào hết hạn hoặc chức năng hết hạn chưa được bật.';
            $bank_notify_message_type = 'info';
        }

        // Refresh stats
        $bank_notify_stats = $bank_notify_manager->get_stats(false);
    } elseif ($bank_notify_action === 'delete_all') {
        $bank_notify_manager->delete_all_codes();
        $bank_notify_message = 'Đã xóa tất cả mã thanh toán.';
        $bank_notify_message_type = 'success';

        // Refresh stats
        $bank_notify_stats = $bank_notify_manager->get_stats(false);
    } elseif ($bank_notify_action === 'recreate_table') {
        // Recreate table
        $bank_notify_db = new Taphoai_BankNotify_DB();
        $bank_notify_db->drop_tables();
        $bank_notify_db->create_tables();

        if ($bank_notify_db->table_exists()) {
            $bank_notify_message = 'Đã tạo lại bảng database thành công!';
            $bank_notify_message_type = 'success';
        } else {
            $bank_notify_message = 'Không thể tạo bảng database. Vui lòng kiểm tra quyền database.';
            $bank_notify_message_type = 'error';
        }

        // Refresh stats
        $bank_notify_stats = $bank_notify_manager->get_stats(false);
    }
}

// Get current tab
$bank_notify_current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'import';
?>

<div class="wrap">
    <h1>Quản lý mã thanh toán</h1>

    <?php if ($bank_notify_message): ?>
        <div class="notice notice-<?php echo esc_attr($bank_notify_message_type); ?> is-dismissible">
            <p><?php echo esc_html($bank_notify_message); ?></p>
        </div>
    <?php endif; ?>

    <?php settings_errors('bank_notify_messages'); ?>

    <!-- Tabs Navigation -->
    <h2 class="nav-tab-wrapper">
           <a href="<?php echo esc_url(add_query_arg('tab', 'import', remove_query_arg(['status', 'paged']))); ?>"
           class="nav-tab <?php echo esc_attr($bank_notify_current_tab === 'import' ? 'nav-tab-active' : ''); ?>">
            <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
            Import mã
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'list', remove_query_arg(['status', 'paged']))); ?>"
           class="nav-tab <?php echo esc_attr($bank_notify_current_tab === 'list' ? 'nav-tab-active' : ''); ?>">
            <span class="dashicons dashicons-list-view" style="margin-top: 3px;"></span>
            Danh sách mã
        </a>
    </h2>

    <!-- Tab Content -->
    <div class="bank-notify-tab-content" style="margin-top: 20px;">
        <?php
        if ($bank_notify_current_tab === 'import') {
            require_once dirname(__FILE__) . '/tab-import.php';
        } elseif ($bank_notify_current_tab === 'list') {
            require_once dirname(__FILE__) . '/tab-list-codes.php';
        }
        ?>
    </div>
</div>
