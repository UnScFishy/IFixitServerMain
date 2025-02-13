<?php
require_once('class.common.php');
require_once('class.sql.php');
class Profile {
    private $db_table = 'profiles'; 
    private $users_table = 'users'; 
    public $ret_val = array();

    public function __construct($action, $values) {
        $ret_val = array();
        switch ($action) {
            case 'profile-fetch':
                $ret_val = $this->fetch($values);
                break;
            case 'profile-save':
                $ret_val = $this->save($values);
                break;
            case 'profile-save-location':
                $ret_val = $this->save_location($values);
                break;
            case 'update-user-status':
                $ret_val = $this->update_user_status($values);
                break;
        }
        $this->ret_val = $ret_val;
    }

    private function fetch($v) {
        if (!isset($_SESSION['user_id'])) {
            return ['error' => 'User not logged in.'];
        }

        $userId = $_SESSION['user_id'];
        $db = new SQLConnection();
        $connection = $db->getConnection();


        $stmt = $connection->prepare("SELECT * FROM {$this->users_table} WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return ['error' => 'User information not found.'];
        }

        $stmt = $connection->prepare("SELECT * FROM {$this->db_table} WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();
        $stmt->close();

        if (!$profile) {
            return ['error' => 'Profile information not found.'];
        }

        return [
            'username' => $profile['username'] ?? '',
            'profile_pic' => $profile['profile_pic'] ?? '',
            'phone' => $user['phone'] ?? '',
            'email' => $user['emailadd'] ?? '',
            'role' => $user['role'] ?? '',
        ];
    }

    private function update_user_status($values) {
        if (!isset($_SESSION['user_id'])) {
            return ['error' => 'User not logged in.'];
        }
    
        if ($_SESSION['role'] !== Common::$role['ADMIN']) {
            return ['error' => 'Unauthorized access'];
        }
    
        if (!isset($values['user_id']) || !isset($values['status'])) {
            return ['error' => 'Invalid request data'];
        }
    
        $userId = $values['user_id'];  
        $status = $values['status'];  
    
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        $stmt = $connection->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('ii', $status, $userId);
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            $stmt->close();
            return ['success' => true, 'message' => 'User status updated successfully'];
        } else {
            $stmt->close();
            return ['error' => 'Failed to update user status or no changes made'];
        }
    }
    
    

    private function save_location($v) {
        if (!isset($_SESSION['user_id'])) {
            return ['error' => 'User not logged in.'];
        }

        $userId = $_SESSION['user_id'];
        $db = new SQLConnection();
        $connection = $db->getConnection();

        if (!isset($v['latitude'], $v['longitude']) || !is_numeric($v['latitude']) || !is_numeric($v['longitude'])) {
            return ['error' => 'Invalid latitude or longitude values.'];
        }

        $latitude = $v['latitude'];
        $longitude = $v['longitude'];

        $stmt = $connection->prepare("UPDATE {$this->db_table} SET latitude = ?, longitude = ? WHERE user_id = ?");
        $stmt->bind_param('ddi', $latitude, $longitude, $userId);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => 'Location updated successfully.'];
    }

    private function save($v) {
        if (!isset($_SESSION['user_id'])) {
            return ['error' => 'User not logged in.'];
        }
    
        $userId = $_SESSION['user_id'];
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        $error = $this->validateInput($v);
        if ($error) return $error;
    
        $userUpdates = [];
        $profileUpdates = [];
    
        if (isset($v['emailadd']) && trim($v['emailadd']) !== '') {
            $userUpdates['emailadd'] = $v['emailadd'];
        }
        if (isset($v['phone']) && trim($v['phone']) !== '') {
            $userUpdates['phone'] = $v['phone'];
        }
    
        if (isset($v['username']) && trim($v['username']) !== '') {
            $profileUpdates['username'] = $v['username'];
        }
        if (isset($v['profile_pic']) && trim($v['profile_pic']) !== '') {
            if ($this->isValidImageBase64($v['profile_pic'])) {
                $profileUpdates['profile_pic'] = $v['profile_pic'];
            } else {
                throw new Exception('Invalid profile picture format. Only PNG, JPG, and JPEG are allowed.');
            }
        }
        if (isset($v['latitude']) && isset($v['longitude'])) {
            if (is_numeric($v['latitude']) && is_numeric($v['longitude'])) {
                $profileUpdates['latitude'] = $v['latitude'];
                $profileUpdates['longitude'] = $v['longitude'];
            } else {
                return ['error' => 'Latitude and longitude must be numeric values.'];
            }
        }
    
        if (!empty($userUpdates)) {
            $userFields = implode(', ', array_map(fn($key) => "$key = ?", array_keys($userUpdates)));
            $stmt = $connection->prepare("UPDATE {$this->users_table} SET $userFields WHERE id = ?");
            $paramTypes = str_repeat('s', count($userUpdates)) . 'i';
            $params = array_merge(array_values($userUpdates), [$userId]);
            $stmt->bind_param($paramTypes, ...$params);
            $stmt->execute();
            $stmt->close();
        }
    
        if (!empty($profileUpdates)) {
            $profileFields = implode(', ', array_map(fn($key) => "$key = ?", array_keys($profileUpdates)));
            $stmt = $connection->prepare("UPDATE {$this->db_table} SET $profileFields WHERE user_id = ?");
            $paramTypes = str_repeat('s', count($profileUpdates)) . 'i';
            $params = array_merge(array_values($profileUpdates), [$userId]);
            $stmt->bind_param($paramTypes, ...$params);
            $stmt->execute();
            $stmt->close();
        }
    
        return ['success' => true, 'message' => 'Profile updated successfully.'];
    }
    


    private function prepareProfileData($v) {
        $profileData = [];

        if (isset($v['profile_pic']) && trim($v['profile_pic']) !== '') {
            if ($this->isValidImageBase64($v['profile_pic'])) {
                $profileData['profile_pic'] = $v['profile_pic'];
            } else {
                throw new Exception('Invalid profile picture format. Only PNG, JPG, and JPEG are allowed.');
            }
        }

        return $profileData;
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
    
    private function validateInput($v) {
        if (isset($v['username']) && trim($v['username']) === '') {
            return ['error' => 'Username cannot be empty.'];
        }

        if (isset($v['phone']) && trim($v['phone']) !== '') {
            if (!preg_match('/^(\+63|0)(9\d{9})$/', $v['phone'])) {
                return ['error' => 'Invalid phone number format.'];
            }
        }

        if (isset($v['emailadd']) && trim($v['emailadd']) !== '') {
            if (!filter_var($v['emailadd'], FILTER_VALIDATE_EMAIL)) {
                return ['error' => 'Invalid email address format.'];
            }
        }

        if (isset($v['latitude']) || isset($v['longitude'])) {
            if (
                !isset($v['latitude']) || !is_numeric($v['latitude']) ||
                !isset($v['longitude']) || !is_numeric($v['longitude'])
            ) {
                return ['error' => 'Latitude and longitude must be numeric values.'];
            }
        }

        return null;
    }
}

