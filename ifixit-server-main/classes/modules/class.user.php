<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once('class.common.php');
require_once('class.sql.php');

class User {
    private $db_table = 'users';
    public $ret_val = array();

    public function __construct($action, $values) {
        $ret_val = array();
        switch($action) {
            case 'login':
                $ret_val = $this->login($values);
                break;
            case 'logout':
                $ret_val = $this->logout($values);
                break;
            case 'user-save':
                $ret_val = $this->save($values);
                break;
            case 'user-check-login':
                $ret_val = $this->user_check_login($values);
                break;
            case 'fetch-all-users':
                $ret_val = $this->fetch_all_users($values);
                break;
        }
        $this->ret_val = $ret_val;
    }

    private function logout($v) {
        unset($_SESSION['user_id']);
        return true;
    }

    private function user_check_login($v) {
        if (!isset($_SESSION['user_id'])) {
            return ['logged_in' => false, 'error' => null];
        }
        return ['logged_in' => $_SESSION['user_id'], 'error' => null];
    }

    private function login($v) {
        if (!isset($v['emailadd']) || !filter_var($v['emailadd'], FILTER_VALIDATE_EMAIL)) {
            return ['logged_in' => false, 'error' => 'Invalid or missing email address.'];
        }
    
        if (!isset($v['password']) || trim($v['password']) === '') {
            return ['logged_in' => false, 'error' => 'Password is required.'];
        }
    
        $email = $v['emailadd'];
        $password = $v['password'];
    
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        $stmt = $connection->prepare('SELECT u.*, s.status AS shop_status FROM users u LEFT JOIN shops s ON u.id = s.user_id WHERE u.emailadd = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    
        if ($user && password_verify($password, $user['password'])) {
            if ($user['role'] === Common::$role['CUSTOMER'] && $user['status'] === 2 ) {
                return ['logged_in' => false, 'error' => 'Your account has been deactivated.'];
            }

            if ($user['role'] === Common::$role['CUSTOMER'] && $user['status'] === 0 ) {
                return ['logged_in' => false, 'error' => 'Your account has not been approved.'];
            }
    
            if ($user['role'] === Common::$role['OWNER']) {
                if (!isset($user['shop_status'])) {
                    return ['logged_in' => false, 'error' => 'Shop information not found.'];
                }
                if ($user['shop_status'] === 0) {
                    return ['logged_in' => false, 'error' => 'Your shop has not been approved.'];
                }
                if ($user['shop_status'] === 2 || $user['shop_status'] === 3) {
                    return ['logged_in' => false, 'error' => 'Your shop has been denied or deactivated.'];
                }
            }
    
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
    
            return [
                'logged_in' => true,
                'record' => [
                    'role' => $user['role'],
                ]
            ];
        }
    
        return ['logged_in' => false, 'error' => 'Invalid login credentials.'];
    }
    

    private function save($v) {
        $role = $v['role'] ?? Common::$role['CUSTOMER'];
    
        $validRoles = [Common::$role['CUSTOMER'], Common::$role['OWNER']];
        if (!in_array($role, $validRoles, true)) {
            return ['error' => 'Invalid role specified.'];
        }
    
        if (!isset($v['emailadd']) || !filter_var($v['emailadd'], FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Invalid or missing email address.'];
        }
    
        if (!isset($v['phone']) || !preg_match('/^(\+63|0)(9\d{9})$/', $v['phone'])) {
            return ['error' => 'Invalid or missing phone number.'];
        }
    
        if (!isset($v['password']) || strlen(trim($v['password'])) < 6) {
            return ['error' => 'Password is required and must be at least 6 characters long.'];
        }

        if ($role === Common::$role['OWNER']) {
            if (empty($v['business_permit']) || !$this->isValidDocumentBase64($v['business_permit'])) {
                return ['error' => 'Invalid business permit format. Only PDF, DOC, and DOCX are allowed.'];
            }

            if (empty($v['qrcode']) || !$this->isValidImageBase64($v['qrcode'])) {
                return ['error' => 'QR code is required for owners.'];
            }

            if (empty(trim($v['messenger'])) || !filter_var(trim($v['messenger']), FILTER_VALIDATE_URL)) {
                return ['error' => 'Valid Messenger link is required for owners.'];
            }            
        }
    
        $hashed_password = password_hash($v['password'], PASSWORD_BCRYPT);
    
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        $stmt = $connection->prepare('SELECT id FROM users WHERE emailadd = ?');
        $stmt->bind_param('s', $v['emailadd']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            return ['error' => 'Email already exists.'];
        }
        $stmt->close();
    
        $stmt = $connection->prepare('INSERT INTO users (emailadd, phone, role, password) VALUES (?, ?, ?, ?)');
       //$role = Common::$role['CUSTOMER'];
        $stmt->bind_param('ssis', $v['emailadd'], $v['phone'], $role, $hashed_password);
        $stmt->execute();
        $user_id = $stmt->insert_id;
        $stmt->close();
    
        if ($role === Common::$role['CUSTOMER']) {
            $stmt = $connection->prepare('INSERT INTO profiles (user_id, username, profile_pic) VALUES (?, ?, ?)');
            $username = $v['username'] ?? null;
            $profile_pic = $v['profile_pic'] ?? Common::profile_pic;
            $stmt->bind_param('iss', $user_id, $username, $profile_pic);
            $stmt->execute();
            $stmt->close();
        }
    
        if ($role === Common::$role['OWNER']) {
            $stmt = $connection->prepare('INSERT INTO shops (user_id, shop_name, owner_name, profile_pic, business_permit, latitude, longitude, qrcode, services, messenger) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $shop_name = $v['shop_name'] ?? null;
            $owner_name = $v['owner_name'] ?? null;
            $profile_pic = $v['profile_pic'] ?? Common::profile_pic;
            $business_permit = $v['business_permit'] ?? null;
            $latitude = $v['latitude'] ?? 0.0;
            $longitude = $v['longitude'] ?? 0.0;
            $qrcode = $v['qrcode'] ?? null;
            $services = isset($v['services']) ? json_encode($v['services']) : null;
            $messenger = $v['messenger'] ?? null;
            $stmt->bind_param('issssddsss', $user_id, $shop_name, $owner_name, $profile_pic, $business_permit, $latitude, $longitude, $qrcode, $services, $messenger);
            $stmt->execute();
            $stmt->close();
        }
    
        return ['inserted' => true, 'emailadd' => $v['emailadd']];
    }

    private function fetch_all_users($v) {
        $db = new SQLConnection();
        $connection = $db->getConnection();
        
        $stmt = $connection->prepare('
            SELECT u.id, u.emailadd, u.role, u.status
            FROM users u
            WHERE u.role IN (?, ?)
        ');
        
        $customerRole = Common::$role['CUSTOMER'];
        $ownerRole = Common::$role['OWNER'];  
    
        $stmt->bind_param('ss', $customerRole, $ownerRole);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    private function isValidImageBase64($base64String) {
        $pattern = '/^data:image\/(jpeg|png|jpg);base64,/';
        if (!preg_match($pattern, $base64String, $matches)) {
            return false; 
        }

        $mimeType = $matches[1];

        $base64String = preg_replace($pattern, '', $base64String);

        $decodedData = base64_decode($base64String, true);
        if ($decodedData === false) {
            return false; 
        }

        $allowedMimeTypes = ['jpeg', 'png', 'jpg'];
        return in_array($mimeType, $allowedMimeTypes);
    }

    private function isValidDocumentBase64($base64String) {
        $pattern = '/^data:(application\/(pdf|msword|vnd\.openxmlformats-officedocument\.wordprocessingml\.document));base64,/';
        
        if (!preg_match($pattern, $base64String, $matches)) {
            return false; 
        }
        
        $base64Data = preg_replace($pattern, '', $base64String);
        
        $decodedData = base64_decode($base64Data, true);
        if ($decodedData === false) {
            return false; 
        }
    
        return true; 
    }
    
    
}
