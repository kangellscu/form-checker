<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Format tool class for variable format check
 *
 * $Id: format_tool.php 391 2012-12-06 09:36:27Z leikou $
 */


class Format_tool {};

/**
 * Format base class
 * <code>
 * <?php
 *      $format = new F_str('name')
 *          ->min(1)->max(2)->multi(5)->required()->strict()
 *          ->valid_v(array(1, 2, 3))->msg('{{name}} xxxxx');
 *
 *      list($is_valid, $raw, $valid_value, $err_msg) = $format->check($value);
 *
 */
abstract class Input {
    protected $_encoding = 'UTF-8';
    protected static $_error_message = array(
        'max' => "{{name}}最大值为{{max}}",
        'min' => "{{name}}最小值为{{min}}",
        'max_len' => "{{name}}最大长度为{{max_len}}个字符",
        'min_len' => "{{name}}最小长度为{{min_len}}个字符",
        'blank' => "{{name}}必填",
        'format' => "{{name}}格式有错",
        'multi_overflow' => "{{name}}最多允许选择{{max_num}}项",
        'default' => "{{name}}格式有错",
    );

    protected $_max = NULL;
    protected $_min = NULL;
    protected $_required = FALSE;
    protected $_strict = FALSE; // means empty string will treat as no input
    protected $_multiple = FALSE;
    protected $_valid_values = array();
    protected $_msg_key = NULL;
    protected $_msg_vars = array();
    protected $_default_value = NULL;
    protected $_custom_error_msg = NULL;


    /**
     * @param string $field: var real name (such as chinese name)
     * @param mixed $default_value: default value
     */
    public function __construct($field_name, $default = NULL)
    {
        $this->_default_value = $default;
        $this->_msg_vars = array(
            'name' => $field_name,
        );
    }


    /**
     * @param integers $max_value: if type of var is integers, 
     *      specify max value. if type of var is strings, 
     *      specify str max length
     */
    public function max($max_value)
    {
        if( ! is_numeric($max_value)) {
            trigger_error(
                "Form_check error: checker method max() expect a 'numeric' ".
                "argument, but supply '".gettype($max_value)."'",
                E_USER_ERROR
            );
        }
        $max_value = (int)$max_value;
        $this->_max = $max_value;

        return $this;
    }


    /**
     * @param integers $min_value: if type of var is integers, 
     *      specify min value. if type of var is strings, 
     *      specify str min length
     *
     */
    public function min($min_value) {
        if( ! is_numeric($min_value)) {
            trigger_error(
                "Form_check error: checker method min() expect a 'numeric' ".
                "argument, but supply '".gettype($min_value)."'",
                E_USER_ERROR
            );
        }
        $max_value = (int)$min_value;
        $this->_min = $min_value;

        return $this;
    }


    /**
     * @param array $valid_values: only value in $valid_values be accepted
     */
    public function valid_v($valid_values)
    {
        $this->_valid_values = is_array($valid_values) ? $valid_values : array();

        return $this;
    }


    /**
     * Indicate var must be set (NULL)
     */
    public function required() {
        $this->_required = TRUE;

        return $this;
    }


    /**
     * Means empty string be treated NULL
     */
    public function strict() {
        $this->_strict = TRUE;

        return $this;
    }


    /**
     * Indicate var must be one-dimensional array
     *
     * @param int $max_num max seleted items number, 0 stand for no limit
     */
    public function multi($max_num = 0) {
        if( ! is_numeric($max_num)) {
            trigger_error(
                "Form_check error: checker method multi() expect a 'numeric' ".
                "argument, but supply '".gettype($max_num)."'",
                E_USER_ERROR
            );
        }

        $max_num = (int)$max_num;
        $this->_multiple = $max_num > 0 ? $max_num : 0;

        return $this;
    }


    /**
     * Set valid value
     */
    public function valid_val($valid_values)
    {
        $this->_valid_values = $valid_values ? (array)$valid_values : array();

        return $this;
    }


