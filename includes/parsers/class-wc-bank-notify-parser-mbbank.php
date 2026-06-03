<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parser cho thông báo từ MBBank (MB Bank)
 * 
 * Format mẫu:
 * TK 37xxx818|GD: +80VND 16/05/26 23:59|SD:4,771,149VND|ND: Tra lai tien gui tai khoan cua 370031818-20260516
 * 
 * Cấu trúc:
 * - TK: Tài khoản (Account number)
 * - GD: Giao dịch (Transaction) - số tiền + thời gian
 * - SD: Số dư (Balance)
 * - ND: Nội dung (Description/Content) - chứa payment code
 */
class TaphGaqr_Parser_MBBank extends TaphGaqr_Parser_Abstract
{
    /**
     * Kiểm tra xem message có phải từ MBBank không
     */
    public function detect()
    {
        // MBBank có format đặc trưng với dấu | phân cách và các prefix TK, GD, SD, ND
        if (preg_match('/TK\s+[0-9x]+\s*\|.*GD:.*\|.*SD:.*\|.*ND:/i', $this->body)) {
            return true;
        }

        // Kiểm tra có từ khóa MBBank
        if (preg_match('/MBBank|MB Bank|MBBANK/i', $this->body)) {
            return true;
        }

        return false;
    }

    /**
     * Trích xuất payment code từ phần ND (Nội dung)
     */
    public function extract_payment_code()
    {
        $body = $this->body;

        // Tìm phần ND: (Nội dung)
        if (preg_match('/ND:\s*(.+?)(?:\||$)/i', $body, $matches)) {
            $content = trim($matches[1]);

            // Loại bỏ các ký tự đặc biệt và số tài khoản dài
            // Tìm pattern PREFIX+NUMBER (ví dụ: DH123, ORDER456)
            if (preg_match('/\b([A-Z]{2,10})(\d+)\b/i', $content, $code_matches)) {
                return $code_matches[1] . $code_matches[2];
            }

            // Nếu không tìm thấy pattern, lấy toàn bộ nội dung (loại bỏ số tài khoản dài)
            // Loại bỏ số có nhiều hơn 10 chữ số (có thể là số tài khoản)
            $content = preg_replace('/\b\d{10,}\b/', '', $content);
            $content = trim($content);

            // Nếu còn lại text hợp lệ, return
            if (strlen($content) >= 3 && strlen($content) <= 100) {
                return $content;
            }
        }

        return null;
    }

    /**
     * Trích xuất số tiền từ phần GD (Giao dịch)
     */
    public function extract_amount()
    {
        $body = $this->body;

        // Tìm phần GD: (Giao dịch)
        // Format: GD: +80VND hoặc GD: +80,000VND
        if (preg_match('/GD:\s*\+\s*([\d,\.]+)\s*VND/i', $body, $matches)) {
            $amount_str = $this->clean_number_string($matches[1]);
            return floatval($amount_str);
        }

        // Fallback: tìm số tiền với dấu + ở bất kỳ đâu
        if (preg_match('/\+\s*([\d,\.]+)\s*VND/i', $body, $matches)) {
            $amount_str = $this->clean_number_string($matches[1]);
            return floatval($amount_str);
        }

        return null;
    }

    /**
     * Trích xuất số tài khoản từ phần TK
     */
    public function extract_account_number()
    {
        $body = $this->body;

        // Tìm phần TK: (Tài khoản)
        // Format: TK 37xxx818 hoặc TK 370031818
        if (preg_match('/TK[\s:]+([0-9x]+)/i', $body, $matches)) {
            // Loại bỏ ký tự x (ẩn số)
            $account = str_replace('x', '', $matches[1]);
            return $account;
        }

        // Fallback: tìm số tài khoản dài
        if (preg_match('/\b(\d{10,20})\b/', $body, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Lấy tên ngân hàng
     */
    public function get_bank_name()
    {
        return 'MB Bank';
    }

    /**
     * Lấy mã ngân hàng
     */
    public function get_bank_code()
    {
        return 'MBBANK';
    }

    /**
     * Trích xuất số dư (Balance) - method bổ sung cho MBBank
     */
    public function extract_balance()
    {
        $body = $this->body;

        // Tìm phần SD: (Số dư)
        if (preg_match('/SD:\s*([\d,\.]+)\s*VND/i', $body, $matches)) {
            $balance_str = $this->clean_number_string($matches[1]);
            return floatval($balance_str);
        }

        return null;
    }

    /**
     * Trích xuất thời gian giao dịch - method bổ sung cho MBBank
     */
    public function extract_transaction_time()
    {
        $body = $this->body;

        // Tìm thời gian trong phần GD
        // Format: 16/05/26 23:59 (YY/MM/DD HH:MM)
        if (preg_match('/(\d{2}\/\d{2}\/\d{2}\s+\d{2}:\d{2})/', $body, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

if (!class_exists('TaphGaqr_WC_Parser_MBBank', false)) {
    class_alias('TaphGaqr_Parser_MBBank', 'TaphGaqr_WC_Parser_MBBank');
}
