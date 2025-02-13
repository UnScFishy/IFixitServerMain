<?php

class Session {
    private $db_table = 'tbl_worklog';
    public $ret_val = array();
    
    public function __construct($action, $values) {
        $ret_val = array();
        switch($action) {
            case 'isLoggedIn':
                $ret_val = $this->isLoggedIn($values);
                break;
        }
        $this->ret_val = $ret_val;
    }
    
    private function isLoggedIn($v) {
		if(isset($_SESSION['user_id'])){
            return true;
        }
        return false;
    }
	
}