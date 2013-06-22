<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * FormChecker
 *
 * $Id: form_checker.php 33 2012-08-09 10:39:41Z leikou $
 */

/**
 * coding: utf-8
 * check form data format
 *
 * <code>
 * <?php
 * $source: $_GET OR $_POST.
 * $input_format = {
 *      'name' => FC_str('abc', 'default-value')->min(2)->max(32),
 *      'age' => FC_int()->min(0)->max(200),
 *      'choices' => FC_int()->min(1)->max(10)
 *          ->multi()->required(),
 *      'email' => FC_email('邮件')->msg('{{ name }} xxxxx'),
 *      'name' => FC_str('chinese_name', 'default_value')->max(10)
 *          ->min(1)->multi()->required()->msg('{name}: xxxx')-exp('pattern'),
 * }
 *
 * checker = new Form_checker;
 *
 * checker.check($_GET, $input_format);
 * checker.get_valid_data();
 * checker.get_raw_data();
 * checker.get_error_message();
 * ?>
 */


/**
 * Base class for form data format checking. Forbidden instance directly.
 */
class Form_checker {
    private $_is_valid;
    private $_error_message;
    private $_valid_data;
    private $_raw_data;


    public function __construct() {
        $this->_init();
    }


    private function _init() {
        $this->_is_valid = TRUE;
        $this->_error_message = array();
        $this->_valid_data = array();
        $this->_raw_data = array();
    }


    /**
     * @param array $source: data source, dict.
     * @param array $input_format: form setting
     *
     * @return bool
     */
    public function check($source, $input_format) {
        $this->_init();

        foreach($input_format as $field_name => $checker) {
            // Get data
            $value = isset($source[$field_name]) ? $source[$field_name] : NULL;
            // Check format
            list($is_valid, $this->_raw_data[$field_name], $v, $m) = $checker->check($value);
            if($is_valid) {
                $this->_valid_data[$field_name] = $v;
            } else {
                $this->_error_message[$field_name] = $m;
            }

            $this->_is_valid = $this->_is_valid && $is_valid;
        }

        return $this->_is_valid;
    }


    public function get_valid_data() {
        return $this->_valid_data;
    }

    public function get_raw_data() {
        return $this->_raw_data;
    }


    /* Get data format error msg
     *
     * @return: array.
     */
    public function get_error_message() {
        return $this->_error_message;
    }

}

/* End of file: form_checker.php */
/* Location: application/libraries/form_checker.php */
