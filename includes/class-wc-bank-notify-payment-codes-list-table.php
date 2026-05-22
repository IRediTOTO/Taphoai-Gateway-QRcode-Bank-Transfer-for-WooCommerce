<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WC_BankNotify_Payment_Codes_List_Table extends WP_List_Table
{
    private $manager;
    private $db;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'payment_code',
            'plural'   => 'payment_codes',
            'ajax'     => false,
        ]);

        $this->manager = new WC_BankNotify_Payment_Code_Manager();
        $this->db = new WC_BankNotify_DB();
    }

    /**
     * Read a GET query argument used only for table filtering/sorting.
     */
    private function get_query_arg($key, $default = '', $sanitize = 'text')
    {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);

        if ($value === null || $value === false) {
            return $default;
        }

        $value = wp_unslash($value);

        return $sanitize === 'key' ? sanitize_key($value) : sanitize_text_field($value);
    }

    /**
     * Get columns
     */
    public function get_columns()
    {
        return [
            'cb'          => '<input type="checkbox" />',
            'code'        => 'Mã thanh toán',
            'status'      => 'Trạng thái',
            'order_id'    => 'Đơn hàng',
            'assigned_at' => 'Ngày gán',
            'used_at'     => 'Ngày dùng',
            'created_at'  => 'Ngày tạo',
        ];
    }

    /**
     * Get sortable columns
     */
    protected function get_sortable_columns()
    {
        return [
            'code'        => ['code', false],
            'status'      => ['status', false],
            'order_id'    => ['order_id', false],
            'assigned_at' => ['assigned_at', false],
            'used_at'     => ['used_at', false],
            'created_at'  => ['created_at', true], // true = already sorted
        ];
    }

    /**
     * Get bulk actions
     */
    protected function get_bulk_actions()
    {
        return [
            'delete'         => 'Xóa',
            'mark_available' => 'Đánh dấu khả dụng',
        ];
    }

    /**
     * Get views (filter tabs)
     */
    protected function get_views()
    {
        $stats = $this->manager->get_stats();
        $current = $this->get_query_arg('status', 'all', 'key');

        $status_links = [
            'all'       => 'Tất cả',
            'available' => 'Khả dụng',
            'assigned'  => 'Đã gán',
            'used'      => 'Đã dùng',
        ];

        $views = [];
        foreach ($status_links as $status => $label) {
            $count = $status === 'all' ? $stats['total'] : ($stats[$status] ?? 0);
            $class = $current === $status ? 'current' : '';
            $url = add_query_arg(['status' => $status], remove_query_arg('paged'));
            
            $views[$status] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url($url),
                esc_attr($class),
                esc_html($label),
                $count
            );
        }

        return $views;
    }

    /**
     * Column checkbox
     */
    protected function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="codes[]" value="%s" />', esc_attr($item->code));
    }

    /**
     * Column code
     */
    protected function column_code($item)
    {
        $actions = [];

        // View order action
        if ($item->order_id) {
            $actions['view_order'] = sprintf(
                '<a href="%s">Xem đơn hàng</a>',
                esc_url(admin_url('post.php?post=' . $item->order_id . '&action=edit'))
            );
        }

        // Release action (only for assigned status)
        if ($item->status === 'assigned') {
            $actions['release'] = sprintf(
                '<a href="#" class="bank-notify-release-code" data-code="%s">Giải phóng</a>',
                esc_attr($item->code)
            );
        }

        // Delete action
        $actions['delete'] = sprintf(
            '<a href="#" class="bank-notify-delete-code" data-code="%s" style="color: #b32d2e;">Xóa</a>',
            esc_attr($item->code)
        );

        return sprintf(
            '<strong>%s</strong>%s',
            esc_html($item->code),
            $this->row_actions($actions)
        );
    }

    /**
     * Column status
     */
    protected function column_status($item)
    {
        $status_labels = [
            'available' => '<span style="color: #46b450;">● Khả dụng</span>',
            'assigned'  => '<span style="color: #ffb900;">● Đá gán</span>',
            'used'      => '<span style="color: #dc3232;">● Đã dùng</span>',
        ];

        return $status_labels[$item->status] ?? esc_html($item->status);
    }

    /**
     * Column order_id
     */
    protected function column_order_id($item)
    {
        if (!$item->order_id) {
            return '—';
        }

        return sprintf(
            '<a href="%s">#%d</a>',
            esc_url(admin_url('post.php?post=' . $item->order_id . '&action=edit')),
            absint($item->order_id)
        );
    }

    /**
     * Column assigned_at
     */
    protected function column_assigned_at($item)
    {
        if (!$item->assigned_at || $item->assigned_at === '0000-00-00 00:00:00') {
            return '—';
        }

        return sprintf(
            '<abbr title="%s">%s</abbr>',
            esc_attr($item->assigned_at),
            esc_html(human_time_diff(strtotime($item->assigned_at), current_time('timestamp')) . ' trước')
        );
    }

    /**
     * Column used_at
     */
    protected function column_used_at($item)
    {
        if (!$item->used_at || $item->used_at === '0000-00-00 00:00:00') {
            return '—';
        }

        return sprintf(
            '<abbr title="%s">%s</abbr>',
            esc_attr($item->used_at),
            esc_html(human_time_diff(strtotime($item->used_at), current_time('timestamp')) . ' trước')
        );
    }

    /**
     * Column created_at
     */
    protected function column_created_at($item)
    {
        return sprintf(
            '<abbr title="%s">%s</abbr>',
            esc_attr($item->created_at),
            esc_html(human_time_diff(strtotime($item->created_at), current_time('timestamp')) . ' trước')
        );
    }

    /**
     * Default column
     */
    protected function column_default($item, $column_name)
    {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '—';
    }

    /**
     * Prepare items for display
     */
    public function prepare_items()
    {
        global $wpdb;

        // Handle bulk actions
        $this->process_bulk_action();

        // Columns
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Get status filter
        $status = $this->get_query_arg('status', 'all', 'key');
        $status = $status !== 'all' ? $status : null;

        // Get search query
        $search = $this->get_query_arg('s');

        // Build query
        $table_name = $this->db->get_table_name();

        if ($status) {
            $status = in_array($status, ['available', 'assigned', 'used'], true) ? $status : null;
        }

        if ($search) {
            $search = '%' . $wpdb->esc_like($search) . '%';
        }

        // Get total items
        if ($status && $search) {
            $total_items = $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE status = %s AND code LIKE %s',
                $table_name,
                $status,
                $search
            ));
        } elseif ($status) {
            $total_items = $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE status = %s',
                $table_name,
                $status
            ));
        } elseif ($search) {
            $total_items = $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE code LIKE %s',
                $table_name,
                $search
            ));
        } else {
            $total_items = $wpdb->get_var(
                $wpdb->prepare('SELECT COUNT(*) FROM %i', $table_name)
            );
        }

        // Get orderby
        $orderby = $this->get_query_arg('orderby', 'created_at', 'key');
        $order = strtoupper($this->get_query_arg('order', 'DESC', 'key'));

        // Validate orderby
        $allowed_orderby = ['code', 'status', 'order_id', 'assigned_at', 'used_at', 'created_at'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'created_at';
        }

        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        // Get items
        if ($status && $search) {
            if ($order === 'ASC') {
                $items = $wpdb->get_results($wpdb->prepare(
                    'SELECT * FROM %i WHERE status = %s AND code LIKE %s ORDER BY %i ASC LIMIT %d OFFSET %d',
                    $table_name,
                    $status,
                    $search,
                    $orderby,
                    $per_page,
                    $offset
                ));
            } else {
                $items = $wpdb->get_results($wpdb->prepare(
                    'SELECT * FROM %i WHERE status = %s AND code LIKE %s ORDER BY %i DESC LIMIT %d OFFSET %d',
                    $table_name,
                    $status,
                    $search,
                    $orderby,
                    $per_page,
                    $offset
                ));
            }
        } elseif ($status) {
            if ($order === 'ASC') {
                $items = $wpdb->get_results($wpdb->prepare(
                    'SELECT * FROM %i WHERE status = %s ORDER BY %i ASC LIMIT %d OFFSET %d',
                    $table_name,
                    $status,
                    $orderby,
                    $per_page,
                    $offset
                ));
            } else {
                $items = $wpdb->get_results($wpdb->prepare(
                    'SELECT * FROM %i WHERE status = %s ORDER BY %i DESC LIMIT %d OFFSET %d',
                    $table_name,
                    $status,
                    $orderby,
                    $per_page,
                    $offset
                ));
            }
        } elseif ($search) {
            if ($order === 'ASC') {
                $items = $wpdb->get_results($wpdb->prepare(
                    'SELECT * FROM %i WHERE code LIKE %s ORDER BY %i ASC LIMIT %d OFFSET %d',
                    $table_name,
                    $search,
                    $orderby,
                    $per_page,
                    $offset
                ));
            } else {
                $items = $wpdb->get_results($wpdb->prepare(
                    'SELECT * FROM %i WHERE code LIKE %s ORDER BY %i DESC LIMIT %d OFFSET %d',
                    $table_name,
                    $search,
                    $orderby,
                    $per_page,
                    $offset
                ));
            }
        } else {
            if ($order === 'ASC') {
                $items = $wpdb->get_results($wpdb->prepare(
                    'SELECT * FROM %i ORDER BY %i ASC LIMIT %d OFFSET %d',
                    $table_name,
                    $orderby,
                    $per_page,
                    $offset
                ));
            } else {
                $items = $wpdb->get_results($wpdb->prepare(
                    'SELECT * FROM %i ORDER BY %i DESC LIMIT %d OFFSET %d',
                    $table_name,
                    $orderby,
                    $per_page,
                    $offset
                ));
            }
        }

        $this->items = $items;

        // Set pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action()
    {
        $action = $this->current_action();

        if (!$action || empty($_POST['codes'])) {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bulk-payment_codes')) {
            return;
        }

        $codes = array_map(
            'sanitize_text_field',
            wp_unslash($_POST['codes'])
        );

        global $wpdb;
        $table_name = $this->db->get_table_name();

        switch ($action) {
            case 'delete':
                foreach ($codes as $code) {
                    $wpdb->delete($table_name, ['code' => $code], ['%s']);
                }
                delete_transient('bank_notify_stats_cache');

                add_settings_error(
                    'bank_notify_messages',
                    'bank_notify_message',
                    sprintf('Đã xóa %d mã thanh toán.', count($codes)),
                    'success'
                );
                break;

            case 'mark_available':
                foreach ($codes as $code) {
                    $wpdb->update(
                        $table_name,
                        [
                            'status' => 'available',
                            'order_id' => null,
                            'assigned_at' => null,
                        ],
                        [
                            'code' => $code,
                            'status' => 'assigned',
                        ],
                        ['%s', '%d', '%s'],
                        ['%s', '%s']
                    );
                }
                delete_transient('bank_notify_stats_cache');

                add_settings_error(
                    'bank_notify_messages',
                    'bank_notify_message',
                    sprintf('Đã đánh dấu %d mã là khả dụng.', count($codes)),
                    'success'
                );
                break;
        }

        // Redirect to remove query args
        wp_safe_redirect(remove_query_arg(['action', 'action2', 'codes', '_wpnonce', '_wp_http_referer']));
        exit;
    }
}