    /**
     * @param string $msg: custom defined message.
     *      Associate array. key must be in self::_error_message
     *
     */
    public function msg($msg) {
        if( ! is_string($msg)) {
            trigger_error(
                "Form_check error: checker method msg() expect a 'string' ".
                "argument, but supply '".gettype($msg)."'",
                E_USER_ERROR
            );
        }

        $this->_custom_error_msg = $msg;

        return $this;
    }


    /**
     * @param string $name: field name of the form data
     * @param mixed $value: field value. array or string.
     * 
     * @return array: a numeric of 4 elements:
     *      the 1 indicates wether the value is valid or not
     *      the 2 is the raw data of user input
     *      the 3 is the valid data or, if not valid, is NULL
     *      the 4 is error message or, if valid, is NULL
     *
     */
    public function check($value)
    {
        if ($this->_multiple !== FALSE)
        {
            return $this->_check_multi($value);
        }
        else
        {
            return $this->_check_value($value);
        }
    }


    protected function _check_multi($values) {
        $values = $values === NULL ? array() : $values;
        if( ! is_array($values))
        {
            $err_tpl = self::$_error_message['default'];
            $err_msg = self::_render_tpl($err_tpl, $this->_msg_vars);

            return array(FALSE, $values, NULL, $err_msg);
        }

        if ($this->_multiple && count($values) > $this->_multiple)
        {
            $err_tpl = self::$_error_message['multi_overflow'];
            $this->_msg_vars['max_num'] = $this->_multiple;
            $err_msg = self::_render_tpl($err_tpl, $this->_msg_vars);

            return array(FALSE, $values, NULL, $err_msg);
        }

        $valid_datas = array();
        foreach($values as $value) {
            list($is_valid, $raw, $valid_data, $err_msg) = $this->_check_value($value);
            if( ! $is_valid) {
                return array(FALSE, $values, NULL, $err_msg);
            }

            if($valid_data !== NULL)
            {
                $valid_datas[] = $valid_data;
            }
        }

        if(empty($valid_datas) && $this->_required) {
            $err_tpl = self::$_error_message['blank'];
            $err_msg = self::_render_tpl($err_tpl, $this->_msg_vars); 

            return array(FALSE, $values, NULL, $err_msg);
        }

        return array(TRUE, $values, $valid_datas, NULL);
    }


    protected function _check_value($raw)
    {
        if ($raw !== NULL && ! is_string($raw))
        {
            $err_tpl = self::$_error_message['format'];
            $err_msg = self::_render_tpl($err_tpl, $this->_msg_vars); 
            
            return array(FALSE, $raw, NULL, $err_msg);
        }

        $is_valid = FALSE;
        $valid_data = NULL;
        $err_msg = NULL;

        // trim begin and end space
        if($raw !== NULL) {
            $value = trim($raw);
        } else {
            $value = $raw;
        }

        if($value === NULL || ($value === '' && $this->_strict)) {
            if($this->_default_value !== NULL) {
                return array(TRUE, $raw, $this->_default_value, NULL);    
            } else {
                $value = NULL;
            }
        }

        // NULL means the client does not send the field's value,
        // this differs from empty string 
        if($value !== NULL) {
            list($is_valid, $data) = $this->_check($value);

            if($is_valid) {
                $valid_data = $data;
            } else {
                $err_msg = $data;
            }
        } else if($this->_required) {
            $err_msg = self::$_error_message['blank']; 
        } else {
            $is_valid = TRUE; 
            $valid_data = $value;
        }

        if ($err_msg !== NULL)
        {
            if ($this->_custom_error_msg)
            {
                $err_msg = $this->_custom_error_msg;
            }

            $err_msg = self::_render_tpl($err_msg, $this->_msg_vars);
        }

        return array($is_valid, $raw, $valid_data, $err_msg);

    }


    protected function _check_mm($value)
    {
        if($this->_max !== NULL && $value > $this->_max) {
            $this->_msg_key = 'max';
            $this->_msg_vars['max'] = $this->_max;
            $this->_msg_vars['max_len'] = $this->_max;

            return FALSE;
        }

        if($this->_min !== NULL && $value < $this->_min) {
            $this->_msg_key = 'min';
            $this->_msg_vars['min'] = $this->_min;
            $this->_msg_vars['min_len'] = $this->_min;

            return FALSE;
        }

        return TRUE;
    }


