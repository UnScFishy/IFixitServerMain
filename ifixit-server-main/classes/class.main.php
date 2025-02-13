<?php
session_start();
require_once('class.utils.php');
require_once('class.dbhelper.php');
require_once('class.common.php');

if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
    $_POST = json_decode(file_get_contents("php://input"), true);
}

$action = '';
$values = array();
if(Utils::has_key('action', $_POST)) $action = $_POST['action'];
if(Utils::has_key('values', $_POST)) $values = $_POST['values'];
if(!empty($_FILES) > 0){
    if(Utils::has_key('file_picture', $_FILES)){
        $action = "uploadPicture";
    } else{
       $action = "uploadLogo";
    }
    $values = $_FILES;
}
new Main($action, $values);

class Main {
    public function __construct($action, $values) {
        $data = array();
        switch($action) {
            case 'login':
            case 'logout':
            case 'user-save':
			case 'user-remove':
			case 'user-check-login':
            case 'fetch-all-users':
                require_once('modules/class.user.php');
                $user = new User($action, $values);
                $data = $user->ret_val;
                break;
            case 'profile-fetch':
            case 'profile-save':
            case 'profile-save-location':
            case 'update-user-status':
                require_once('modules/class.profile.php');
                $profile = new Profile($action, $values);
                $data = $profile->ret_val;
                break;
            case 'shop-fetch':
            case 'shop-save':
            case 'fetch-all-shops':
            case 'update-shop-status':
                require_once('modules/class.shop.php');
                $shop = new Shop($action, $values);
                $data = $shop->ret_val;
                break;
            case 'service-fetch-by-category':
            case 'service-get-shop-by-id':
            case 'service-book-service':
            case 'accept-or-decline-service':
            case 'service-get-service-history':
            case 'get-active-bookings':
            case 'get-active-bookings-by-owner':
            case 'get-shop-location-by-owner':
            case 'get-bookings-by-id':
            case 'process-payment':
            case 'get-bookings-history':
            case 'get-all-bookings-history':
                require_once('modules/class.service.php');
                $service = new Service($action, $values);
                $data = $service->ret_val;
                break;
            case 'isLoggedIn':
                require_once('modules/class.session.php');
                $ss = new Session($action, $values);
                $data = $ss->ret_val;
                break;
        }

        if(is_string($data)) echo $data;
        else echo json_encode($data);
    }
}