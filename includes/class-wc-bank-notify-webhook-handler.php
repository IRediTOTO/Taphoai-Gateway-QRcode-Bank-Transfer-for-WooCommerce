<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xử lý webhook thanh toán từ ứng dụng điện thoại
 */
class Taphoai_BankNotify_Webhook_Handler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Đăng ký REST API routes
     */
    public function register_routes()
    {
        register_rest_route('bank-notify-gateway/v1', '/new-payment', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_payment_notification'],
            'permission_callback' => [$this, 'verify_api_key'],
        ]);
    }

    /**
     * Xác thực API key từ Authorization header.
     */
    public function verify_api_key($request)
    {
        $provided_key = null;

        // Thử lấy từ Authorization header
        $auth_header = $request->get_header('Authorization');

        if (!empty($auth_header)) {
            // Kiểm tra Bearer token format: "Bearer <api_key>"
            if (preg_match('/^Bearer\s+(.+)$/i', $auth_header, $matches)) {
                $provided_key = trim($matches[1]);
            }
        }

        // Nếu không tìm thấy API key ở bất kỳ đâu
        if (empty($provided_key)) {
            Taphoai_BankNotify_Logger::warning('Webhook authentication failed: No API key provided');
            return new WP_Error('no_auth', 'Missing API key. Provide via Authorization: Bearer <api_key>.', ['status' => 401]);
        }

        // Lấy API key từ settings
        $gateway = new Taphoai_Gateway_BankNotify();
        $stored_key = $gateway->get_option('webhook_api_key');

        if (empty($stored_key)) {
            Taphoai_BankNotify_Logger::error('Webhook authentication failed: API key not configured');
            return new WP_Error('api_key_not_configured', 'API key not configured', ['status' => 500]);
        }

        // So sánh API key
        if (!hash_equals((string) $stored_key, (string) $provided_key)) {
            Taphoai_BankNotify_Logger::warning('Webhook authentication failed: Invalid API key provided');
            return new WP_Error('invalid_api_key', 'Invalid API key', ['status' => 403]);
        }

        Taphoai_BankNotify_Logger::debug('Webhook authentication successful');
        return true;
    }

    /**
     * Xử lý thông báo thanh toán
     */
    public function handle_payment_notification($request)
    {
        // Lấy body text
        $body = $request->get_body();

        Taphoai_BankNotify_Logger::info('Webhook received', [
            'body_length' => strlen($body),
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown',
        ]);

        if (empty($body)) {
            Taphoai_BankNotify_Logger::warning('Webhook rejected: Empty request body');
            return new WP_Error('empty_body', 'Empty request body', ['status' => 400]);
        }

        Taphoai_BankNotify_Logger::debug('Webhook body content', ['body' => $body]);

        // Sử dụng Parser Factory để tự động detect ngân hàng và parse
        $parser = Taphoai_BankNotify_Parser_Factory::create($body);
        $parsed_data = $parser->parse();

        Taphoai_BankNotify_Logger::info('Webhook parsed by bank parser', [
            'bank_name' => $parsed_data['bank_name'],
            'bank_code' => $parsed_data['bank_code'],
        ]);

        // Trích xuất payment code
        $payment_code = $parsed_data['payment_code'];

        if (!$payment_code) {
            Taphoai_BankNotify_Logger::warning('Webhook rejected: Could not extract payment code', [
                'body' => $body,
                'bank' => $parsed_data['bank_name'],
            ]);
            return new WP_Error('no_payment_code', 'Could not extract payment code from body', ['status' => 400]);
        }

        Taphoai_BankNotify_Logger::info('Payment code extracted from webhook', [
            'payment_code' => $payment_code,
            'bank' => $parsed_data['bank_name'],
        ]);

        // Tìm order theo payment code
        $order = $this->find_order_by_payment_code($payment_code);

        if (!$order) {
            Taphoai_BankNotify_Logger::warning('Webhook rejected: Order not found', ['payment_code' => $payment_code]);
            return new WP_Error('order_not_found', 'Order not found for payment code: ' . $payment_code, ['status' => 404]);
        }

        Taphoai_BankNotify_Logger::info('Order found for payment code', [
            'order_id' => $order->get_id(),
            'payment_code' => $payment_code,
            'order_status' => $order->get_status(),
        ]);

        // Trích xuất số tiền từ body
        $amount = $parsed_data['amount'];

        if (!$amount) {
            Taphoai_BankNotify_Logger::warning('Webhook rejected: Could not extract amount', [
                'order_id' => $order->get_id(),
                'body' => $body,
                'bank' => $parsed_data['bank_name'],
            ]);
            return new WP_Error('no_amount', 'Could not extract amount from body', ['status' => 400]);
        }

        Taphoai_BankNotify_Logger::info('Amount extracted from webhook', [
            'order_id' => $order->get_id(),
            'amount' => $amount,
            'order_total' => $order->get_total(),
            'bank' => $parsed_data['bank_name'],
        ]);

        // Nếu đơn không còn chờ thanh toán, ghi nhận khoản chuyển phát sinh thêm.
        if (!$order->has_status('on-hold')) {
            Taphoai_BankNotify_Logger::info('Webhook received for already processed order', [
                'order_id' => $order->get_id(),
                'current_status' => $order->get_status(),
                'amount' => $amount,
            ]);

            $order->add_order_note(
                $this->build_extra_payment_note($order, $body, $amount)
            );
            $order->update_meta_data('_bank_notify_extra_payment_received_at', current_time('mysql'));
            $order->update_meta_data('_bank_notify_extra_payment_amount', $amount);
            $order->save();
            $this->mark_order_payment_code_used($order);

            return new WP_REST_Response([
                'success' => false,
                'message' => 'Order already processed. Extra payment noted.',
                'order_id' => $order->get_id(),
                'current_status' => $order->get_status(),
                'amount' => $amount,
            ], 200);
        }

        // Kiểm tra số tiền thanh toán.
        // Chuyển thừa vẫn được xác nhận, chuyển thiếu thì ghi note và chờ admin xử lý.
        $order_total = floatval($order->get_total());
        $tolerance = 0.1; // Cho phép sai số 0.1 VND

        if ($amount + $tolerance < $order_total) {
            $short_amount = $order_total - $amount;

            Taphoai_BankNotify_Logger::error('Webhook rejected: Underpaid amount', [
                'order_id' => $order->get_id(),
                'received_amount' => $amount,
                'expected_amount' => $order_total,
                'short_amount' => $short_amount,
            ]);

            $order->add_order_note(
                sprintf(
                    'Webhook nhận được thanh toán thiếu. Số tiền nhận: %s VND, số tiền đơn hàng: %s VND, còn thiếu: %s VND.',
                    number_format($amount, 0, ',', '.'),
                    number_format($order_total, 0, ',', '.'),
                    number_format($short_amount, 0, ',', '.')
                )
            );
            $order->update_meta_data('_bank_notify_underpaid_amount', $short_amount);
            $order->save();

            return new WP_Error('amount_underpaid', sprintf(
                'Amount underpaid. Received: %s, Expected: %s, Short: %s',
                number_format($amount, 0, ',', '.'),
                number_format($order_total, 0, ',', '.'),
                number_format($short_amount, 0, ',', '.')
            ), ['status' => 400]);
        }

        if ($amount - $order_total > $tolerance) {
            $overpaid_amount = $amount - $order_total;

            Taphoai_BankNotify_Logger::warning('Webhook accepted: Overpaid amount', [
                'order_id' => $order->get_id(),
                'received_amount' => $amount,
                'expected_amount' => $order_total,
                'overpaid_amount' => $overpaid_amount,
            ]);

            $order->add_order_note(
                sprintf(
                    'Webhook nhận được thanh toán thừa. Số tiền nhận: %s VND, số tiền đơn hàng: %s VND, chuyển thừa: %s VND.',
                    number_format($amount, 0, ',', '.'),
                    number_format($order_total, 0, ',', '.'),
                    number_format($overpaid_amount, 0, ',', '.')
                )
            );
            $order->update_meta_data('_bank_notify_overpaid_amount', $overpaid_amount);
            $order->save();
        }

        // Trích xuất số tài khoản từ body
        $account_number = $parsed_data['account_number'];

        if ($account_number) {
            Taphoai_BankNotify_Logger::debug('Account number extracted from webhook', [
                'order_id' => $order->get_id(),
                'account_number' => $account_number,
                'bank' => $parsed_data['bank_name'],
            ]);
            // Kiểm tra số tài khoản có khớp với settings không
            $gateway = new Taphoai_Gateway_BankNotify();
            $configured_account = $gateway->get_option('bank_account_number');

            // Loại bỏ khoảng trắng và ký tự đặc biệt để so sánh
            $account_number_clean = preg_replace('/[^0-9]/', '', $account_number);
            $configured_account_clean = preg_replace('/[^0-9]/', '', $configured_account);

            // Chỉ so sánh 4 số cuối (vì số tài khoản có thể bị ẩn một phần)
            $account_last_4 = substr($account_number_clean, -4);
            $configured_last_4 = substr($configured_account_clean, -4);

            if ($account_last_4 !== $configured_last_4) {
                Taphoai_BankNotify_Logger::error('Webhook rejected: Account number mismatch', [
                    'order_id' => $order->get_id(),
                    'received_last_4' => $account_last_4,
                    'expected_last_4' => $configured_last_4,
                ]);

                $order->add_order_note(
                    sprintf(
                        'Webhook nhận được số tài khoản không khớp. 4 số cuối nhận: %s, 4 số cuối cấu hình: %s',
                        $account_last_4,
                        $configured_last_4
                    )
                );

                return new WP_Error('account_mismatch', sprintf(
                    'Account number mismatch. Last 4 digits received: %s, Expected: %s',
                    $account_last_4,
                    $configured_last_4
                ), ['status' => 400]);
            }

            Taphoai_BankNotify_Logger::debug('Account number verification passed', [
                'order_id' => $order->get_id(),
            ]);
        }

        // Cập nhật trạng thái order
        $result = $this->update_order_status($order, $body, $amount);

        if ($result) {
            Taphoai_BankNotify_Logger::info('Order updated successfully via webhook', [
                'order_id' => $order->get_id(),
                'new_status' => $order->get_status(),
                'amount' => $amount,
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Order updated successfully',
                'order_id' => $order->get_id(),
                'new_status' => $order->get_status(),
                'amount' => $amount,
            ], 200);
        } else {
            Taphoai_BankNotify_Logger::error('Failed to update order via webhook', [
                'order_id' => $order->get_id(),
            ]);
            return new WP_Error('update_failed', 'Failed to update order', ['status' => 500]);
        }
    }

    /**
     * Tìm order theo payment code
     */
    private function find_order_by_payment_code($payment_code)
    {
        $payment_code = trim((string) $payment_code);
        $code_candidates = array_filter(array_unique([
            $payment_code,
            preg_match('/^SEVQR\s+/i', $payment_code) ? preg_replace('/^SEVQR\s+/i', '', $payment_code) : null,
            preg_match('/^SEVQR\s+/i', $payment_code) ? null : 'SEVQR ' . $payment_code,
        ]));

        foreach (['_bank_notify_payment_code', '_bank_notify_transfer_content'] as $meta_key) {
            foreach ($code_candidates as $candidate) {
                $orders = wc_get_orders([
                    'limit' => 1,
                    'meta_key' => $meta_key,
                    'meta_value' => $candidate,
                    'return' => 'ids',
                ]);

                if (!empty($orders)) {
                    return wc_get_order($orders[0]);
                }
            }
        }

        $order = $this->find_order_by_contained_natural_payment_code($payment_code);
        if ($order) {
            return $order;
        }

        // Nếu không tìm thấy, thử tìm theo pattern "tiền tố + order ID"
        // Trường hợp này xảy ra khi hết mã thanh toán và fallback về prefix mode
        $gateway = new Taphoai_Gateway_BankNotify();
        $prefix = $gateway->get_option('pay_code_prefix', 'DH');

        // Kiểm tra xem payment_code có bắt đầu bằng prefix không
        if (strpos($payment_code, $prefix) === 0) {
            // Trích xuất order ID từ payment code
            $order_id = substr($payment_code, strlen($prefix));

            // Kiểm tra xem order_id có phải là số không
            if (is_numeric($order_id)) {
                $order = wc_get_order($order_id);

                // Kiểm tra order tồn tại và sử dụng payment gateway này
                if ($order && $order->get_payment_method() === 'bank_notify') {
                    Taphoai_BankNotify_Logger::info('Order found by prefix pattern fallback', [
                        'payment_code' => $payment_code,
                        'order_id' => $order_id,
                        'prefix' => $prefix,
                    ]);

                    return $order;
                }
            }
        }

        Taphoai_BankNotify_Logger::debug('Order not found for payment code', [
            'payment_code' => $payment_code,
            'tried_prefix' => $prefix,
        ]);

        return null;
    }

    /**
     * Tìm đơn natural mode khi ngân hàng thêm metadata sau nội dung chuyển khoản.
     */
    private function find_order_by_contained_natural_payment_code($payment_code)
    {
        $orders = wc_get_orders([
            'limit' => -1,
            'status' => ['on-hold'],
            'payment_method' => 'bank_notify',
            'meta_key' => '_bank_notify_payment_mode',
            'meta_value' => 'natural',
            'return' => 'objects',
        ]);

        $matches = [];

        foreach ($orders as $order) {
            $code = trim((string) $order->get_meta('_bank_notify_payment_code'));
            if (
                $code === ''
                || $this->get_payment_code_length($code) < Taphoai_BankNotify_Payment_Code_Manager::MIN_CODE_LENGTH
                || stripos($payment_code, $code) === false
            ) {
                continue;
            }

            $matches[] = [
                'order' => $order,
                'code' => $code,
                'length' => strlen($code),
            ];
        }

        if (empty($matches)) {
            return null;
        }

        usort($matches, function ($a, $b) {
            return $b['length'] <=> $a['length'];
        });

        Taphoai_BankNotify_Logger::info('Order found by contained natural payment code', [
            'payment_code' => $payment_code,
            'matched_code' => $matches[0]['code'],
            'order_id' => $matches[0]['order']->get_id(),
        ]);

        return $matches[0]['order'];
    }

    private function get_payment_code_length($code)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($code, 'UTF-8');
        }

        return strlen($code);
    }

    /**
     * Cập nhật trạng thái order
     */
    private function update_order_status($order, $webhook_body, $amount = null)
    {
        // Lấy trạng thái mong muốn từ settings
        $gateway = new Taphoai_Gateway_BankNotify();
        $target_status = $gateway->get_option('order_when_completed', 'processing');

        Taphoai_BankNotify_Logger::info('Updating order status', [
            'order_id' => $order->get_id(),
            'current_status' => $order->get_status(),
            'target_status' => $target_status,
            'amount' => $amount,
        ]);

        // Thêm note vào order
        $note_parts = [
            sprintf('Thanh toán đã được xác nhận qua webhook.')
        ];

        if ($amount) {
            $note_parts[] = sprintf('Số tiền: %s VND', number_format($amount, 0, ',', '.'));
        }

        if (Taphoai_BankNotify_Logger::is_enabled()) {
            $note_parts[] = sprintf('Nội dung: %s', sanitize_textarea_field(substr($webhook_body, 0, 200)));
        }

        $order->add_order_note(implode(' | ', $note_parts));

        // Cập nhật trạng thái
        $order->update_status($target_status, 'Thanh toán đã được xác nhận tự động qua webhook.');

        // Lưu thời gian webhook và số tiền
        $order->update_meta_data('_bank_notify_webhook_received_at', current_time('mysql'));
        if ($amount) {
            $order->update_meta_data('_bank_notify_webhook_amount', $amount);
        }
        $order->save();
        $this->mark_order_payment_code_used($order);

        Taphoai_BankNotify_Logger::info('Order status updated successfully', [
            'order_id' => $order->get_id(),
            'new_status' => $order->get_status(),
        ]);

        return true;
    }

    /**
     * Build order note for payments received after the order has already moved past on-hold.
     */
    private function build_extra_payment_note($order, $webhook_body, $amount)
    {
        $note_parts = [
            sprintf(
                'Webhook nhận được giao dịch chuyển khoản thêm cho đơn đã xử lý. Trạng thái hiện tại: %s.',
                $order->get_status()
            ),
            sprintf('Số tiền nhận thêm: %s VND.', number_format($amount, 0, ',', '.')),
        ];

        if (Taphoai_BankNotify_Logger::is_enabled()) {
            $note_parts[] = sprintf('Nội dung: %s', sanitize_textarea_field(substr($webhook_body, 0, 200)));
        }

        return implode(' ', $note_parts);
    }

    /**
     * Mark natural pool payment code as used once the server has accepted payment.
     */
    private function mark_order_payment_code_used($order)
    {
        $code = $order->get_meta('_bank_notify_payment_code');
        $mode = $order->get_meta('_bank_notify_payment_mode');

        if ($mode !== 'natural' || empty($code)) {
            return;
        }

        $manager = new Taphoai_BankNotify_Payment_Code_Manager();
        if ($manager->mark_code_as_used($code)) {
            $order->update_meta_data('_bank_notify_code_used_at', current_time('mysql'));
            $order->save();
        }
    }
}

if (!class_exists('WC_BankNotify_Webhook_Handler', false)) {
    class_alias('Taphoai_BankNotify_Webhook_Handler', 'WC_BankNotify_Webhook_Handler');
}