    protected function _check_valid_v($value)
    {
        if ($this->_valid_values && ! in_array($value, $this->_valid_values))
        {
            return FALSE;
        }

        return TRUE;
    }


    public static function _render_tpl($tpl, $data)
    {
        $search = array();
        $replace = array();
        foreach ($data as $key => $value)
        {
            $search[] = '{{' . $key . '}}';
            $replace[] = $value;
        }

        return str_replace($search, $replace, $tpl);
    }


    /**
     * @param mixed $value: data be checked. string 
     */
    abstract protected function _check($value);
}



/**
 * <code>
 * <?php
 *      $checker = new F_str('type');
 *      $checker->min(5)->max(100)->multi()
 *          ->required()->valide_v()->msg('{{name}} xxxx');
 *      
 *      list($is_valid, $raw, $valid_value, $err_msg = $checker->check($value);
 */
class F_str extends Input {
    protected function _check($value)
    {
        $value = @(string)$value;
        // Check function mb_strlen is exists
        if( ! function_exists('mb_strlen'))
        {
            trigger_error('extention: mbstring must active', E_USER_ERROR);
        }

        if ( ! $this->_check_valid_v($value))
        {
            $err_msg = self::$_error_message['default'];
            return array(FALSE, $err_msg);
        }

        if( ! $this->_check_mm(mb_strlen($value, $this->_encoding))) {
            $err_msg = self::$_error_message[$this->_msg_key.'_len'];

            return array(FALSE, $err_msg);
        }

        return array(TRUE, $value);

    }
}


/**
 * <code>
 * <?php
 *      $checker = new F_int('age', 12);
 *      $checker->min(5)->max(100)->multi()
 *          ->required()->valide_v()->msg('{{name}} xxxx');
 *      
 *      list($is_valid, $raw, $valid_value, $err_msg = $checker->check($value);
 */
class F_int extends Input {
    protected $_strict = TRUE;


    protected function _check($value)
    {
        if ( ! $this->_is_int($value))
        {
            $err_msg = self::$_error_message['format'];

            return array(FALSE, $err_msg);
        }

        $value = intval($value);

        if ( ! $this->_check_valid_v($value))
        {
            $err_msg = self::$_error_message['default'];

            return array(FALSE, $err_msg);
        }

        if( ! $this->_check_mm($value))
        {
            $err_msg = self::$_error_message[$this->_msg_key];

            return array(FALSE, $err_msg);
        }

        return array(TRUE, $value);
    }


    private function _is_int($value)
    {
        $pattern = '/^[-+]?\d+$/';

        return preg_match($pattern, $value) !== 0;
    }
}



/**
 * <code>
 * <?php
 *      $checker = new F_email('address');
 *      $checker->min(5)->max(100)->multi()
 *          ->required()->valide_v()->msg('{{name}} xxxx');
 *      
 *      list($is_valid, $raw, $valid_value, $err_msg = $checker->check($value);
 */
class F_email extends F_str {
    protected $_strict = TRUE;


    protected function _check($value) {
        list($is_valid, $data) = parent::_check($value);

        if( ! $is_valid) {
            return array($is_valid, $data);
        }

        return $this->_is_email($data);
    }


    protected function _is_email($value) {
        if(filter_var($value, FILTER_VALIDATE_EMAIL) === FALSE) {
            return array(FALSE, self::$_error_message['format']);
        }

        return array(TRUE, $value);
    }
}



/**
 * <code>
 * <?php
 *      $checker = new F_phone('tel');
 *      $checker->min(5)->max(100)->multi()
 *          ->required()->valide_v(array(123,123))->msg('{{name}} xxxx');
 *      
 *      list($is_valid, $raw, $valid_value, $err_msg = $checker->check($value);
 *
 * Check phone format.
 * allow format as: 
 *      028-85333333
 *      028 85333333
 *      02885333333
 *      88888888
 *
 *  when format pass, remove charactor '-' and ' '
 */
