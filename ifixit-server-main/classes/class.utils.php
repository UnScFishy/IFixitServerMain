<?php
define('UPLOADS_DIR', '../../.uploads/');

class Utils {
    public static function check_value($value, $type = 'self/null') {
        $retval = null;
        
        if($type == 'self/null') {
            $retval = ($value) ? $value : (($value == '0') ? '0' : null);
        } else if($type == '1/0') {
            $retval = ($value == 'true' || $value == '1') ? '1' : '0';
        } else if($type == 'password') {
           // $retval = password_hash($value, PASSWORD_DEFAULT);
		   $retval = crypt($value, '$2a$10$1qAz2wSx3eDc4rFv5tGb5e4jVuld5/KF2Kpy.B8D2XoC031sReFGi');
        }
        
        return $retval;
    }
    
    public static function has_key($key, $values) {
        return array_key_exists($key, $values);
    }
    
    public static function upload() {
        $filename = $_FILES['file']['tmp_name'];
        	
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $the_filename = 'file_' . time() . '.' . $ext;
        $file_path = UPLOADS_DIR . $the_filename;
        
		move_uploaded_file($filename, $file_path);
         
        $data = array(
            'name' => $the_filename,
        );
        return $data;
    }
    
    public static function is_file_exists($file_name) {
        $file_path = UPLOADS_DIR . $file_name;
        return file_exists($file_path);
    }
    
    public static function get_validate($file, $validator){
        switch($validator){
                case 'validate_image':
                    return self::validate_image($file);
        }
    }
    
    public static function validate_image($file){
        $info = getimagesize($file);
        if ($info === FALSE) {
           return false;
        }

        if ($info[2] !== IMAGETYPE_JPEG) {
           return false;
        }
        
        return array(true,$file);
    }
	
}