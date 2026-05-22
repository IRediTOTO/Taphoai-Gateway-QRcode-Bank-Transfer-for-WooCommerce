<?php
if (!defined('ABSPATH')) {
    exit;
}

// Check if there are any codes in the system
$bank_notify_manager = new WC_BankNotify_Payment_Code_Manager();
$bank_notify_stats = $bank_notify_manager->get_stats(false);

// Create list table instance
$bank_notify_list_table = new WC_BankNotify_Payment_Codes_List_Table();
$bank_notify_list_table->prepare_items();
?>

<div class="bank-notify-list-section">
    <?php if ($bank_notify_stats['total'] == 0): ?>
        <div class="notice notice-warning inline">
            <p>
                <strong>Chưa có mã thanh toán nào trong hệ thống!</strong><br>
                Vui lòng <a href="<?php echo esc_url(add_query_arg('tab', 'import')); ?>">import mã thanh toán</a>
                để có thể sử dụng chức năng thanh toán tự động.
            </p>
        </div>
    <?php elseif ($bank_notify_stats['total'] < 50): ?>
        <div class="notice notice-warning inline">
            <p>
                <strong>Cảnh báo:</strong> Hệ thống chỉ còn <strong><?php echo esc_html($bank_notify_stats['total']); ?> mã</strong> trong tổng số.
                Nên có ít nhất 50 mã để đảm bảo hoạt động ổn định.
                Vui lòng <a href="<?php echo esc_url(add_query_arg('tab', 'import')); ?>">thêm mã mới</a>.
            </p>
        </div>
    <?php elseif ($bank_notify_stats['available'] == 0 && $bank_notify_stats['assigned'] > 0): ?>
        <div class="notice notice-info inline">
            <p>
                <strong>Thông báo:</strong> Hiện tại không còn mã available.
                Khi có đơn hàng mới, hệ thống sẽ tự động giải phóng mã cũ nhất để tái sử dụng.
            </p>
        </div>
    <?php elseif ($bank_notify_stats['available'] < 10 && $bank_notify_stats['total'] >= 50): ?>
        <div class="notice notice-info inline">
            <p>
                <strong>Thông báo:</strong> Chỉ còn <strong><?php echo esc_html($bank_notify_stats['available']); ?> mã available</strong>.
                Nên thêm mã mới hoặc giải phóng các mã đã hết hạn để tránh gián đoạn.
            </p>
        </div>
    <?php endif; ?>

    <form method="get">
        <input type="hidden" name="page" value="bank-notify-payment-codes">
        <input type="hidden" name="tab" value="list">
        <?php
        $bank_notify_list_table->search_box('Tìm kiếm mã', 'payment_code');
        $bank_notify_list_table->views();
        ?>
    </form>

    <form method="post">
        <?php
        $bank_notify_list_table->display();
        ?>
    </form>
</div>

<style>
.bank-notify-list-section .tablenav {
    margin: 10px 0;
}

.bank-notify-list-section .widefat {
    border: 1px solid #c3c4c7;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.bank-notify-list-section .widefat thead th,
.bank-notify-list-section .widefat tfoot th {
    background: #f6f7f7;
}

.bank-notify-list-section .widefat tbody tr:hover {
    background: #f6f7f7;
}

.bank-notify-list-section .subsubsub {
    margin: 10px 0;
}
</style>
