<?php
require_once('class.common.php');
require_once('class.sql.php');

class Shop {
    private $db_table = 'shops';
    private $users_table = 'users';
    public $ret_val = array();

    public function __construct($action, $values) {
        $ret_val = array();
        switch ($action) {
            case 'shop-fetch':
                $ret_val = $this->fetch($values);
                break;
            case 'shop-save':
                $ret_val = $this->save($values);
                break;
            case 'fetch-all-shops':
                $ret_val = $this->fetch_all_shops($values);
                break;
            case 'update-shop-status':
                $ret_val = $this->update_shop_status($values);
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

        $stmt = $connection->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    
        if (!$user || ($user['role'] ?? '') !== Common::$role['OWNER']) {
            return ['error' => 'Owner information not found.'];
        }
    
        $stmt = $connection->prepare('SELECT * FROM shops WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $shop = $result->fetch_assoc();
        $stmt->close();
    
        if (!$shop) {
            return ['error' => 'Shop information not found.'];
        }

        // Shop ID
        $shopId = $shop['id'];

        // Fetch existing categories for the shop
        $stmt = $connection->prepare('SELECT category_id FROM shop_categories WHERE shop_id = ?');
        $stmt->bind_param('i', $shopId);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingCategories = [];
        while ($row = $result->fetch_assoc()) {
            $existingCategories[] = $row['category_id'];
        }
        $stmt->close();
    
        return array_merge($shop, [
            'emailadd' => $user['emailadd'] ?? '',
            'phone' => $user['phone'] ?? '',
            'role' => $user['role'] ?? '',
            'services' => $existingCategories,
            'messenger' => $shop['messenger'] ?? null
        ]);
    }
    
    

    private function save($v) {
        if (!isset($_SESSION['user_id'])) {
            return ['error' => 'User not logged in.'];
        }
    
        $userId = $_SESSION['user_id'];
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        $stmt = $connection->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    
        if (!$user || ($user['role'] ?? '') !== Common::$role['OWNER']) {
            return ['error' => 'Not authorized to update shop data.'];
        }
    
        $stmt = $connection->prepare('SELECT * FROM shops WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $shop = $result->fetch_assoc();
        $stmt->close();
    
        if (!$shop) {
            return ['error' => 'Shop record not found.'];
        }
    
        $shopId = $shop['id'];
    
        $updateFields = [];
        $updateParams = [];
        $paramTypes = '';
    
        if (isset($v['shop_name'])) {
            $updateFields[] = 'shop_name = ?';
            $updateParams[] = $v['shop_name'];
            $paramTypes .= 's';
        }
        if (isset($v['owner_name'])) {
            $updateFields[] = 'owner_name = ?';
            $updateParams[] = $v['owner_name'];
            $paramTypes .= 's';
        }
        if (isset($v['profile_pic'])) {
            $updateFields[] = 'profile_pic = ?';
            $updateParams[] = $v['profile_pic'];
            $paramTypes .= 's';
        }
        if (isset($v['business_permit'])) {
            $updateFields[] = 'business_permit = ?';
            $updateParams[] = $v['business_permit'];
            $paramTypes .= 's';
        }
        if (isset($v['latitude'])) {
            $updateFields[] = 'latitude = ?';
            $updateParams[] = $v['latitude'];
            $paramTypes .= 'd';
        }
        if (isset($v['longitude'])) {
            $updateFields[] = 'longitude = ?';
            $updateParams[] = $v['longitude'];
            $paramTypes .= 'd';
        }
        if (isset($v['qrcode'])) {
            $updateFields[] = 'qrcode = ?';
            $updateParams[] = $v['qrcode'];
            $paramTypes .= 's';
        }
        if (isset($v['messenger'])) { 
            $updateFields[] = 'messenger = ?';
            $updateParams[] = $v['messenger'];
            $paramTypes .= 's';
        }
    
        if (!empty($updateFields)) {
            $updateQuery = 'UPDATE shops SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
            $stmt = $connection->prepare($updateQuery);
    
            $paramTypes .= 'i';
            $updateParams[] = $shopId;
    
            $stmt->bind_param($paramTypes, ...$updateParams);
            $stmt->execute();
            $stmt->close();
        }
    
        if (isset($v['services']) && is_array($v['services'])) {
            $validCategories = array_values(Common::$category); 
            $categoriesToSave = array_intersect($v['services'], $validCategories); 
    
            if (!empty($categoriesToSave)) {
                $stmt = $connection->prepare('DELETE FROM shop_categories WHERE shop_id = ?');
                $stmt->bind_param('i', $shopId);
                $stmt->execute();
                $stmt->close();
    
                $stmt = $connection->prepare('INSERT INTO shop_categories (shop_id, category_id) VALUES (?, ?)');
                foreach ($categoriesToSave as $categoryId) {
                    $stmt->bind_param('ii', $shopId, $categoryId);
                    $stmt->execute();
                }
                $stmt->close();
            }
        }
    
        return ['success' => true, 'message' => 'Shop information updated successfully.'];
    }

    private function fetch_all_shops() {
        if(!isset($_SESSION['user_id'])) {
            return ['error' => 'User not logged in.'];
        }
        
        $db = new SQLConnection();
        $connection = $db->getConnection();
        $stmt = $connection->prepare('SELECT * FROM shops');
        $stmt->execute();
        $result = $stmt->get_result();
        $shops = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $shops;
    }

    private function update_shop_status($values) {
        if (!isset($_SESSION['user_id'])) {
            return ['error' => 'User not logged in.'];
        }

        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== Common::$role['ADMIN']) {
            return ['error' => 'Unauthorized access'];
        }
    
        if (!isset($values['shop_id']) || !isset($values['status'])) {
            return ['error' => 'Invalid request data'];
        }
    
        $shopId = $values['shop_id'];
        $status = $values['status'];  
    
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        $stmt = $connection->prepare('UPDATE shops SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('ii', $status, $shopId);
        $stmt->execute();
        $stmt->close();
    
        return ['success' => true, 'message' => 'Shop status updated successfully'];
    }
    
                                                                                                                                                                                                                           
    private function prepareShopData($v) {
        $shopData = [];

        if (isset($v['profile_pic']) && trim($v['profile_pic']) !== '') {
            if ($this->isValidImageBase64($v['profile_pic'])) {
                $shopData['profile_pic'] = $v['profile_pic'];
            } else {
                throw new Exception('Invalid profile picture format. Only PNG, JPG, and JPEG are allowed.');
            }
        }

        if (isset($v['business_permit']) && trim($v['business_permit']) !== '') {
            if ($this->isValidDocumentBase64($v['business_permit'])) {
                $shopData['business_permit'] = $v['business_permit'];
            } else {
                throw new Exception('Invalid business permit format. Only PDF, DOC, and DOCX are allowed.');
            }
        }

        if (isset($v['services']) && is_array($v['services'])) {
            $shopData['services'] = array_filter($v['services'], function($category) {
                return in_array($category, [
                    Common::$category['BICYCLE'],
                    Common::$category['MOTORBIKE'],
                    Common::$category['TRICYCLE'],
                    Common::$category['VEHICLE']
                ]);
            });
        }

        if (isset($v['latitude']) && isset($v['longitude'])) {
            $shopData['location'] = [
                'latitude' => (float)$v['latitude'],
                'longitude' => (float)$v['longitude'],
            ];
        }

        if (isset($v['qrcode']) && trim($v['qrcode']) !== '') {
            if ($this->isValidBase64($v['qrcode'])) {
                $shopData['qrcode'] = $v['qrcode'];
            } else {
                throw new Exception('Invalid QR code format.');
            }
        }

        if (isset($v['shop_name']) && trim($v['shop_name']) !== '') {
            $shopData['shop_name'] = $v['shop_name'];
        }

        if (isset($v['emailadd']) && trim($v['emailadd']) !== '') {
            if (filter_var($v['emailadd'], FILTER_VALIDATE_EMAIL)) {
                $shopData['emailadd'] = $v['emailadd'];
            } else {
                throw new Exception('Invalid email address.');
            }
        }
    
        if (isset($v['phone']) && trim($v['phone']) !== '') {
            if (preg_match('/^(\+63|0)(9\d{9})$/', $v['phone'])) {
                $shopData['phone'] = $v['phone'];
            } else {
                throw new Exception('Invalid phone number format.');
            }
        }
    
        if (isset($v['messenger']) && trim($v['messenger']) !== '') {
            if ($this->isValidMessengerLink($v['messenger'])) {
                $shopData['messenger'] = $v['messenger'];
            } else {
                throw new Exception('Invalid Messenger link format.');
            }
        }

        return $shopData;
    }

    private function validateInput($v) {
        if (isset($v['shop_name']) && trim($v['shop_name']) === '') {
            return ['error' => 'Shop name cannot be empty.'];
        }

        if (isset($v['emailadd']) && !filter_var($v['emailadd'], FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Invalid email address.'];
        }

        if (isset($v['phone']) && !preg_match('/^\+?\d{10,15}$/', $v['phone'])) {
            return ['error' => 'Invalid phone number format.'];
        }

        return null;
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

        $base64String = preg_replace($pattern, '', $base64String);

        $decodedData = base64_decode($base64String, true);
        if ($decodedData === false) {
            return false; 
        }

        return true; 
    }
}