class F_phone extends F_str {
    protected $_strict = TRUE;


    protected function _check($value) {
        list($is_valid, $data) = parent::_check($value);

        if( ! $is_valid) {
            return array($is_valid, $data);
        }

        return $this->_is_phone($data);
    }


    protected function _is_phone($value) {
        $pattern = '/^(?:0\d{2,3}[-\s]?)?\d{6,8}$/';
        if(preg_match($pattern, $value) === 0) {
            return array(FALSE, self::$_error_message['format']);
        }

        $value = str_replace(
            array('-', ' '),
            array('', ''),
            $value
        );

        return array(TRUE, $value);
    }
}


/**
 * <code>
 * <?php
 *      $checker = new F_mobile('mobile');
 *      $checker->min(5)->max(100)->multi()
 *          ->required()->valide_v(array(123,123))->msg('{{name}} xxxx');
 *      
 *      list($is_valid, $raw, $valid_value, $err_msg = $checker->check($value);
 *
 */
class F_mobile extends F_str {
    protected $_strict = TRUE;


    protected function _check($value) {
        list($is_valid, $data) = parent::_check($value);

        if( ! $is_valid) {
            return array($is_valid, $data);
        }

        return $this->_is_mobile($data);
    }


    protected function _is_mobile($value) {
        $pattern = '/^1\d{10}$/';
        if(preg_match($pattern, $value) === 0) {
            return array(FALSE, self::$_error_message['format']);
        }

        return array(TRUE, $value);
    }
}


/**
 * <code>
 * <?php
 *      $checker = new F_date('begin_date', 'Y-m-d H:i:s');
 *      $checker->min(5)->max(100)->multi()
 *          ->required()->valide_v(array(123,123))->msg('{{name}} xxxx');
 *      
 *      list($is_valid, $raw, $valid_value, $err_msg = $checker->check($value);
 *
 */
class F_date extends F_str {
    protected $_date_format;
    protected $_timestamp = FALSE;

    public function __construct(
        $field_name = NULL, 
        $format='Y-m-d',
        $default_value = NULL
    ) {
        parent::__construct($field_name, $default_value);

        $this->_date_format = $format;
        $this->_strict = TRUE;
    }


    public function timestamp()
    {
        $this->_timestamp = TRUE;
        return $this;
    }


    protected function _check($value) {
        list($is_valid, $data) = parent::_check($value);

        if( ! $is_valid) {
            return array($is_valid, $data);
        }

        return $this->_validate_date($data);
    }


    protected function _validate_date($date_str) {
        try
        {
            $datetime = new Datetime($date_str);
        }
        catch (Exception $e)
        {
            return array(FALSE, self::$_error_message['format']);
        }

        $value = $datetime->format($this->_date_format);
        if ($this->_timestamp)
        {
            $value = strtotime($value);
        }

        return array(TRUE, $value);
    }
}


/**
 * <code>
 * <?php
 *      $checker = new F_qq('qq');
 *      $checker->min(5)->max(100)->multi()
 *          ->required()->valide_v(array(123,123))->msg('{{name}} xxxx');
 *      
 *      list($is_valid, $raw, $valid_value, $err_msg = $checker->check($value);
 *
 */
class F_qq extends F_str {
    protected $_strict = TRUE;


    public function __construct($field_name, $default_value = 0)
    {
        parent::__construct($field_name, $default_value);
    }


    protected function _check($value)
    {
        list($is_valid, $data) = parent::_check($value);

        if( ! $is_valid) {
            return array($is_valid, $data);
        }

        return $this->_is_qq($data);
    }


    protected function _is_qq($value)
    {
        $pattern = '/^[1-9]\d{4,20}$/';
        if(preg_match($pattern, $value) === 0) {
            return array(FALSE, self::$_error_message['format']);
        }

        return array(TRUE, $value);
    }
}


/* End of file: format_tool.php */
/* Location: application/libraries/format_tool.php */
