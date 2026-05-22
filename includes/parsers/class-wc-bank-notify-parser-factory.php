<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Factory class để tự động detect và tạo parser phù hợp
 */
class Taphoai_BankNotify_Parser_Factory
{
    /**
     * Danh sách các parser classes
     */
    private static $parsers = [
        'Taphoai_BankNotify_Parser_TPBank',
        'Taphoai_BankNotify_Parser_MBBank',
    ];

    /**
     * Parser riêng cho các ngân hàng đã hỗ trợ format cụ thể.
     */
    private static $bank_parser_map = [
        'tpbank' => 'Taphoai_BankNotify_Parser_TPBank',
        'tpb' => 'Taphoai_BankNotify_Parser_TPBank',
        'mbbank' => 'Taphoai_BankNotify_Parser_MBBank',
        'mb' => 'Taphoai_BankNotify_Parser_MBBank',
    ];

    /**
     * Tạo parser phù hợp dựa trên message body
     * 
     * @param string $body Message body từ webhook
     * @return Taphoai_BankNotify_Parser_Abstract
     */
    public static function create($body)
    {
        Taphoai_BankNotify_Logger::debug('Parser Factory: Detecting bank from message', [
            'body_preview' => substr($body, 0, 100),
        ]);

        $detected_bank = self::detect_bank_from_body($body);

        if ($detected_bank) {
            $parser_class = isset(self::$bank_parser_map[$detected_bank['key']])
                ? self::$bank_parser_map[$detected_bank['key']]
                : null;

            if (!$parser_class && isset(self::$bank_parser_map[strtolower($detected_bank['code'])])) {
                $parser_class = self::$bank_parser_map[strtolower($detected_bank['code'])];
            }

            if ($parser_class && class_exists($parser_class)) {
                $parser = new $parser_class($body);
                Taphoai_BankNotify_Logger::info('Parser Factory: Bank key detected, using specific parser', [
                    'parser' => $parser_class,
                    'bank_key' => $detected_bank['key'],
                    'bank_name' => $parser->get_bank_name(),
                    'bank_code' => $parser->get_bank_code(),
                ]);
                return $parser;
            }

            Taphoai_BankNotify_Logger::info('Parser Factory: Bank key detected, using generic parser', [
                'bank_key' => $detected_bank['key'],
                'bank_name' => $detected_bank['short_name'],
                'bank_code' => $detected_bank['code'],
            ]);

            return new Taphoai_BankNotify_Parser_Generic($body, $detected_bank['short_name'], $detected_bank['code']);
        }

        // Không có bank key trong body thì dùng parser mặc định để cố gắng trích xuất dữ liệu.
        Taphoai_BankNotify_Logger::warning('Parser Factory: No bank key detected, using generic parser');
        return new Taphoai_BankNotify_Parser_Generic($body);
    }

    /**
     * Nhận diện ngân hàng bằng key/code/short_name trong body.
     */
    private static function detect_bank_from_body($body)
    {
        if (!class_exists('Taphoai_Gateway_BankNotify') || !method_exists('Taphoai_Gateway_BankNotify', 'get_supported_bank_data')) {
            return null;
        }

        $body_lower = function_exists('mb_strtolower') ? mb_strtolower($body, 'UTF-8') : strtolower($body);
        $bank_data = Taphoai_Gateway_BankNotify::get_supported_bank_data();

        foreach ($bank_data as $bank_key => $bank) {
            $needles = array_filter([
                $bank_key,
                isset($bank['code']) ? $bank['code'] : null,
                isset($bank['short_name']) ? $bank['short_name'] : null,
            ]);

            foreach ($needles as $needle) {
                $needle_lower = function_exists('mb_strtolower') ? mb_strtolower($needle, 'UTF-8') : strtolower($needle);

                if (preg_match('/(?<![a-z0-9])' . preg_quote($needle_lower, '/') . '(?![a-z0-9])/i', $body_lower)) {
                    return array_merge(['key' => $bank_key], $bank);
                }
            }
        }

        return null;
    }

    /**
     * Đăng ký parser mới
     * 
     * @param string $parser_class Class name của parser
     */
    public static function register_parser($parser_class)
    {
        if (!in_array($parser_class, self::$parsers)) {
            // Thêm vào đầu mảng để ưu tiên parser mới
            array_unshift(self::$parsers, $parser_class);

            Taphoai_BankNotify_Logger::debug('Parser Factory: New parser registered', [
                'parser' => $parser_class,
            ]);
        }
    }

    /**
     * Lấy danh sách tất cả parsers đã đăng ký
     * 
     * @return array
     */
    public static function get_registered_parsers()
    {
        return self::$parsers;
    }

    /**
     * Lấy danh sách các ngân hàng được hỗ trợ
     * 
     * @return array {
     *     @type string $bank_code => $bank_name
     * }
     */
    public static function get_supported_banks()
    {
        $banks = [];

        foreach (self::$parsers as $parser_class) {
            if (!class_exists($parser_class)) {
                continue;
            }

            // Tạo instance tạm để lấy thông tin
            $parser = new $parser_class('');
            $banks[$parser->get_bank_code()] = $parser->get_bank_name();
        }

        return $banks;
    }
}

if (!class_exists('WC_BankNotify_Parser_Factory', false)) {
    class_alias('Taphoai_BankNotify_Parser_Factory', 'WC_BankNotify_Parser_Factory');
}
