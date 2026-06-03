<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if (! defined('ABSPATH')) {
    exit;
}

final class TaphGaqr_Blocks_Support extends AbstractPaymentMethodType
{
    protected $name = 'bank_notify';

    public function initialize() {
        $this->settings = get_option('woocommerce_taphgaqr_settings', []);
    }

    public function is_active()
    {
        return filter_var($this->get_setting('enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function get_payment_method_script_handles()
    {
        $script_path = plugin_dir_path(__DIR__) . 'assets/js/block/checkout.js';

        if (file_exists($script_path)) {
            $script_version = filemtime($script_path);
        } else {
            $script_version = '';
        }

        wp_register_script(
            'wc-bank-notify-blocks-integration',
            plugin_dir_url(__DIR__) . 'assets/js/block/checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            $script_version,
            true
        );

        return ['wc-bank-notify-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => sanitize_text_field((string) $this->get_setting('title')),
            'description' => wp_kses_post((string) $this->get_setting('description')),
            'logo' => esc_url_raw((string) $this->get_setting('logo', plugins_url('assets/images/logo.png', __DIR__))),
            'supports' => $this->get_supported_features(),
        ];
    }
}

if (!class_exists('TaphGaqr_WC_Blocks_Support', false)) {
    class_alias('TaphGaqr_Blocks_Support', 'TaphGaqr_WC_Blocks_Support');
}
