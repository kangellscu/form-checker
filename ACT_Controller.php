<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * All controllers extends from me
 */ 

/**
 * @author leikou
 *
 * $Id: ACT_Controller.php 426 2012-12-19 01:53:56Z leikou $
 */


/******************************************************************
 *
 *       SUPER base class
 *
 ******************************************************************/

// ----------------------------------------------------------------------------

/**
 * Super base Controller class, all base class need inherit from it
 */
class SUPER_Controller extends CI_Controller {
    protected $valid_output_type = array('json', 'jsonp', 'js', 'xml', 'iframe');
    /*
     * output_type指定 show_error(), show_info(), $CI->load->out()
     * 的输出格式, output_type为NULL时，表示使用普通的模板加载
     */
    protected $output_type;

    public function __construct()
    {
		parent::__construct();

        $this->load->vars('STATICS_ROOT', $this->config->item('statics_root_path'));
        $this->load->vars('PROJECT_BASE', rtrim(base_url(), '/') . '/');

        // 如果是ajax请求，response默认为json格式
        if ($this->input->is_ajax_request())
        {
            $this->set_output_type('json');
        }
    }

    /**
     * Change $CI->db
     *
     * @param string $group database config group name
     */
    public function change_db($group = 'write')
    {
        if (class_exists('CI_DB') AND isset($this->db))
        {
            $this->db->close();
            unset($this->db);
        }
        
        $this->load->database($group, FALSE, TRUE);
        return $this;
    }


    public function set_output_type($type)
    {
        if ( ! in_array($type, $this->valid_output_type))
        {
            echo "输出类型($type)不支持";
            exit;
        }

        $this->output_type = $type;

        return $this;
    }


    public function get_output_type()
    {
        return $this->output_type;
    }


    /**
     * form check
     *
     * @param array $input_form
     *          data check rules, see form_checker.php
     * @param mixed $source
     *      check data source, default $_GET|$_POST by REQUEST_METHOD
     * @param string $return_style
     *      return data structure:
     *          S: is_valid, V: valid_data, R: raw_data, E: error_message
     *
     * @return array check result
     */
    public function check($input_form, $source = NULL, $return_style = 'V')
    {
        if($return_style == '')
        {
            return NULL;
        }
        
        if($source == NULL)
        {
            $_SERVER['REQUEST_METHOD'] == 'POST'
                ? $source = &$this->input->post()
                : $source = &$this->input->get();
        }
        
        $show_error = TRUE;
        // Load Form_checker class
        $is_valid = $this->form_checker->check($source, $input_form);
        
        $return = array();
        foreach(str_split(strtoupper($return_style)) as $style) {
            switch($style)
            {
                case 'S':
                    $return[] = $is_valid;
                    break;
                case 'V':
                    $return[] = $this->form_checker->get_valid_data();
                    break;
                case 'R':
                    $return[] = $this->form_checker->get_raw_data();
                    break;
                case 'E':
                    $return[] = $this->form_checker->get_error_message();
                    $show_error = FALSE;
                    break;
            }
        }
        
        if(!$is_valid && $show_error)
        {
            show_error($this->form_checker->get_error_message());
        }
        
        return $return;
    }


    public function post_only()
    {
        $method = $this->input->server('REQUEST_METHOD'); 
        if ( ! $method || strtoupper($method) != 'POST')
        {
            show_error('method forbidden, POST available');
        }

        return $this;
    }


    protected function init_template_path($type)
    {
        $smarty_config = $this->config->item('smarty');
        $smarty_config['template_dir'] = rtrim($smarty_config['template_dir'], '/') . '/' . $type;
        $this->config->set_item('smarty', $smarty_config);
    }
}


/******************************************************************
 *
 *       ADMIN base class
 *
 ******************************************************************/

// ----------------------------------------------------------------------------


class ADMIN_NOLOGIN_Constroller extends SUPER_Controller {
    public function __construct()
    {
		parent::__construct();
        
        $this->init_template_path('admin');
    }
}


/**
 * Include AAA check and template dir setting
 */
class ADMIN_Controller extends SUPER_Controller {
    public function __construct()
    {
		parent::__construct();

        $class = $GLOBALS['class'];
        $method = $GLOBALS['method'];

        // Check AAA
        $this->load->library('AAA');
        if ( ! $this->aaa->check($class, $method))
        {       
            show_error('no permission');
        }       
        $passport_data = $this->aaa->get_data();

        // Check site
        $this->load->model('Passport_model');
        if ( ! $this->Passport_model->check_site($passport_data['site_id']))
        {
            show_error('no permission');
        }

        // Set PASSPORT global
        $this->load->vars('PASSPORT', $passport_data);

        // Set template dir
        $this->init_template_path('admin');
    }
}



/******************************************************************
 *
 *       ADMIN base class
 *
 ******************************************************************/

// -----------------------------------------------------------------------------

class USER_NOLOGIN_Controller extends SUPER_Controller {

    public function __construct()
    {
        parent::__construct();

        $this->init_template_path('user');
    }
}



class USER_Controller extends SUPER_Controller {

    public function __construct()
    {
        parent::__construct();

        $this->passport = qqlogin_check();
        if ( ! $this->passport)
        {
            show_error('请登录');
        }

        $this->init_template_path('user');
    }

}



/******************************************************************
 *
 *       API base class
 *
 ******************************************************************/

// ----------------------------------------------------------------------------

/**
 * Include AAA check and template dir setting
 */
class API_Controller extends SUPER_Controller {
    public function __construct()
    {
		parent::__construct();        
	
        // Set template dir
        $this->init_template_path('api');
    }


    public function is_logined()
    {
        $passport_config = $this->config->item('passport');
        if (is_dir($passport_config['dir']))
        {
            set_include_path($passport_config['dir']);
            require_once 'Passport.php';
        }
        else
        {
            log_message('error', '$config[passport][path] not exists');
            show_error('passport class file missed');
        }

        $uin = Passport::getUIN();
        $skey = Passport::getSkey();
        $logined = Passport::checkLogin($uin, '', $skey);
        if ($logined)
        {
            $this->load->vars('PASSPORT', array(
                'uin' => $uin,
                'skey' => $skey,
            ));
        }

        return $logined;
    }
}





/********************************************************************
 *
 *      CLI BASE class
 *
 ********************************************************************/


// -----------------------------------------------------------------------------


/**
 * CLI Controller class, all controller need execute in command line must inherit from it
 */
class CLI_Controller extends SUPER_Controller { 
    public function __construct()
    {
        parent::__construct();

        if ( ! $this->input->is_cli_request())
        {
            show_404('', FALSE);
        }

        $this->init_template_path('cli');
    }
}


/* End of file: ACT_Controller.php */
/* Location: application/core/ACT_Controller.php */
