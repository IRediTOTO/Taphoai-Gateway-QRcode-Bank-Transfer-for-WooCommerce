<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parser mặc định cho webhook chưa có parser riêng.
 */
class TaphGaqr_Parser_Generic extends TaphGaqr_Parser_Abstract
{
    private $bank_name;

    private $bank_code;

    public function __construct($body = '', $bank_name = 'Unknown Bank', $bank_code = 'UNKNOWN')
    {
        parent::__construct($body);
        $this->bank_name = $bank_name;
        $this->bank_code = $bank_code;
    }

    public function detect()
    {
        return true;
    }

    public function extract_payment_code()
    {
        $body = $this->body;

        if (preg_match('/(?:^|\R)(?:ND|Noi dung|Content|Description|Remark|Memo)\s*:\s*(.+?)(?:\R|$)/iu', $body, $matches)) {
            $content = $this->clean_payment_content($matches[1]);
            if ($content !== null) {
                return $content;
            }
        }

        if (preg_match('/\b([A-Z]{2,10})(\d+)\b/i', $body, $matches)) {
            return $matches[1] . $matches[2];
        }

        $lines = preg_split('/\R/', $body);
        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || preg_match('/(?:BANK|TK|ACC|Account|VND|SD|Balance|PS|GD|SO GD|Transaction)/iu', $line)) {
                continue;
            }

            $content = $this->clean_payment_content($line);
            if ($content !== null) {
                return $content;
            }
        }

        return null;
    }

    public function extract_amount()
    {
        $body = $this->body;

        $patterns = [
            '/(?:^|\R)(?:PS|GD|Amount|So tien|Số tiền|Tien|Tiền)\s*:\s*\+?\s*(?:VND\s*)?([\d,.]+)\s*(?:VND)?/iu',
            '/\+\s*(?:VND\s*)?([\d,.]+)\s*(?:VND)?/iu',
            '/(?:VND\s*)?([\d,.]+)\s*VND/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                $amount = floatval($this->clean_number_string($matches[1]));
                if ($amount >= 1000) {
                    return $amount;
                }
            }
        }

        return null;
    }

    public function extract_account_number()
    {
        $body = $this->body;

        if (preg_match('/(?:^|\R)(?:TK|So TK|Số TK|Account|Account No|ACC|A\/C)\s*:\s*([0-9x]{4,20})/iu', $body, $matches)) {
            return str_replace('x', '', $matches[1]);
        }

        if (preg_match('/\b(\d{10,20})\b/', $body, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function get_bank_name()
    {
        return $this->bank_name;
    }

    public function get_bank_code()
    {
        return $this->bank_code;
    }

    private function clean_payment_content($content)
    {
        $content = trim((string) $content);
        $content = preg_replace('/\b\d{10,}\b/', '', $content);
        $content = trim($content);

        if (strlen($content) < 3 || strlen($content) > 100) {
            return null;
        }

        return $content;
    }
}

if (!class_exists('TaphGaqr_WC_Parser_Generic', false)) {
    class_alias('TaphGaqr_Parser_Generic', 'TaphGaqr_WC_Parser_Generic');
}
