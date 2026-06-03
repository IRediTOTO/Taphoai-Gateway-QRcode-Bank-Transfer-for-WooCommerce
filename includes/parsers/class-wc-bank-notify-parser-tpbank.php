<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parser cho thông báo từ TPBank
 */
class TaphGaqr_Parser_TPBank extends TaphGaqr_Parser_Abstract
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

        // Format SMS/app notification phổ biến: TK, PS, SD, ND trên từng dòng.
        if (preg_match('/(?:^|\R)TK\s*:/i', $this->body) && preg_match('/(?:^|\R)ND\s*:/i', $this->body)) {
            return true;
        }

        return false;
    }

    /**
     * Trích xuất payment code
     */
    public function extract_payment_code()
    {
        if (preg_match('/(?:^|\R)ND\s*:\s*(.+?)(?:\R|$)/iu', $this->body, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Trích xuất số tiền
     */
    public function extract_amount()
    {
        if (preg_match('/(?:^|\R)PS\s*:\s*\+\s*([\d,.]+)\s*VND\b/iu', $this->body, $matches)) {
            $amount_str = $this->clean_number_string($matches[1]);
            return floatval($amount_str);
        }

        return null;
    }

    /**
     * Trích xuất số tài khoản
     */
    public function extract_account_number()
    {
        if (preg_match('/(?:^|\R)TK\s*:\s*([0-9x]{7,20})/iu', $this->body, $matches)) {
            $account_digits = preg_replace('/\D/', '', $matches[1]);
            return substr($account_digits, -7);
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

if (!class_exists('TaphGaqr_WC_Parser_TPBank', false)) {
    class_alias('TaphGaqr_Parser_TPBank', 'TaphGaqr_WC_Parser_TPBank');
}
