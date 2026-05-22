<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parser cho thông báo từ TPBank
 */
class Taphoai_BankNotify_Parser_TPBank extends Taphoai_BankNotify_Parser_Abstract
{
    /**
     * Kiểm tra xem message có phải từ TPBank không
     */
    public function detect()
    {
        // TPBank thường có prefix SEVQR hoặc format đơn giản PREFIX+NUMBER
        // hoặc có các từ khóa đặc trưng
        if (preg_match('/^SEVQR\s+/i', $this->body)) {
            return true;
        }

        // Kiểm tra format đơn giản: PREFIX+NUMBER và không có ký tự đặc biệt của ngân hàng khác
        if (preg_match('/([A-Z]{2,10})(\d+)/i', $this->body) && !preg_match('/\|/', $this->body)) {
            return true;
        }

        // Nếu có các từ khóa TPBank
        if (preg_match('/TPBank|TPBANK/i', $this->body)) {
            return true;
        }

        return false;
    }

    /**
     * Trích xuất payment code
     */
    public function extract_payment_code()
    {
        $body = $this->body;

        // Loại bỏ prefix SEVQR nếu có
        $body = preg_replace('/^SEVQR\s+/i', '', $body);

        // Tìm pattern: Tiền tố (2-10 chữ cái) + Số
        // Ví dụ: DH35434, ORDER123, etc.
        if (preg_match('/([A-Z]{2,10})(\d+)/i', $body, $matches)) {
            return $matches[1] . $matches[2];
        }

        // Nếu không tìm thấy pattern prefix+số, tìm chuỗi tự nhiên
        $lines = explode("\n", $body);
        foreach ($lines as $line) {
            $line = trim($line);
            // Bỏ qua dòng chứa số tiền, số dư, tài khoản
            if (preg_match('/(VND|Số dư|Tài khoản|balance|\d{10,})/i', $line)) {
                continue;
            }
            // Lấy chuỗi text thuần (chỉ chữ cái và khoảng trắng)
            if (preg_match('/^([A-Za-z\s]{3,50})$/', $line, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Trích xuất số tiền
     */
    public function extract_amount()
    {
        $body = $this->body;

        // Pattern 1: + VND 12,000 hoặc +12,000 VND
        if (preg_match('/\+\s*(?:VND\s*)?([\d,\.]+)(?:\s*VND)?/i', $body, $matches)) {
            $amount_str = $this->clean_number_string($matches[1]);
            return floatval($amount_str);
        }

        // Pattern 2: Số tiền: 152,000 VND hoặc Amount: 152000
        if (preg_match('/(?:Số tiền|Amount|Tiền|Money)[\s:]+(?:VND\s*)?([\d,\.]+)(?:\s*VND)?/i', $body, $matches)) {
            $amount_str = $this->clean_number_string($matches[1]);
            return floatval($amount_str);
        }

        // Pattern 3: VND 152,000 hoặc 152000 VND (không có dấu +)
        if (preg_match('/(?:VND\s+)?([\d,\.]+)(?:\s*VND)?/i', $body, $matches)) {
            $amount_str = $this->clean_number_string($matches[1]);
            $amount = floatval($amount_str);
            // Chỉ chấp nhận nếu số đủ lớn (tránh nhầm với số tài khoản)
            if ($amount >= 1000) {
                return $amount;
            }
        }

        return null;
    }

    /**
     * Trích xuất số tài khoản
     */
    public function extract_account_number()
    {
        $body = $this->body;

        // Pattern 1: Tài khoản: 19028697499027
        if (preg_match('/(?:Tài khoản|Account|TK|ACC)[\s:]+(\d{10,20})/i', $body, $matches)) {
            return $matches[1];
        }

        // Pattern 2: Số TK 19028697499027
        if (preg_match('/(?:Số TK|Account No|A\/C)[\s:]+(\d{10,20})/i', $body, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Lấy tên ngân hàng
     */
    public function get_bank_name()
    {
        return 'TPBank';
    }

    /**
     * Lấy mã ngân hàng
     */
    public function get_bank_code()
    {
        return 'TPBANK';
    }
}

if (!class_exists('WC_BankNotify_Parser_TPBank', false)) {
    class_alias('Taphoai_BankNotify_Parser_TPBank', 'WC_BankNotify_Parser_TPBank');
}
