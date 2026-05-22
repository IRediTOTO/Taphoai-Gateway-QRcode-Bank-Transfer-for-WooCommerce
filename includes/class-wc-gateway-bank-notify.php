<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_BankNotify extends WC_Payment_Gateway
{
    public $displayed_bank_name;

    public $bank_name_display_type;

    public $bank_accounts = [];

    public $bank_bin;

    public $bank_logo_url;

    public function __construct()
    {
        $this->id = 'bank_notify';
        $this->has_fields = false;
        $this->method_title = 'Chuyển khoản ngân hàng';
        $this->method_description = 'Thanh toán qua chuyển khoản ngân hàng với QR Code (VietQR).';
        $this->supports = ['products'];

        if (is_admin()) {
            $this->init_form_fields();
        }

        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->bank_name_display_type = $this->get_option('show_bank_name');

        $bank_data = $this->get_bank_data();
        $selected_bank = $this->get_option('bank_select');

        $this->bank_bin = array_key_exists($selected_bank, $bank_data) ? $bank_data[$selected_bank]['bin'] : null;
        $this->bank_logo_url = array_key_exists($selected_bank, $bank_data)
            ? $this->get_bank_logo_url($selected_bank)
            : null;

        $bank_brand_name = array_key_exists($selected_bank, $bank_data) ? $bank_data[$selected_bank]['short_name'] : null;
        if ($this->bank_name_display_type === 'brand_name') {
            $this->displayed_bank_name = $bank_brand_name;
        } elseif ($this->bank_name_display_type === 'full_name' && array_key_exists($selected_bank, $bank_data)) {
            $this->displayed_bank_name = $bank_data[$selected_bank]['full_name'];
        } elseif ($this->bank_name_display_type === 'full_include_brand' && array_key_exists($selected_bank, $bank_data)) {
            $this->displayed_bank_name = $bank_data[$selected_bank]['full_name'] . ' (' . $bank_data[$selected_bank]['short_name'] . ')';
        } else {
            $this->displayed_bank_name = $bank_brand_name;
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
        add_action('woocommerce_admin_field_bank_select_with_logo', [$this, 'generate_bank_select_with_logo_html']);
        add_action('woocommerce_admin_field_webhook_url_display', [$this, 'generate_webhook_url_display_html']);
        add_action('woocommerce_admin_field_logo_upload', [$this, 'generate_logo_upload_html']);
    }

    public function init_form_fields()
    {
        $form_fields = [
            'display_section' => [
                'title' => 'Cài đặt hiển thị',
                'type' => 'title',
                'description' => 'Quản lý nội dung hiển thị ở checkout, mã thanh toán, trạng thái đơn hàng và trải nghiệm sau thanh toán.',
            ],
            'enabled' => [
                'title' => 'Bật/Tắt',
                'type' => 'checkbox',
                'label' => 'Bật Chuyển khoản ngân hàng',
                'default' => 'no',
            ],
            'title' => [
                'title' => 'Tiêu đề',
                'type' => 'text',
                'description' => 'Tiêu đề hiển thị cho khách hàng khi thanh toán.',
                'default' => 'Chuyển khoản ngân hàng (Quét mã QR)',
                'desc_tip' => true,
            ],
            'description' => [
                'title' => 'Mô tả',
                'type' => 'textarea',
                'description' => 'Mô tả hiển thị cho khách hàng khi thanh toán.',
                'default' => 'Thanh toán chuyển khoản qua mã QR (VietQR).',
                'desc_tip' => true,
            ],
            'payment_code_mode' => [
                'title' => 'Chế độ mã thanh toán',
                'type' => 'select',
                'desc_tip' => true,
                'description' => 'Chọn cách tạo mã thanh toán cho đơn hàng.',
                'options' => [
                    'prefix' => 'Tiền tố + Mã đơn hàng (VD: DH123)',
                    'natural' => 'Chuỗi tự nhiên (từ pool mã)',
                ],
                'default' => 'prefix',
                'class' => 'payment-code-mode-field',
            ],
            'pay_code_prefix' => [
                'title' => 'Tiền tố mã thanh toán',
                'type' => 'text',
                'default' => 'DH',
                'desc_tip' => true,
                'description' => 'Tiền tố mã thanh toán (chỉ dùng khi chế độ là "Tiền tố + Mã đơn hàng").',
                'class' => 'payment-code-prefix-field',
                'custom_attributes' => [
                    'required' => 'required',
                ],
            ],
            'payment_code_expiry_enabled' => [
                'title' => 'Bật thời hạn hết hạn mã',
                'type' => 'checkbox',
                'label' => 'Hiển thị bộ đếm ngược hết hạn mã ở trang thanh toán',
                'default' => 'no',
                'desc_tip' => true,
                'description' => 'Khi bật, hiển thị bộ đếm ngược trong trang thanh toán. Với chế độ "Chuỗi tự nhiên", thời gian hết hạn bên dưới vẫn bắt buộc để hệ thống giải phóng mã đã cấp.',
            ],
            'payment_code_expiry_value' => [
                'title' => 'Thời gian hết hạn',
                'type' => 'number',
                'default' => '24',
                'desc_tip' => true,
                'description' => 'Số lượng thời gian (kết hợp với đơn vị bên dưới).',
                'class' => 'payment-code-expiry-field payment-code-expiry-value-field',
                'custom_attributes' => [
                    'min' => '1',
                    'step' => '1',
                ],
            ],
            'payment_code_expiry_unit' => [
                'title' => 'Đơn vị thời gian',
                'type' => 'select',
                'default' => 'hours',
                'desc_tip' => true,
                'description' => 'Đơn vị thời gian cho thời hạn hết hạn.',
                'class' => 'payment-code-expiry-field payment-code-expiry-unit-field',
                'options' => [
                    'minutes' => 'Phút',
                    'hours' => 'Giờ',
                    'days' => 'Ngày',
                    'weeks' => 'Tuần',
                    'months' => 'Tháng',
                ],
            ],
            'success_message' => [
                'title' => 'Thông điệp thanh toán thành công',
                'type' => 'textarea',
                'desc_tip' => true,
                'description' => 'Nội dung hiển thị sau khi khách hàng thanh toán thành công. Hỗ trợ HTML.',
                'default' => '<h2 class="text-success">Thanh toán thành công</h2>',
            ],
            'order_when_completed' => [
                'title' => 'Trạng thái đơn hàng sau thanh toán',
                'type' => 'select',
                'desc_tip' => true,
                'description' => 'Trạng thái đơn hàng sau khi thanh toán thành công.',
                'options' => $this->getWcOrderStatuses(),
                'default' => 'processing',
            ],
            'download_mode' => [
                'title' => 'Chế độ tải xuống sau khi thanh toán',
                'type' => 'select',
                'desc_tip' => true,
                'description' => 'Dành cho các sản phẩm có thể tải xuống',
                'options' => [
                    'auto' => 'Tự động',
                    'manual' => 'Thủ công',
                ],
                'default' => 'manual',
            ],
            'show_bank_name' => [
                'title' => 'Hiển thị tên ngân hàng',
                'type' => 'select',
                'desc_tip' => true,
                'description' => 'Thông tin hiển thị tên ngân hàng tại ô thanh toán.',
                'options' => [
                    'brand_name' => 'Tên viết tắt',
                    'full_name' => 'Tên đầy đủ',
                    'full_include_brand' => 'Tên đầy đủ kèm tên viết tắt',
                ],
                'default' => 'brand_name',
            ],
            'logo' => [
                'title' => 'Logo',
                'type' => 'logo_upload',
                'description' => 'Logo hiển thị trên phương thức thanh toán ở trang thanh toán.',
                'default' => set_url_scheme(plugins_url('assets/images/logo.png', __DIR__)),
            ],
            'bank_info_section' => [
                'title' => 'Cài đặt tài khoản ngân hàng',
                'type' => 'title',
                'description' => 'Thiết lập ngân hàng nhận tiền, thông tin tài khoản và webhook xác nhận thanh toán tự động.',
            ],
            'bank_select' => [
                'title' => 'Ngân hàng',
                'type' => 'bank_select_with_logo',
                'css' => 'min-width: 350px;',
                'desc_tip' => true,
                'description' => 'Chọn đúng ngân hàng nhận thanh toán của bạn.',
                'custom_attributes' => [
                    'required' => 'required',
                ],
            ],
            'bank_account_number' => [
                'title' => 'Số tài khoản',
                'type' => 'text',
                'desc_tip' => true,
                'description' => 'Điền đúng số tài khoản ngân hàng.',
                'custom_attributes' => [
                    'required' => 'required',
                ],
            ],
            'bank_account_holder' => [
                'title' => 'Chủ tài khoản',
                'type' => 'text',
                'desc_tip' => true,
                'description' => 'Điền đúng tên chủ tài khoản.',
            ],
            'webhook_api_key' => [
                'title' => 'API Key',
                'type' => 'text',
                'desc_tip' => true,
                'description' => 'API key để xác thực webhook. Gửi key bằng header Authorization: Bearer <api_key>.',
                'default' => $this->generate_api_key(),
                'custom_attributes' => [
                    'required' => 'required',
                ],
            ],
            'webhook_url_info' => [
                'title' => 'Webhook URL',
                'type' => 'webhook_url_display',
                'description' => 'URL endpoint để nhận thông báo thanh toán.',
            ],
            'debug_section' => [
                'title' => 'Debug & Logging',
                'type' => 'title',
                'description' => 'Bật ghi log cục bộ trong WooCommerce khi cần hỗ trợ kỹ thuật. Log có thể chứa thông tin giao dịch nên chỉ bật khi cần.',
            ],
            'debug_log_enabled' => [
                'title' => 'Bật Debug Log',
                'type' => 'checkbox',
                'label' => 'Ghi log các hoạt động của plugin',
                'default' => 'no',
                'desc_tip' => true,
                'description' => 'Khi bật, plugin sẽ ghi log chi tiết vào WooCommerce log system. Hữu ích cho việc debug và theo dõi.',
            ],
        ];

        $this->form_fields = $form_fields;
    }

    public function getWcOrderStatuses()
    {
        $statuses = wc_get_order_statuses();
        $result = [];

        foreach ($statuses as $key => $label) {
            $result[str_replace('wc-', '', $key)] = $label;
        }

        return $result;
    }

    public function admin_options()
    {
        $this->enqueue_admin_scripts();
        parent::admin_options();
    }

    public function process_admin_options()
    {
        check_admin_referer('woocommerce-settings');

        $payment_mode_key = $this->get_field_key('payment_code_mode');
        $payment_mode = isset($_POST[$payment_mode_key])
            ? wc_clean(sanitize_text_field(wp_unslash($_POST[$payment_mode_key])))
            : $this->get_option('payment_code_mode', 'prefix');

        if (!in_array($payment_mode, ['prefix', 'natural'], true)) {
            $payment_mode = 'prefix';
        }

        $prefix_key = $this->get_field_key('pay_code_prefix');
        $prefix = isset($_POST[$prefix_key]) ? wc_clean(sanitize_text_field(wp_unslash($_POST[$prefix_key]))) : '';

        if ($payment_mode === 'prefix' && $prefix === '') {
            WC_Admin_Settings::add_error('Tiền tố mã thanh toán không được để trống khi dùng chế độ Tiền tố + Mã đơn hàng.');
            return false;
        }

        if ($payment_mode === 'natural') {
            $expiry_value_key = $this->get_field_key('payment_code_expiry_value');
            $expiry_unit_key = $this->get_field_key('payment_code_expiry_unit');
            $expiry_value = isset($_POST[$expiry_value_key]) ? absint(wp_unslash($_POST[$expiry_value_key])) : 0;
            $expiry_unit = isset($_POST[$expiry_unit_key])
                ? wc_clean(sanitize_text_field(wp_unslash($_POST[$expiry_unit_key])))
                : '';

            if ($expiry_value < 1) {
                WC_Admin_Settings::add_error('Thời gian hết hạn không được để trống khi dùng chế độ Chuỗi tự nhiên.');
                return false;
            }

            if (!in_array($expiry_unit, ['minutes', 'hours', 'days', 'weeks', 'months'], true)) {
                WC_Admin_Settings::add_error('Đơn vị thời gian hết hạn không hợp lệ.');
                return false;
            }
        }

        $logo_key = $this->get_field_key('logo');
        if (!empty($_POST[$logo_key])) {
            $_POST[$logo_key] = esc_url_raw(set_url_scheme(wp_unslash($_POST[$logo_key])));
        }

        $required_fields = [
            'bank_select' => 'Ngân hàng',
            'bank_account_number' => 'Số tài khoản',
            'webhook_api_key' => 'API Key',
        ];

        foreach ($required_fields as $key => $label) {
            $field_key = $this->get_field_key($key);
            $value = isset($_POST[$field_key]) ? wc_clean(sanitize_text_field(wp_unslash($_POST[$field_key]))) : '';

            if ($value === '') {
                WC_Admin_Settings::add_error(sprintf('%s không được để trống.', $label));
                return false;
            }
        }

        return parent::process_admin_options();
    }

    public function enqueue_admin_scripts()
    {
        wp_enqueue_script('jquery');
        wp_enqueue_script('wc-enhanced-select');

        // Enqueue custom admin CSS
        $admin_style_path = plugin_dir_path(__DIR__) . 'assets/css/admin-settings.css';
        $admin_style_version = file_exists($admin_style_path) ? filemtime($admin_style_path) : '1.0.0';

        wp_enqueue_style(
            'bank-notify-admin-settings',
            set_url_scheme(plugins_url('assets/css/admin-settings.css', dirname(__FILE__))),
            [],
            $admin_style_version
        );

        $inline_script = "
        jQuery(document).ready(function($) {
            // Toggle payment code fields based on mode
            function togglePaymentCodeFields() {
                var mode = $('#woocommerce_bank_notify_payment_code_mode').val();
                var \$modeRow = $('.payment-code-mode-field').closest('tr');
                var \$prefixField = $('.payment-code-prefix-field');
                var \$prefixRow = \$prefixField.closest('tr');
                var \$expiryFields = $('.payment-code-expiry-field');

                // Remove existing notice if any
                \$modeRow.find('.payment-code-natural-notice').remove();

                if (mode === 'natural') {
                    // Hide prefix field
                    \$prefixField
                        .prop('required', false)
                        .removeAttr('required aria-required');
                    \$prefixRow.hide();
                    \$expiryFields
                        .prop('required', true)
                        .attr('required', 'required')
                        .attr('aria-required', 'true');

                    // Add link to payment codes management page
                    var manageUrl = '" . esc_url(admin_url('admin.php?page=bank-notify-payment-codes&tab=import')) . "';
                    var notice = '<p class=\"payment-code-natural-notice\" style=\"margin-top: 10px; padding: 10px; background: #e7f3ff; border-left: 4px solid #2271b1; border-radius: 4px;\">' +
                        '<strong>Chế độ chuỗi tự nhiên:</strong> Mã thanh toán sẽ được lấy từ pool mã đã import. ' +
                        '<a href=\"' + manageUrl + '\" style=\"font-weight: 600; text-decoration: none;\">Quản lý mã thanh toán</a>' +
                        '</p>';
                    \$modeRow.find('td.forminp').append(notice);
                } else {
                    // Show prefix field
                    \$prefixField
                        .prop('required', true)
                        .attr('required', 'required')
                        .attr('aria-required', 'true');
                    \$prefixRow.show();
                    \$expiryFields
                        .prop('required', false)
                        .removeAttr('required aria-required');
                }
            }

            // Run on page load
            togglePaymentCodeFields();

            // Run on mode change
            $('#woocommerce_bank_notify_payment_code_mode').on('change', function() {
                togglePaymentCodeFields();
            });

        });
        ";

        wp_add_inline_script('jquery', $inline_script);
    }

    public function generate_bank_select_with_logo_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
        ];

        $data = wp_parse_args($data, $defaults);
        $bank_data = $this->get_bank_data();

        ob_start();
?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo wp_kses_post($this->get_tooltip_html($data)); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <select class="select bank-notify-bank-select wc-enhanced-select <?php echo esc_attr($data['class']); ?>"
                        name="<?php echo esc_attr($field_key); ?>"
                        id="<?php echo esc_attr($field_key); ?>"
                        style="<?php echo esc_attr($data['css']); ?>"
                        data-placeholder="<?php echo esc_attr__('Chọn hoặc tìm ngân hàng', 'taphoai-gateway-qrcode-bank-transfer-for-woocommerce'); ?>"
                        data-allow_clear="true"
                        <?php disabled($data['disabled'], true); ?>
                        <?php echo wp_kses_post($this->get_custom_attribute_html($data)); ?>>
                        <option value="">-- Chọn ngân hàng --</option>
                        <?php foreach ($bank_data as $bank_key => $bank_info) : ?>
                            <option value="<?php echo esc_attr($bank_key); ?>"
                                <?php selected($this->get_option($key), $bank_key); ?>>
                                <?php echo esc_html($bank_info['short_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php echo wp_kses_post($this->get_description_html($data)); ?>
                </fieldset>
            </td>
        </tr>
    <?php
        return ob_get_clean();
    }

    public function process_payment($order_id)
    {
        WC_BankNotify_Logger::info('Processing payment for order', ['order_id' => $order_id]);

        $order = wc_get_order($order_id);
        if (!$order) {
            WC_BankNotify_Logger::error('Payment processing failed: order not found', ['order_id' => $order_id]);

            return [
                'result' => 'failure',
            ];
        }

        $this->update_order_status_and_clear_cart($order);

        WC_BankNotify_Logger::debug('Payment processed successfully', [
            'order_id' => $order_id,
            'order_status' => $order->get_status(),
        ]);

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    public function thankyou_page($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $remark = $this->get_remark($order_id);

        $bank_select = $this->get_option('bank_select');
        $bank_info = $this->get_bank_info($bank_select);

        $account_number = $this->get_option('sub_account') ? $this->get_option('sub_account') : $this->get_option('bank_account_number');
        $account_holder_name = $this->get_option('bank_account_holder');

        if ($bank_info) {
            $bank_bin = $bank_info['bin'];
            $bank_logo_url = $this->get_bank_logo_url($bank_select);

            if ($this->bank_name_display_type === 'brand_name') {
                $displayed_bank_name = $bank_info['short_name'];
            } elseif ($this->bank_name_display_type === 'full_name') {
                $displayed_bank_name = $bank_info['full_name'];
            } elseif ($this->bank_name_display_type === 'full_include_brand') {
                $displayed_bank_name = $bank_info['full_name'] . ' (' . $bank_info['short_name'] . ')';
            } else {
                $displayed_bank_name = $bank_info['short_name'];
            }
        } else {
            $bank_bin = $this->bank_bin;
            $bank_logo_url = $this->bank_logo_url;
            $displayed_bank_name = $this->displayed_bank_name;
        }

        if ($this->should_skip_thankyou_page($order)) {
            return;
        }

        $qr_code_url = add_query_arg(
            [
                'acc' => $account_number,
                'bank' => $bank_bin,
                'amount' => $order->get_total(),
                'des' => $remark,
            ],
            'https://qr.sepay.vn/img'
        );

        // Get expiry settings for countdown
        $expiry_enabled = $this->get_option('payment_code_expiry_enabled', 'no') === 'yes';
        $expiry_time = null;

        if ($expiry_enabled) {
            $assigned_at = $order->get_meta('_bank_notify_code_assigned_at');
            if (!$assigned_at) {
                // Set assigned time if not exists
                $assigned_at = current_time('mysql');
                $order->update_meta_data('_bank_notify_code_assigned_at', $assigned_at);
                $order->save();
            }

            $expiry_value = absint($this->get_option('payment_code_expiry_value', 24));
            $expiry_unit = $this->get_option('payment_code_expiry_unit', 'hours');
            $expiry_seconds = $this->calculate_expiry_seconds($expiry_value, $expiry_unit);

            $expiry_time = strtotime($assigned_at) + $expiry_seconds;
        }

        require_once plugin_dir_path(__FILE__) . 'views/transfer-info.php';

        $this->enqueue_sepay_scripts($order_id, $order);
    }

    public function enqueue_sepay_scripts($order_id, $order)
    {
        $script_version = filemtime(plugin_dir_path(__DIR__) . 'assets/js/sepay.js');
        $style_version = filemtime(plugin_dir_path(__DIR__) . 'assets/css/sepay.css');

        wp_enqueue_script('bank_notify_script', plugin_dir_url(__DIR__) . 'assets/js/sepay.js', ['jquery'], $script_version, true);
        wp_enqueue_style('bank_notify_style', plugin_dir_url(__DIR__) . 'assets/css/sepay.css', [], $style_version);

        $account_number = $this->get_option('sub_account') ? $this->get_option('sub_account') : $this->get_option('bank_account_number');

        // Get expiry time for countdown
        $expiry_enabled = $this->get_option('payment_code_expiry_enabled', 'no') === 'yes';
        $expiry_timestamp = 0;

        if ($expiry_enabled) {
            $assigned_at = $order->get_meta('_bank_notify_code_assigned_at');
            if ($assigned_at) {
                $expiry_value = absint($this->get_option('payment_code_expiry_value', 24));
                $expiry_unit = $this->get_option('payment_code_expiry_unit', 'hours');
                $expiry_seconds = $this->calculate_expiry_seconds($expiry_value, $expiry_unit);
                $expiry_timestamp = strtotime($assigned_at) + $expiry_seconds;
            }
        }

        wp_localize_script('bank_notify_script', 'bank_notify_vars', [
            'ajax_url' => esc_url(admin_url('admin-ajax.php')),
            'order_code' => $this->get_option('pay_code_prefix') . $order_id,
            'account_number' => $account_number,
            'remark' => $this->get_remark($order_id),
            'amount' => $order->get_total(),
            'order_nonce' => wp_create_nonce('submit_order'),
            'order_id' => $order_id,
            'order_key' => $order->get_order_key(),
            'download_mode' => $this->get_option('download_mode'),
            'success_message' => $this->get_option('success_message') ? wp_kses_post($this->get_option('success_message')) : '<p>Thanh toán thành công!</p>',
            'expiry_enabled' => $expiry_enabled,
            'expiry_timestamp' => $expiry_timestamp,
        ]);
    }

    public function get_remark($order_id): string
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return $this->get_option('pay_code_prefix') . absint($order_id);
        }

        $mode = $this->get_option('payment_code_mode', 'prefix');

        WC_BankNotify_Logger::debug('Getting payment code for order', [
            'order_id' => $order_id,
            'mode' => $mode,
        ]);

        // Kiểm tra xem order đã có mã chưa
        $existing_code = $order->get_meta('_bank_notify_payment_code');
        if ($existing_code) {
            WC_BankNotify_Logger::debug('Using existing payment code', [
                'order_id' => $order_id,
                'code' => $existing_code,
            ]);
            $remark = $existing_code;
        } else {
            if ($mode === 'natural') {
                // Natural mode - get code from pool
                $manager = new WC_BankNotify_Payment_Code_Manager();
                $code = $manager->get_available_code($order_id);

                if (!$code) {
                    // Không còn mã khả dụng, fallback về prefix mode
                    $code = $this->get_option('pay_code_prefix') . $order_id;
                    $payment_mode = 'prefix';

                    // Log warning
                    WC_BankNotify_Logger::warning('No available payment codes. Falling back to prefix mode', [
                        'order_id' => $order_id,
                        'fallback_code' => $code,
                    ]);
                } else {
                    WC_BankNotify_Logger::info('Assigned natural payment code from pool', [
                        'order_id' => $order_id,
                        'code' => $code,
                    ]);
                    $payment_mode = 'natural';
                }

                $order->update_meta_data('_bank_notify_payment_code', $code);
                $order->update_meta_data('_bank_notify_payment_mode', $payment_mode);
                $order->update_meta_data('_bank_notify_code_assigned_at', current_time('mysql'));
                $order->save();

                $remark = $code;
            } else {
                // Prefix mode
                $remark = $this->get_option('pay_code_prefix') . $order_id;

                WC_BankNotify_Logger::info('Generated prefix payment code', [
                    'order_id' => $order_id,
                    'code' => $remark,
                    'prefix' => $this->get_option('pay_code_prefix'),
                ]);

                $order->update_meta_data('_bank_notify_payment_code', $remark);
                $order->update_meta_data('_bank_notify_payment_mode', 'prefix');
                $order->update_meta_data('_bank_notify_code_assigned_at', current_time('mysql'));
                $order->save();
            }
        }

        // Xử lý đặc biệt cho VietinBank và ABBANK
        if (in_array($this->bank_bin, ['970415', '970425'])) {
            $original_remark = $remark;
            $remark = 'SEVQR ' . $remark;

            WC_BankNotify_Logger::debug('Applied SEVQR prefix for VietinBank/ABBANK', [
                'order_id' => $order_id,
                'original' => $original_remark,
                'modified' => $remark,
            ]);
        }

        return $remark;
    }

    private function update_order_status_and_clear_cart($order)
    {
        $order->update_status('on-hold', 'Đang chờ thanh toán');

        if (function_exists('WC') && WC()->cart) {
            WC()->cart->empty_cart();
        }
    }

    private function should_skip_thankyou_page($order)
    {
        return $order->get_payment_method() !== $this->id || $order->has_status(['processing', 'completed']);
    }

    private function get_bank_data()
    {
        return [
            'vietcombank' => ['bin' => '970436', 'code' => 'VCB', 'short_name' => 'Vietcombank', 'full_name' => 'Ngân hàng TMCP Ngoại Thương Việt Nam'],
            'vpbank' => ['bin' => '970432', 'code' => 'VPB', 'short_name' => 'VPBank', 'full_name' => 'Ngân hàng TMCP Việt Nam Thịnh Vượng'],
            'acb' => ['bin' => '970416', 'code' => 'ACB', 'short_name' => 'ACB', 'full_name' => 'Ngân hàng TMCP Á Châu'],
            'sacombank' => ['bin' => '970403', 'code' => 'STB', 'short_name' => 'Sacombank', 'full_name' => 'Ngân hàng TMCP Sài Gòn Thương Tín'],
            'hdbank' => ['bin' => '970437', 'code' => 'HDB', 'short_name' => 'HDBank', 'full_name' => 'Ngân hàng TMCP Phát triển Thành phố Hồ Chí Minh'],
            'vietinbank' => ['bin' => '970415', 'code' => 'ICB', 'short_name' => 'VietinBank', 'full_name' => 'Ngân hàng TMCP Công thương Việt Nam'],
            'techcombank' => ['bin' => '970407', 'code' => 'TCB', 'short_name' => 'Techcombank', 'full_name' => 'Ngân hàng TMCP Kỹ thương Việt Nam'],
            'mbbank' => ['bin' => '970422', 'code' => 'MB', 'short_name' => 'MBBank', 'full_name' => 'Ngân hàng TMCP Quân đội'],
            'bidv' => ['bin' => '970418', 'code' => 'BIDV', 'short_name' => 'BIDV', 'full_name' => 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam'],
            'msb' => ['bin' => '970426', 'code' => 'MSB', 'short_name' => 'MSB', 'full_name' => 'Ngân hàng TMCP Hàng Hải Việt Nam'],
            'shinhanbank' => ['bin' => '970424', 'code' => 'SHBVN', 'short_name' => 'ShinhanBank', 'full_name' => 'Ngân hàng TNHH MTV Shinhan Việt Nam'],
            'tpbank' => ['bin' => '970423', 'code' => 'TPB', 'short_name' => 'TPBank', 'full_name' => 'Ngân hàng TMCP Tiên Phong'],
            'eximbank' => ['bin' => '970431', 'code' => 'EIB', 'short_name' => 'Eximbank', 'full_name' => 'Ngân hàng TMCP Xuất Nhập khẩu Việt Nam'],
            'vib' => ['bin' => '970441', 'code' => 'VIB', 'short_name' => 'VIB', 'full_name' => 'Ngân hàng TMCP Quốc tế Việt Nam'],
            'agribank' => ['bin' => '970405', 'code' => 'VBA', 'short_name' => 'Agribank', 'full_name' => 'Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam'],
            'publicbank' => ['bin' => '970439', 'code' => 'PBVN', 'short_name' => 'PublicBank', 'full_name' => 'Ngân hàng TNHH MTV Public Việt Nam'],
            'kienlongbank' => ['bin' => '970452', 'code' => 'KLB', 'short_name' => 'KienLongBank', 'full_name' => 'Ngân hàng TMCP Kiên Long'],
            'ocb' => ['bin' => '970448', 'code' => 'OCB', 'short_name' => 'OCB', 'full_name' => 'Ngân hàng TMCP Phương Đông'],
            'abbank' => ['bin' => '970425', 'code' => 'ABBANK', 'short_name' => 'ABBANK', 'full_name' => 'Ngân hàng TMCP An Bình'],
        ];
    }

    private function get_bank_info($identifier)
    {
        $bank_data = $this->get_bank_data();

        if (isset($bank_data[$identifier])) {
            return $bank_data[$identifier];
        }

        foreach ($bank_data as $bank) {
            if (
                strtolower($bank['code']) === strtolower($identifier) ||
                $bank['bin'] === $identifier ||
                $bank['short_name'] === $identifier ||
                strtolower($bank['short_name']) === strtolower($identifier)
            ) {
                return $bank;
            }
        }

        return null;
    }

    private function get_bank_logo_url($bank_key)
    {
        $bank_data = $this->get_bank_data();

        if (!isset($bank_data[$bank_key]['short_name'])) {
            return set_url_scheme(plugins_url('assets/images/logo.png', __DIR__));
        }

        return esc_url_raw(sprintf(
            'https://my.sepay.vn/assets/images/banklogo/%s.png',
            strtolower($bank_data[$bank_key]['short_name'])
        ));
    }

    /**
     * Calculate expiry time in seconds
     */
    private function calculate_expiry_seconds($value, $unit)
    {
        switch ($unit) {
            case 'minutes':
                return $value * 60;
            case 'hours':
                return $value * 3600;
            case 'days':
                return $value * 86400;
            case 'weeks':
                return $value * 604800;
            case 'months':
                return $value * 2592000; // 30 days
            default:
                return $value * 3600; // Default to hours
        }
    }

    /**
     * Generate API key for webhook
     */
    private function generate_api_key()
    {
        // Chỉ tạo key mới nếu chưa có
        $existing_key = $this->get_option('webhook_api_key');
        if (!empty($existing_key)) {
            return $existing_key;
        }

        return 'bnk_' . bin2hex(random_bytes(32));
    }

    /**
     * Generate webhook URL display field
     */
    public function generate_webhook_url_display_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = [
            'title' => '',
            'description' => '',
        ];

        $data = wp_parse_args($data, $defaults);
        $webhook_url = rest_url('bank-notify-gateway/v1/new-payment');

        ob_start();
    ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <code style="display: block; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; margin-bottom: 15px;">
                        <?php echo esc_html($webhook_url); ?>
                    </code>
                    <p class="description" style="margin-bottom: 15px;">
                        <?php echo wp_kses_post($data['description']); ?>
                    </p>

                    <!-- Accordion for detailed instructions -->
                    <details style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-top: 10px; background: #fafafa;">
                        <summary style="cursor: pointer; font-weight: 600; padding: 5px; user-select: none;">
                            📖 Hướng dẫn sử dụng Webhook
                        </summary>
                        <div style="margin-top: 15px; padding: 10px; background: #fff; border-radius: 4px;">
                            <h4 style="margin-top: 0;">Thông tin cơ bản</h4>
                            <ul style="margin-left: 20px;">
                                <li><strong>Method:</strong> POST</li>
                                <li><strong>Content-Type:</strong> text/plain</li>
                                <li><strong>Body:</strong> Nội dung thông báo thanh toán từ ngân hàng</li>
                            </ul>

                            <h4>Xác thực API Key</h4>
                            <p>Gửi API key bằng header Authorization theo định dạng Bearer token.</p>

                            <div style="margin-bottom: 15px;">
                                <strong>Bearer Token</strong>
                                <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">Authorization: Bearer &lt;api_key&gt;</pre>
                                <p style="margin: 5px 0; color: #666; font-size: 12px;">Ví dụ: Authorization: Bearer bnk_abc123...</p>
                            </div>

                            <h4>Ví dụ sử dụng cURL</h4>
                            <pre style="background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;">
curl -X POST "<?php echo esc_html($webhook_url); ?>" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: text/plain" \
  -d "Nội dung thông báo thanh toán"</pre>

                            <h4>Response</h4>
                            <p><strong>Thành công (200):</strong></p>
                            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px;">{
  "success": true,
  "message": "Order updated successfully",
  "order_id": 123,
  "new_status": "processing",
  "amount": 100000
}</pre>

                            <p><strong>Lỗi xác thực (401/403):</strong></p>
                            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px;">{
  "code": "invalid_api_key",
  "message": "Invalid API key",
  "data": { "status": 403 }
}</pre>

                            <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                                <strong>⚠️ Lưu ý bảo mật:</strong>
                                <ul style="margin: 10px 0 0 20px; font-size: 13px;">
                                    <li>Không chia sẻ API key với người khác</li>
                                    <li>Sử dụng HTTPS khi gọi webhook</li>
                                    <li>Không đưa API key vào query string hoặc log máy chủ</li>
                                    <li>Thay đổi API key định kỳ để tăng cường bảo mật</li>
                                </ul>
                            </div>
                        </div>
                    </details>
                </fieldset>
            </td>
        </tr>
    <?php
        return ob_get_clean();
    }

    /**
     * Generate logo upload field with WordPress Media Library
     */
    public function generate_logo_upload_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
        ];

        $data = wp_parse_args($data, $defaults);
        $value = $this->get_option($key);

        // Use default if no value set
        if (empty($value) && !empty($data['default'])) {
            $value = $data['default'];
        }

        if (!empty($value)) {
            $value = set_url_scheme($value);
        }

        ob_start();
    ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo wp_kses_post($this->get_tooltip_html($data)); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <div class="bank-notify-logo-upload-wrapper">
                        <input type="hidden"
                            class="<?php echo esc_attr($data['class']); ?>"
                            name="<?php echo esc_attr($field_key); ?>"
                            id="<?php echo esc_attr($field_key); ?>"
                            value="<?php echo esc_attr($value); ?>"
                            <?php disabled($data['disabled'], true); ?>
                            <?php echo wp_kses_post($this->get_custom_attribute_html($data)); ?> />

                        <div class="bank-notify-logo-preview" style="margin-bottom: 10px;">
                            <?php if (!empty($value)) : ?>
                                <img src="<?php echo esc_url($value); ?>" style="max-width: 200px; max-height: 100px; display: block; border: 1px solid #ddd; padding: 5px; background: #fff;" />
                            <?php else : ?>
                                <img src="" style="max-width: 200px; max-height: 100px; display: none; border: 1px solid #ddd; padding: 5px; background: #fff;" />
                            <?php endif; ?>
                        </div>

                        <button type="button" class="button bank-notify-upload-logo-button">
                            <?php echo esc_html(!empty($value) ? 'Thay đổi Logo' : 'Chọn Logo'); ?>
                        </button>

                        <?php if (!empty($value)) : ?>
                            <button type="button" class="button bank-notify-remove-logo-button" style="margin-left: 5px;">
                                Xóa Logo
                            </button>
                        <?php else : ?>
                            <button type="button" class="button bank-notify-remove-logo-button" style="margin-left: 5px; display: none;">
                                Xóa Logo
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php echo wp_kses_post($this->get_description_html($data)); ?>
                </fieldset>
            </td>
        </tr>
    <?php
        return ob_get_clean();
    }

}
