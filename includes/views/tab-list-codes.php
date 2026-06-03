<?php
if (!defined('ABSPATH')) {
    exit;
}

// Check if there are any codes in the system
$taphgaqr_manager = new TaphGaqr_Payment_Code_Manager();
$taphgaqr_stats = $taphgaqr_manager->get_stats(false);

// Create list table instance
$taphgaqr_list_table = new TaphGaqr_Payment_Codes_List_Table();
$taphgaqr_list_table->prepare_items();
?>

<div class="bank-notify-list-section">
    <?php if ($taphgaqr_stats['total'] == 0): ?>
        <div class="notice notice-warning inline">
            <p>
                <strong>Chưa có mã thanh toán nào trong hệ thống!</strong><br>
                Vui lòng <a href="<?php echo esc_url(add_query_arg('tab', 'import')); ?>">import mã thanh toán</a>
                để có thể sử dụng chức năng thanh toán tự động.
            </p>
        </div>
    <?php elseif ($taphgaqr_stats['total'] < 50): ?>
        <div class="notice notice-warning inline">
            <p>
                <strong>Cảnh báo:</strong> Hệ thống chỉ còn <strong><?php echo esc_html($taphgaqr_stats['total']); ?> mã</strong> trong tổng số.
                Nên có ít nhất 50 mã để đảm bảo hoạt động ổn định.
                Vui lòng <a href="<?php echo esc_url(add_query_arg('tab', 'import')); ?>">thêm mã mới</a>.
            </p>
        </div>
    <?php elseif ($taphgaqr_stats['available'] == 0 && $taphgaqr_stats['assigned'] > 0): ?>
        <div class="notice notice-info inline">
            <p>
                <strong>Thông báo:</strong> Hiện tại không còn mã available.
                Khi có đơn hàng mới, hệ thống sẽ tự động giải phóng mã cũ nhất để tái sử dụng.
            </p>
        </div>
    <?php elseif ($taphgaqr_stats['available'] < 10 && $taphgaqr_stats['total'] >= 50): ?>
        <div class="notice notice-info inline">
            <p>
                <strong>Thông báo:</strong> Chỉ còn <strong><?php echo esc_html($taphgaqr_stats['available']); ?> mã available</strong>.
                Nên thêm mã mới hoặc giải phóng các mã đã hết hạn để tránh gián đoạn.
            </p>
        </div>
    <?php endif; ?>

    <form method="get">
        <input type="hidden" name="page" value="bank-notify-payment-codes">
        <input type="hidden" name="tab" value="list">
        <?php
        $taphgaqr_list_table->search_box('Tìm kiếm mã', 'payment_code');
        $taphgaqr_list_table->views();
        ?>
    </form>

    <form method="post">
        <?php
        $taphgaqr_list_table->display();
        ?>
    </form>
</div>

