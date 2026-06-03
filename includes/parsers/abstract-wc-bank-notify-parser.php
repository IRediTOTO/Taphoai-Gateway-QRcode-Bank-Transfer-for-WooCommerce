<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract class cho parser thông báo ngân hàng
 * Mỗi ngân hàng sẽ có parser riêng kế thừa từ class này
 */
abstract class TaphGaqr_Parser_Abstract
{
    /**
     * Message body từ webhook
     */
    protected $body;

    /**
     * Constructor
     */
    public function __construct($body = '')
    {
        $this->body = $body;
    }

    /**
     * Set message body
     */
    public function set_body($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Get message body
     */
    public function get_body()
    {
        return $this->body;
    }

    /**
     * Kiểm tra xem message có phù hợp với parser này không
     * 
     * @return bool
     */
    abstract public function detect();

    /**
     * Trích xuất payment code từ message
     * 
     * @return string|null
     */
    abstract public function extract_payment_code();

    /**
     * Trích xuất số tiền từ message
     * 
     * @return float|null
     */
    abstract public function extract_amount();

    /**
     * Trích xuất số tài khoản từ message
     * 
     * @return string|null
     */
    abstract public function extract_account_number();

    /**
     * Lấy tên ngân hàng
     * 
     * @return string
     */
    abstract public function get_bank_name();

    /**
     * Lấy mã ngân hàng (bank code)
     * 
     * @return string
     */
    abstract public function get_bank_code();

    /**
     * Parse toàn bộ thông tin từ message
     * 
     * @return array {
     *     @type string|null $payment_code
     *     @type float|null $amount
     *     @type string|null $account_number
     *     @type string $bank_name
     *     @type string $bank_code
     * }
     */
    public function parse()
    {
        return [
            'payment_code' => $this->extract_payment_code(),
            'amount' => $this->extract_amount(),
            'account_number' => $this->extract_account_number(),
            'bank_name' => $this->get_bank_name(),
            'bank_code' => $this->get_bank_code(),
        ];
    }

    /**
     * Helper: Loại bỏ dấu phẩy và chấm từ chuỗi số
     */
    protected function clean_number_string($str)
    {
        return str_replace([',', '.'], '', $str);
    }

    /**
     * Helper: Loại bỏ các ký tự không phải số
     */
    protected function extract_digits_only($str)
    {
        return preg_replace('/[^0-9]/', '', $str);
    }
}

if (!class_exists('TaphGaqr_WC_Parser_Abstract', false)) {
    class_alias('TaphGaqr_Parser_Abstract', 'TaphGaqr_WC_Parser_Abstract');
}
