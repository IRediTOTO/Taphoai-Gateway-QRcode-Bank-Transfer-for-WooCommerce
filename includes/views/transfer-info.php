<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="woocommerce-sepay-bank-details">
    <div class="sepay-box">
        <div class="box-title">
            Thanh toán qua chuyển khoản ngân hàng
        </div>
        <div class="sepay-message"></div>
        <div class="sepay-pay-info">
            <div class="qr-box">
                <div class="qr-title">
                    Cách 1: Mở app ngân hàng/ Ví và <b>quét mã QR</b>
                </div>
                <div class="qr-zone">
                    <div class="qr-element">
                        <div class="qr-top-border"></div>
                        <div class="qr-bottom-border"></div>
                        <div class="qr-content">
                            <img decoding="async" class="qr-image" src="<?php echo esc_url($qr_code_url) ?>"/>
                        </div>
                    </div>
                    <div class="download-qr">
                        <a class="button-qr" href="<?php echo esc_url(add_query_arg('download', 'yes', $qr_code_url)) ?>" download="">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" x2="12" y1="15" y2="3"/>
                            </svg>
                            <span>Tải ảnh QR</span>
                        </a>
                    </div>
                </div>
                <div style="margin-top: -1rem;"></div>
            </div>
            <div class="manual-box">
                <div class="manual-title">
                    Cách 2: Chuyển khoản <b>thủ công</b> theo thông tin
                </div>
                <div class="bank-info">
                    <div class="banner">
                        <img decoding="async" class="bank-logo" src="<?php echo esc_url($bank_logo_url) ?>"/>
                    </div>
                    <div class="bank-info-table">
                        <div class="bank-info-row-group">
                            <div class="bank-info-row">
                                <div class="bank-info-cell">Ngân hàng</div>
                                <div class="bank-info-cell font-bold">
                                    <?php echo esc_html($displayed_bank_name) ?>
                                </div>
                            </div>
                            <?php if (!empty($account_holder_name)) : ?>
                                <div class="bank-info-row">
                                    <div class="bank-info-cell">Thụ hưởng</div>
                                    <div class="bank-info-cell">
                                        <span class="font-bold" id="copy_accholder">
                                            <?php echo esc_html($account_holder_name) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="bank-info-row">
                                <div class="bank-info-cell">Số tài khoản</div>
                                <div class="bank-info-cell">
                                    <div class="bank-info-value">
                                        <span class="font-bold" id="copy_accno">
                                            <?php echo esc_html($account_number) ?>
                                        </span>
                                        <span id="sepay_copy_account_number">
                                            <a id="sepay_copy_account_number_btn" href="#">
                                                <svg width="15" height="15" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.625 3.125C6.34886 3.125 6.125 3.34886 6.125 3.625V4.875H13.375C14.3415 4.875 15.125 5.6585 15.125 6.625V13.875H16.375C16.6511 13.875 16.875 13.6511 16.875 13.375V3.625C16.875 3.34886 16.6511 3.125 16.375 3.125H6.625ZM15.125 15.125H16.375C17.3415 15.125 18.125 14.3415 18.125 13.375V3.625C18.125 2.6585 17.3415 1.875 16.375 1.875H6.625C5.6585 1.875 4.875 2.6585 4.875 3.625V4.875H3.625C2.6585 4.875 1.875 5.6585 1.875 6.625V16.375C1.875 17.3415 2.6585 18.125 3.625 18.125H13.375C14.3415 18.125 15.125 17.3415 15.125 16.375V15.125ZM13.875 6.625C13.875 6.34886 13.6511 6.125 13.375 6.125H3.625C3.34886 6.125 3.125 6.34886 3.125 6.625V16.375C3.125 16.6511 3.34886 16.875 3.625 16.875H13.375C13.6511 16.875 13.875 16.6511 13.875 16.375V6.625Z" fill="rgba(51, 102, 255, 1)"></path>
                                                </svg>
                                            </a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="bank-info-row">
                                <div class="bank-info-cell">Số tiền</div>
                                <div class="bank-info-cell">
                                    <div class="bank-info-value">
                                        <span class="font-bold" id="copy_amount">
                                            <?php echo wp_kses_post(wc_price($order->get_total())) ?>
                                        </span>
                                        <span id="sepay_copy_amount">
                                            <a id="sepay_copy_amount_btn" href="#">
                                                <svg width="15" height="15" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.625 3.125C6.34886 3.125 6.125 3.34886 6.125 3.625V4.875H13.375C14.3415 4.875 15.125 5.6585 15.125 6.625V13.875H16.375C16.6511 13.875 16.875 13.6511 16.875 13.375V3.625C16.875 3.34886 16.6511 3.125 16.375 3.125H6.625ZM15.125 15.125H16.375C17.3415 15.125 18.125 14.3415 18.125 13.375V3.625C18.125 2.6585 17.3415 1.875 16.375 1.875H6.625C5.6585 1.875 4.875 2.6585 4.875 3.625V4.875H3.625C2.6585 4.875 1.875 5.6585 1.875 6.625V16.375C1.875 17.3415 2.6585 18.125 3.625 18.125H13.375C14.3415 18.125 15.125 17.3415 15.125 16.375V15.125ZM13.875 6.625C13.875 6.34886 13.6511 6.125 13.375 6.125H3.625C3.34886 6.125 3.125 6.34886 3.125 6.625V16.375C3.125 16.6511 3.34886 16.875 3.625 16.875H13.375C13.6511 16.875 13.875 16.6511 13.875 16.375V6.625Z" fill="rgba(51, 102, 255, 1)"></path>
                                                </svg>
                                            </a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="bank-info-row">
                                <div class="bank-info-cell">Nội dung CK</div>
                                <div class="bank-info-cell">
                                    <div class="bank-info-value">
                                        <span id="copy_memo" class="font-bold">
                                            <?php echo esc_html($remark) ?>
                                        </span>
                                        <span id="sepay_copy_transfer_content">
                                            <a id="sepay_copy_transfer_content_btn" href="#">
                                                <svg width="15" height="15" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.625 3.125C6.34886 3.125 6.125 3.34886 6.125 3.625V4.875H13.375C14.3415 4.875 15.125 5.6585 15.125 6.625V13.875H16.375C16.6511 13.875 16.875 13.6511 16.875 13.375V3.625C16.875 3.34886 16.6511 3.125 16.375 3.125H6.625ZM15.125 15.125H16.375C17.3415 15.125 18.125 14.3415 18.125 13.375V3.625C18.125 2.6585 17.3415 1.875 16.375 1.875H6.625C5.6585 1.875 4.875 2.6585 4.875 3.625V4.875H3.625C2.6585 4.875 1.875 5.6585 1.875 6.625V16.375C1.875 17.3415 2.6585 18.125 3.625 18.125H13.375C14.3415 18.125 15.125 17.3415 15.125 16.375V15.125ZM13.875 6.625C13.875 6.34886 13.6511 6.125 13.375 6.125H3.625C3.34886 6.125 3.125 6.34886 3.125 6.625V16.375C3.125 16.6511 3.34886 16.875 3.625 16.875H13.375C13.6511 16.875 13.875 16.6511 13.875 16.375V6.625Z" fill="rgba(51, 102, 255, 1)"></path>
                                                </svg>
                                            </a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="note">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd"/>
                        </svg>
                        <span>Lưu ý: Vui lòng giữ nguyên nội dung chuyển khoản <b><?php echo esc_html($remark) ?></b> để xác nhận thanh toán tự động.</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="sepay-pay-footer">
            <div class="sepay-status-wrapper">
                <span class="sepay-status-text">Trạng thái: Chờ thanh toán</span>
                <img decoding="async" src="<?php echo esc_url(plugin_dir_url(__DIR__) . '../') . 'assets/images/loading.gif' ?>"/>
            </div>
            <?php if ($expiry_enabled && $expiry_time): ?>
            <div class="sepay-countdown-box">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="countdown-icon">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                <span class="countdown-label">Thời gian còn lại:</span>
                <span class="sepay-countdown-timer" data-expiry="<?php echo esc_attr($expiry_time); ?>">
                    <span class="countdown-time">--:--:--</span>
                </span>
            </div>
            <?php endif; ?>
        </div>
        <div class="sepay-download" style="display: none;">
            <?php if ($this->get_option('download_mode') === 'auto'): ?>
                <div class="autodownload">
                    <p class="countdown">Hệ thống sẽ tự động tải xuống sau vài giây nữa...</p>
                    <p class="subtle">Nếu tiến trình vẫn chưa tải xuống, vui lòng nhấp <span class="force-download">vào đây</span>.</p>
                </div>
            <?php endif ?>
            <?php if ($this->get_option('download_mode') === 'manual'): ?>
                <div class="download-list"></div>
            <?php endif ?>
        </div>
    </div>
</section>
