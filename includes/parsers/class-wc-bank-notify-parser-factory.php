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
        'Taphoai_BankNotify_Parser_MBBank',
        'Taphoai_BankNotify_Parser_TPBank',
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

        // Loop qua tất cả parsers và tìm parser phù hợp
        foreach (self::$parsers as $parser_class) {
            if (!class_exists($parser_class)) {
                Taphoai_BankNotify_Logger::warning('Parser Factory: Parser class not found', [
                    'class' => $parser_class,
                ]);
                continue;
            }

            $parser = new $parser_class($body);

            if ($parser->detect()) {
                Taphoai_BankNotify_Logger::info('Parser Factory: Bank detected', [
                    'parser' => $parser_class,
                    'bank_name' => $parser->get_bank_name(),
                    'bank_code' => $parser->get_bank_code(),
                ]);
                return $parser;
            }
        }

        // Nếu không tìm thấy parser phù hợp, sử dụng TPBank làm default
        Taphoai_BankNotify_Logger::warning('Parser Factory: No specific parser detected, using default TPBank parser');
        return new Taphoai_BankNotify_Parser_TPBank($body);
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
