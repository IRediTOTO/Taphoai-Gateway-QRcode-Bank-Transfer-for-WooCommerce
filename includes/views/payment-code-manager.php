<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!taphgaqr_current_user_can_manage()) {
    wp_die(esc_html__('You do not have permission to access this page.', 'taphoai-gateway-qrcode-bank-transfer-for-woocommerce'));
}

$taphgaqr_manager = new TaphGaqr_Payment_Code_Manager();
$taphgaqr_stats = $taphgaqr_manager->get_stats(false);

// Handle form submissions
$taphgaqr_message = '';
$taphgaqr_message_type = '';

if (isset($_POST['taphgaqr_action'])) {
    check_admin_referer('taphgaqr_payment_codes_action');

    $taphgaqr_action = sanitize_key(wp_unslash($_POST['taphgaqr_action']));

    if ($taphgaqr_action === 'import' && !empty($_POST['payment_codes'])) {
        $taphgaqr_codes_text = sanitize_textarea_field(wp_unslash($_POST['payment_codes']));
        $taphgaqr_codes_array = array_filter(array_map('trim', explode("\n", $taphgaqr_codes_text)));

        $taphgaqr_result = $taphgaqr_manager->import_codes($taphgaqr_codes_array);

        $taphgaqr_message = sprintf(
            'Đã import thành công %d mã. Trùng lặp: %d. Quá ngắn: %d. Lỗi: %d.',
            $taphgaqr_result['imported'],
            $taphgaqr_result['duplicates'],
            $taphgaqr_result['too_short'],
            $taphgaqr_result['errors']
        );
        $taphgaqr_message_type = 'success';

        // Refresh stats
        $taphgaqr_stats = $taphgaqr_manager->get_stats(false);
    } elseif ($taphgaqr_action === 'release_expired') {
        $taphgaqr_released_count = $taphgaqr_manager->release_expired_codes();

        if ($taphgaqr_released_count > 0) {
            $taphgaqr_message = sprintf('Đã giải phóng %d mã hết hạn.', $taphgaqr_released_count);
            $taphgaqr_message_type = 'success';
        } else {
            $taphgaqr_message = 'Không có mã nào hết hạn hoặc chức năng hết hạn chưa được bật.';
            $taphgaqr_message_type = 'info';
        }

        // Refresh stats
        $taphgaqr_stats = $taphgaqr_manager->get_stats(false);
    } elseif ($taphgaqr_action === 'delete_all') {
        $taphgaqr_manager->delete_all_codes();
        $taphgaqr_message = 'Đã xóa tất cả mã thanh toán.';
        $taphgaqr_message_type = 'success';

        // Refresh stats
        $taphgaqr_stats = $taphgaqr_manager->get_stats(false);
    } elseif ($taphgaqr_action === 'delete_available') {
        $taphgaqr_deleted_count = $taphgaqr_manager->delete_codes_by_status('available');

        if ($taphgaqr_deleted_count === false) {
            $taphgaqr_message = 'Không thể xóa mã thanh toán khả dụng. Vui lòng thử lại.';
            $taphgaqr_message_type = 'error';
        } else {
            $taphgaqr_message = sprintf('Đã xóa %d mã thanh toán khả dụng.', $taphgaqr_deleted_count);
            $taphgaqr_message_type = 'success';
        }

        // Refresh stats
        $taphgaqr_stats = $taphgaqr_manager->get_stats(false);
    } elseif ($taphgaqr_action === 'recreate_table') {
        // Recreate table
        $taphgaqr_db = new TaphGaqr_DB();
        $taphgaqr_db->drop_tables();
        $taphgaqr_db->create_tables();

        if ($taphgaqr_db->table_exists()) {
            $taphgaqr_message = 'Đã tạo lại bảng database thành công!';
            $taphgaqr_message_type = 'success';
        } else {
            $taphgaqr_message = 'Không thể tạo bảng database. Vui lòng kiểm tra quyền database.';
            $taphgaqr_message_type = 'error';
        }

        // Refresh stats
        $taphgaqr_stats = $taphgaqr_manager->get_stats(false);
    }
}

// Get current tab
$taphgaqr_current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'import';
?>

<div class="wrap">
    <h1>Quản lý mã thanh toán</h1>

    <?php if ($taphgaqr_message): ?>
        <div class="notice notice-<?php echo esc_attr($taphgaqr_message_type); ?> is-dismissible">
            <p><?php echo esc_html($taphgaqr_message); ?></p>
        </div>
    <?php endif; ?>

    <?php settings_errors('taphgaqr_messages'); ?>

    <!-- Tabs Navigation -->
    <h2 class="nav-tab-wrapper">
           <a href="<?php echo esc_url(add_query_arg('tab', 'import', remove_query_arg(['status', 'paged']))); ?>"
           class="nav-tab <?php echo esc_attr($taphgaqr_current_tab === 'import' ? 'nav-tab-active' : ''); ?>">
            <span class="dashicons dashicons-upload"></span>
            Import mã
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'list', remove_query_arg(['status', 'paged']))); ?>"
           class="nav-tab <?php echo esc_attr($taphgaqr_current_tab === 'list' ? 'nav-tab-active' : ''); ?>">
            <span class="dashicons dashicons-list-view"></span>
            Danh sách mã
        </a>
    </h2>

    <!-- Tab Content -->
    <div class="bank-notify-tab-content" style="margin-top: 20px;">
        <?php
        if ($taphgaqr_current_tab === 'import') {
            require_once dirname(__FILE__) . '/tab-import.php';
        } elseif ($taphgaqr_current_tab === 'list') {
            require_once dirname(__FILE__) . '/tab-list-codes.php';
        }
        ?>
    </div>
</div>
