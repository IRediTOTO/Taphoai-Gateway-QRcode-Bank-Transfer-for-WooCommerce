<?php
if (!defined('ABSPATH')) {
    exit;
}

$bank_notify_manager = new Taphoai_BankNotify_Payment_Code_Manager();
?>

<!-- Import Codes Section -->
<div class="bank-notify-import-section" style="margin: 30px 0;">
    <h2>Import mã thanh toán</h2>
    <p class="description">
        Nhập danh sách mã thanh toán, mỗi mã một dòng. Hệ thống sẽ tự động bỏ qua các mã trùng lặp. Nên thêm ít nhất 500 mã để đảm bảo luôn có mã khả dụng cho khách hàng. Nếu hết mã khả dụng, hệ thống sẽ tự động chuyển về chế độ "Tiền tố + Mã đơn hàng". Mã có thể chứa chữ cái, số, khoảng trắng và các ký tự đặc biệt (trừ xuống dòng).
    </p>

    <form method="post" action="">
        <?php wp_nonce_field('bank_notify_payment_codes_action'); ?>
        <input type="hidden" name="bank_notify_action" value="import">

        <textarea
            name="payment_codes"
            rows="15"
            style="width: 100%; max-width: 800px; font-family: monospace; font-size: 13px;"
            placeholder="NGUYEN_VAN_A&#10;TRAN_THI_B&#10;LE_VAN_C&#10;..."
        ></textarea>

        <p style="margin-top: 15px;">
            <button type="submit" class="button button-primary">
                <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
                Import mã
            </button>
        </p>
    </form>
</div>

<!-- Danger Zone -->
<div class="bank-notify-danger-zone" style="margin: 40px 0; padding: 20px; background: #fff; border: 1px solid #dc3232; border-radius: 4px;">
    <h2 style="color: #dc3232; margin-top: 0;">
        <span class="dashicons dashicons-warning" style="margin-top: 3px;"></span>
        Vùng nguy hiểm
    </h2>

    <div style="margin: 20px 0;">
        <h3>Giải phóng mã hết hạn</h3>
        <p class="description">
            Giải phóng các mã đã được gán nhưng đã hết hạn theo cài đặt thời gian. Mã sẽ trở về trạng thái "Khả dụng".
        </p>
        <form method="post" action="" onsubmit="return confirm('Bạn có chắc chắn muốn giải phóng các mã hết hạn?');">
            <?php wp_nonce_field('bank_notify_payment_codes_action'); ?>
            <input type="hidden" name="bank_notify_action" value="release_expired">
            <button type="submit" class="button button-secondary">
                <span class="dashicons dashicons-clock" style="margin-top: 3px;"></span>
                Giải phóng mã hết hạn
            </button>
        </form>
    </div>

    <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">

    <div style="margin: 20px 0;">
        <h3>Xóa tất cả mã thanh toán</h3>
        <p class="description">
            Hành động này sẽ xóa tất cả mã thanh toán trong hệ thống. Không thể hoàn tác!
        </p>
        <form method="post" action="" onsubmit="return confirm('Bạn có chắc chắn muốn xóa TẤT CẢ mã thanh toán? Hành động này không thể hoàn tác!');">
            <?php wp_nonce_field('bank_notify_payment_codes_action'); ?>
            <input type="hidden" name="bank_notify_action" value="delete_all">
            <button type="submit" class="button" style="background: #dc3232; color: #fff; border-color: #dc3232;">
                <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
                Xóa tất cả mã
            </button>
        </form>
    </div>

    <div style="margin: 20px 0; padding-top: 20px; border-top: 1px solid #ddd;">
        <h3>Tạo lại bảng database</h3>
        <p class="description">
            Nếu bảng database bị lỗi, bạn có thể tạo lại. Lưu ý: Tất cả dữ liệu sẽ bị xóa!
        </p>
        <form method="post" action="" onsubmit="return confirm('Bạn có chắc chắn muốn tạo lại bảng database? Tất cả dữ liệu sẽ bị xóa!');">
            <?php wp_nonce_field('bank_notify_payment_codes_action'); ?>
            <input type="hidden" name="bank_notify_action" value="recreate_table">
            <button type="submit" class="button" style="background: #dc3232; color: #fff; border-color: #dc3232;">
                <span class="dashicons dashicons-database" style="margin-top: 3px;"></span>
                Tạo lại bảng
            </button>
        </form>
    </div>
</div>

<!-- Instructions -->
<div class="bank-notify-instructions" style="margin: 30px 0; padding: 20px; background: #f0f6fc; border-left: 4px solid #2271b1;">
    <h3>Hướng dẫn sử dụng</h3>

    <h4>Cách hoạt động:</h4>
    <ol>
        <li><strong>Import mã:</strong> Nhập danh sách mã vào textarea hoặc tạo tự động từ mẫu.</li>
        <li><strong>Gán mã:</strong> Khi khách hàng đặt hàng, hệ thống tự động gán một mã khả dụng.</li>
        <li><strong>Đánh dấu đã dùng:</strong> Khi thanh toán thành công, mã được đánh dấu "Đã dùng".</li>
        <li><strong>Giải phóng mã:</strong> Nếu đơn hàng bị hủy, mã tự động được giải phóng.</li>
    </ol>

    <h4>Lưu ý quan trọng:</h4>
    <ul>
        <li>Mỗi mã phải là duy nhất (unique).</li>
        <li>Nên import hàng ngàn mã để đảm bảo luôn có mã khả dụng.</li>
        <li>Nếu hết mã khả dụng, hệ thống sẽ tự động chuyển về chế độ "Tiền tố + Mã đơn hàng".</li>
        <li>Mã có thể chứa chữ cái, số, khoảng trắng và các ký tự đặc biệt (trừ xuống dòng).</li>
    </ul>
</div>
