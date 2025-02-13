<?php
require_once('class.common.php');
require_once('class.sql.php');

class Service {
    private $db_table = 'shops'; 
    public $ret_val = array();
    //private $firestore;

    public function __construct($action, $values) {
        //$this->firestore = (new FirebaseDB())->getFirestore();
        $ret_val = array();
        switch ($action) {
            case 'service-fetch-by-category':
                $ret_val = $this->fetch_by_category($values);
                break;
            case 'service-get-shop-by-id':
                $ret_val = $this->get_shop_by_id($values);
                break;
            case 'service-book-service':
                $ret_val = $this->book_service($values);
                break;
            case 'accept-or-decline-service':
                $ret_val = $this->accept_or_decline_service($values);
                break;
            case 'service-get-service-history':
                $ret_val = $this->get_service_history();
                break;
            case 'get-active-bookings':
                $ret_val = $this->get_active_bookings();
                break;
            case 'get-active-bookings-by-owner':
                $ret_val = $this->get_active_bookings_by_owner();
                break;
            case 'get-shop-location-by-owner':
                $ret_val = $this->get_shop_location_by_owner();
                break;
            case 'get-bookings-by-id':
                $ret_val = $this->get_bookings_by_id($values);
                break;
            case 'process-payment':
                $ret_val = $this->process_payment($values);
                break;
            case 'get-bookings-history':
                $ret_val = $this->get_bookings_history();
                break;
            case 'get-all-bookings-history':
                $ret_val = $this->get_all_bookings_history();
                break;
            default:
                $ret_val = ['error' => 'Invalid action'];
        }
        $this->ret_val = $ret_val;
    }

    private function fetch_by_category($v) {
        $categoryId = $v['category'] ?? null;
        $clientLat = $v['latitude'] ?? null;
        $clientLon = $v['longitude'] ?? null;
    
        if (!$categoryId || !$clientLat || !$clientLon) {
            return ['error' => 'Category, latitude, and longitude are required.'];
        }
    
        $verifiedStatus = Common::$shopStatus['VERIFIED'];  
    
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        $stmt = $connection->prepare("
            SELECT s.id, s.shop_name, s.business_permit, s.messenger, s.qrcode,s.latitude, s.longitude
            FROM {$this->db_table} s
            INNER JOIN shop_categories sc ON s.id = sc.shop_id
            WHERE sc.category_id = ? AND s.status = ?
        ");
        $stmt->bind_param('ii', $categoryId, $verifiedStatus);  
        $stmt->execute();
        $result = $stmt->get_result();
    
        $shops = [];
        while ($row = $result->fetch_assoc()) {
            $distance = $this->calculate_distance(
                $clientLat, 
                $clientLon, 
                $row['latitude'], 
                $row['longitude']
            );
    
            if ($distance <= 50) {  
                $row['distance'] = $distance;
                $shops[] = $row;
            }
        }
    
        $stmt->close();
    
        usort($shops, fn($a, $b) => $a['distance'] <=> $b['distance']);
    
        return empty($shops) 
            ? ['error' => 'No verified shops found within 50km for the specified category.'] 
            : $shops;
    }
    
    private function get_shop_by_id($v) {
        if (!isset($v['shop_id']) || trim($v['shop_id']) === '') {
            return ['error' => 'Shop ID is required.'];
        }
    
        $shopId = $v['shop_id'];
    
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        $stmt = $connection->prepare("
            SELECT s.id AS shop_id, s.shop_name, s.owner_name, s.business_permit, s.messenger, s.qrcode, s.latitude, s.longitude, s.profile_pic, u.emailadd, u.phone
            FROM {$this->db_table} s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ?
        ");
        $stmt->bind_param('i', $shopId);
        $stmt->execute();
        $result = $stmt->get_result();
        $shopInfo = $result->fetch_assoc();
        $stmt->close();
    
        if (!$shopInfo) {
            return ['error' => 'Shop information not found.'];
        }
    
        return [
            'shop_id' => $shopInfo['shop_id'],
            'shop_name' => $shopInfo['shop_name'],
            'owner_name' => $shopInfo['owner_name'],
            'business_permit' => $shopInfo['business_permit'],
            'messenger' => $shopInfo['messenger'],
            'qrcode' => $shopInfo['qrcode'],
            'latitude' => $shopInfo['latitude'],
            'longitude' => $shopInfo['longitude'],
            'profile_pic' => $shopInfo['profile_pic'],
            'email' => $shopInfo['emailadd'],
            'phone' => $shopInfo['phone']
        ];
    }

    private function book_service($v) {
        if (!isset($v['shop_id']) || trim($v['shop_id']) === '') {
            return ['error' => 'Shop ID is required.'];
        }
        if (!isset($_SESSION['user_id']) || trim($_SESSION['user_id']) === '') {
            return ['error' => 'User not logged in.'];
        }
        if (!isset($v['category']) || trim($v['category']) === '') {
            return ['error' => 'Category is required.'];
        }
        if (!isset($v['date']) || trim($v['date']) === '') {
            return ['error' => 'Booking date is required.'];
        }
    
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['date'])) {
            return ['error' => 'Invalid date format. Use YYYY-MM-DD.'];
        }
    
        $bookingDate = strtotime($v['date']);
        if ($bookingDate === false) {
            return ['error' => 'Invalid booking date provided.'];
        }
    
        $today = strtotime(date('Y-m-d'));
        if ($bookingDate < $today) {
            return ['error' => 'Booking date cannot be in the past.'];
        }

        $issue = $v['issue'] ?? null;
        if (trim($issue) === '') {
            return ['error' => 'Issue is required.'];
        }
    
        $latitude = $v['latitude'] ?? null;
        $longitude = $v['longitude'] ?? null;
    
        if ($latitude === null || $longitude === null) {
            return ['error' => 'Latitude and longitude are required.'];
        }
    
        $shopId = $v['shop_id'];
        $userId = $_SESSION['user_id'];
        $category = $v['category'];
        $date = $v['date'];
    
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        $stmt = $connection->prepare("
            SELECT 1 
            FROM shop_categories 
            WHERE shop_id = ? AND category_id = ?
        ");
        $stmt->bind_param('ii', $shopId, $category);
        $stmt->execute();
        $categoryExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    
        if (!$categoryExists) {
            return ['error' => 'The specified category is not available for this shop.'];
        }
    
        // $stmt = $connection->prepare("
        //     SELECT id 
        //     FROM bookings 
        //     WHERE shop_id = ? AND date = ? AND user_id = ?
        // ");
        // $stmt->bind_param('isi', $shopId, $date, $userId);
        // $stmt->execute();
        // $existingBooking = $stmt->get_result()->fetch_assoc();
        // $stmt->close();
    
        // if ($existingBooking) {
        //     return ['error' => 'You have already booked this shop for the selected date.'];
        // }
    
        $status = Common::$status['PENDING'];
        $createdAt = date('Y-m-d H:i:s');
        $stmt = $connection->prepare("
            INSERT INTO bookings (shop_id, user_id, category, date, status, latitude, longitude, created_at, issue) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'iiissddss', 
            $shopId, 
            $userId, 
            $category, 
            $date, 
            $status, 
            $latitude, 
            $longitude, 
            $createdAt,
            $issue
        );
        $stmt->execute();
        $stmt->close();
    
        return [
            'success' => true,
            'message' => 'Service booked successfully.',
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
    }
       
    
    private function get_active_bookings() {
        if (!isset($_SESSION['user_id']) || trim($_SESSION['user_id']) === '') {
            return ['error' => 'User ID is required.'];
        }
    
        $userId = $_SESSION['user_id'];
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        $stmt = $connection->prepare("
            SELECT 
                b.id AS booking_id, 
                b.shop_id, 
                s.shop_name, 
                s.messenger,
                p.username AS customer_name, 
                b.date, 
                b.category, 
                b.latitude, 
                b.longitude, 
                b.status,
                b.issue
            FROM bookings AS b
            INNER JOIN shops AS s ON b.shop_id = s.id
            INNER JOIN profiles AS p ON b.user_id = p.user_id
            WHERE b.user_id = ? AND b.status NOT IN (?, ?)
        ");
        $completedStatus = Common::$status['COMPLETED'];
        $declinedStatus = Common::$status['DECLINED'];
        $stmt->bind_param('iii', $userId, $completedStatus, $declinedStatus);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    
        if ($result->num_rows === 0) {
            return ['message' => 'No active bookings found.'];
        }
    
        $results = [];
        while ($row = $result->fetch_assoc()) {
            $results[] = [
                'booking_id' => $row['booking_id'],
                'shop_name' => $row['shop_name'],
                'messenger' => $row['messenger'],
                'customer_name' => $row['customer_name'], 
                'shop_id' => $row['shop_id'],
                'date' => $row['date'],
                'category' => $row['category'], 
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'status' => $row['status'],
                'issue' => $row['issue']
            ];
        }
    
        return ['bookings' => $results];
    }
    
    private function get_active_bookings_by_owner() {
        if (!isset($_SESSION['user_id']) || trim($_SESSION['user_id']) === '') {
            return ['error' => 'User ID is required.'];
        }
    
        $userId = $_SESSION['user_id'];
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        try {
            $stmt = $connection->prepare("
                SELECT id 
                FROM shops 
                WHERE user_id = ?
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
    
            $shopIds = [];
            while ($row = $result->fetch_assoc()) {
                $shopIds[] = $row['id'];
            }
    
            if (empty($shopIds)) {
                return ['message' => 'No shops found for the owner.'];
            }
    
            $placeholders = implode(',', array_fill(0, count($shopIds), '?'));
            $query = "
                SELECT 
                    b.id AS booking_id, 
                    b.shop_id, 
                    s.shop_name, 
                    s.business_permit,
                    s.messenger,
                    s.qrcode,
                    p.username AS customer_name, 
                    b.date, 
                    b.category, 
                    b.latitude, 
                    b.longitude, 
                    b.status,
                    b.issue
                FROM bookings AS b
                INNER JOIN shops AS s ON b.shop_id = s.id
                INNER JOIN profiles AS p ON b.user_id = p.user_id
                WHERE b.shop_id IN ($placeholders) AND b.status NOT IN (?, ?)
            ";
            $stmt = $connection->prepare($query);
    
            $completedStatus = Common::$status['COMPLETED'];
            $declinedStatus = Common::$status['DECLINED'];
            $params = array_merge($shopIds, [$completedStatus, $declinedStatus]);
            $paramTypes = str_repeat('i', count($shopIds)) . 'ii';
            $stmt->bind_param($paramTypes, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
    
            if ($result->num_rows === 0) {
                return ['message' => 'No active bookings found for your shops.'];
            }
    
            $results = [];
            while ($row = $result->fetch_assoc()) {
                $results[] = [
                    'booking_id' => $row['booking_id'],
                    'shop_name' => $row['shop_name'],
                    'business_permit' => $row['business_permit'],
                    'messenger' => $row['messenger'],
                    'qrcode' => $row['qrcode'],
                    'customer_name' => $row['customer_name'], 
                    'shop_id' => $row['shop_id'],
                    'date' => $row['date'],
                    'category' => $row['category'], 
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                    'status' => $row['status'],
                    'issue' => $row['issue']

                ];
            }
            return ['active_bookings' => $results];
        } catch (Exception $e) {
            error_log('Error fetching active bookings by owner: ' . $e->getMessage());
            return ['error' => 'An error occurred while fetching active bookings.'];
        }
    }
    
    private function get_shop_location_by_owner() {
        if (!isset($_SESSION['user_id']) || trim($_SESSION['user_id']) === '') {
            return ['error' => 'User ID is required.'];
        }
    
        $userId = $_SESSION['user_id'];
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        try {
            $stmt = $connection->prepare("
                SELECT shop_name, latitude, longitude 
                FROM shops 
                WHERE user_id = ?
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
    
            if ($result->num_rows === 0) {
                return ['error' => 'Shop information not found.'];
            }
    
            $shopInfo = $result->fetch_assoc();
    
            if (empty($shopInfo['shop_name']) || $shopInfo['latitude'] === null || $shopInfo['longitude'] === null) {
                return ['error' => 'Incomplete shop information found.'];
            }
    
            return [
                'success' => true,
                'data' => [
                    'shop_name' => $shopInfo['shop_name'],
                    'latitude' => $shopInfo['latitude'],
                    'longitude' => $shopInfo['longitude']
                ]
            ];
        } catch (Exception $e) {
            error_log('Database error: ' . $e->getMessage());
            return ['error' => 'An error occurred while fetching shop information.'];
        }
    }
    
    private function get_bookings_by_id($v) {
        if (!isset($v['booking_id']) || trim($v['booking_id']) === '') {
            return ['error' => 'Booking ID is required.'];
        }
    
        $bookingId = $v['booking_id'];
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        try {
            $stmt = $connection->prepare("
                SELECT 
                    b.id AS booking_id, 
                    b.shop_id, 
                    s.shop_name, 
                    s.business_permit,
                    s.messenger,
                    s.qrcode,
                    b.user_id, 
                    p.username, 
                    b.date, 
                    b.category, 
                    b.status, 
                    b.latitude, 
                    b.longitude,
                    b.issue
                FROM bookings AS b
                INNER JOIN shops AS s ON b.shop_id = s.id
                INNER JOIN profiles AS p ON b.user_id = p.user_id
                WHERE b.id = ?
            ");
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
    
            if ($result->num_rows === 0) {
                return ['error' => 'Booking not found.'];
            }
    
            $booking = $result->fetch_assoc();
    
            if (in_array($booking['status'], [Common::$status['DECLINED'], Common::$status['COMPLETED']])) {
                return ['error' => 'The booking is not available for further action.'];
            }
    
            return [
                'booking_id' => $booking['booking_id'],
                'shop_id' => $booking['shop_id'],
                'shop_name' => $booking['shop_name'],
                'business_permit' => $booking['business_permit'],
                'messenger' => $booking['messenger'],
                'qrcode' => $booking['qrcode'],
                'username' => $booking['username'], 
                'date' => $booking['date'],
                'category' => $booking['category'], 
                'status' => $booking['status'], 
                'issue' => $booking['issue'],
                'latitude' => $booking['latitude'],
                'longitude' => $booking['longitude']
            ];
        } catch (Exception $e) {
            error_log('Error fetching booking by ID: ' . $e->getMessage());
            return ['error' => 'An error occurred while fetching the booking.'];
        }
    }
    
    private function accept_or_decline_service($v) {
        if (!isset($v['booking_id']) || trim($v['booking_id']) === '') {
            return ['error' => 'Booking ID is required.'];
        }
    
        if (!isset($v['status']) || !in_array($v['status'], [
            Common::$status['ACCEPTED'], 
            Common::$status['DECLINED'], 
            Common::$status['COMPLETED']
        ])) {
            return ['error' => 'Valid status (accepted, declined, or completed) is required.'];
        }
    
        if (!isset($_SESSION['user_id']) || trim($_SESSION['user_id']) === '') {
            return ['error' => 'User is not logged in.'];
        }
    
        $userId = $_SESSION['user_id'];
        $bookingId = $v['booking_id'];
        $status = $v['status'];
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        try {
            $stmt = $connection->prepare("
                SELECT b.shop_id, s.user_id AS owner_id 
                FROM bookings AS b
                INNER JOIN shops AS s ON b.shop_id = s.id
                WHERE b.id = ?
            ");
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
    
            if ($result->num_rows === 0) {
                return ['error' => 'Booking not found.'];
            }
    
            $booking = $result->fetch_assoc();
    
            if ((int)$booking['owner_id'] !== $userId) {
                return ['error' => 'You do not have permission to perform this action.'];
            }
    
            $updatedAt = date('Y-m-d H:i:s');
            $stmt = $connection->prepare("
                UPDATE bookings 
                SET status = ?, updated_at = ? 
                WHERE id = ?
            ");
            $stmt->bind_param('isi', $status, $updatedAt, $bookingId);
            $stmt->execute();
            $stmt->close();
    
            $message = ($status == Common::$status['ACCEPTED']) 
                ? 'Booking accepted successfully.' 
                : (($status == Common::$status['DECLINED']) 
                    ? 'Booking declined.' 
                    : 'Booking marked as completed.');
    
            return [
                'success' => true,
                'message' => $message,
                'booking_id' => $bookingId,
                'status' => $status
            ];
        } catch (Exception $e) {
            error_log('Error processing booking action: ' . $e->getMessage());
            return ['error' => 'An error occurred while processing the booking action.'];
        }
    }
    
    private function process_payment($v) {
        if (!isset($v['booking_id']) || trim($v['booking_id']) === '') {
            return ['error' => 'Booking ID is required.'];
        }
    
        if (!isset($_SESSION['user_id']) || trim($_SESSION['user_id']) === '') {
            return ['error' => 'User not logged in.'];
        }
    
        $userId = $_SESSION['user_id'];
        $bookingId = $v['booking_id'];
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        try {
            $stmt = $connection->prepare("
                SELECT user_id, status 
                FROM bookings 
                WHERE id = ?
            ");
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
    
            if ($result->num_rows === 0) {
                return ['error' => 'Booking not found.'];
            }
    
            $booking = $result->fetch_assoc();
    
            if ((int)$booking['user_id'] !== $userId) {
                return ['error' => 'You are not authorized to process payment for this booking.'];
            }
    
            if ((int)$booking['status'] !== Common::$status['ACCEPTED']) {
                return ['error' => 'Payment can only be processed for bookings with ACCEPTED status.'];
            }
    
            $updatedAt = date('Y-m-d H:i:s');
            $newStatus = Common::$status['PAYMENT_PROCESSING'];
            $stmt = $connection->prepare("
                UPDATE bookings 
                SET status = ?, updated_at = ? 
                WHERE id = ?
            ");
            $stmt->bind_param('isi', $newStatus, $updatedAt, $bookingId);
            $stmt->execute();
            $stmt->close();
    
            return [
                'success' => true,
                'message' => 'Payment processed successfully.',
                'booking_id' => $bookingId,
                'status' => $newStatus
            ];
        } catch (Exception $e) {
            error_log('Error processing payment: ' . $e->getMessage());
            return ['error' => 'An error occurred while processing the payment.'];
        }
    }
    
    private function get_service_history() {
        if (!isset($_SESSION['user_id']) || trim($_SESSION['user_id']) === '') {
            return ['error' => 'User is not logged in.'];
        }
    
        $userId = $_SESSION['user_id'];
        $today = date('Y-m-d');
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        try {
            $stmt = $connection->prepare("
                SELECT 
                    b.shop_id, 
                    b.date, 
                    s.shop_name 
                FROM bookings AS b
                INNER JOIN shops AS s ON b.shop_id = s.id
                WHERE b.user_id = ? AND b.date < ?
            ");
            $stmt->bind_param('is', $userId, $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
    
            if ($result->num_rows === 0) {
                return ['error' => 'No service history found for this user.'];
            }
    
            $history = [];
            while ($row = $result->fetch_assoc()) {
                $history[] = [
                    'shop_id' => $row['shop_id'],
                    'name' => $row['shop_name'] ?? 'Unknown Shop',
                    'time' => $row['date'] ?? 'N/A'
                ];
            }
    
            return $history;
        } catch (Exception $e) {
            error_log('Error fetching service history: ' . $e->getMessage());
            return ['error' => 'An error occurred while fetching service history.'];
        }
    }

    private function get_bookings_history() {
        if (!isset($_SESSION['user_id']) || trim($_SESSION['user_id']) === '') {
            return ['error' => 'User is not logged in.'];
        }
    
        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['role'] ?? null;
    
        if ($userRole === null) {
            return ['error' => 'User role is not defined.'];
        }
    
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        try {
            $query = "";
            $params = [];
            $paramTypes = "";
    
            if ($userRole === Common::$role['OWNER']) {
                $query = "
                    SELECT 
                        b.id AS booking_id, 
                        b.shop_id, 
                        s.shop_name, 
                        s.business_permit,
                        s.messenger,
                        s.qrcode,
                        b.user_id, 
                        p.username AS customer_name, 
                        b.date, 
                        b.status, 
                        b.category, 
                        b.latitude, 
                        b.longitude 
                    FROM bookings AS b
                    INNER JOIN shops AS s ON b.shop_id = s.id
                    INNER JOIN profiles AS p ON b.user_id = p.user_id
                    WHERE s.user_id = ? AND b.status = ?
                ";
                $params = [$userId, Common::$status['COMPLETED']];
                $paramTypes = "ii";
            } elseif ($userRole === Common::$role['CUSTOMER']) {
                $query = "
                    SELECT 
                        b.id AS booking_id, 
                        b.shop_id, 
                        s.shop_name, 
                        s.business_permit,
                        s.messenger,
                        s.qrcode,
                        b.user_id, 
                        b.date, 
                        b.status, 
                        b.category, 
                        b.latitude, 
                        b.longitude 
                    FROM bookings AS b
                    INNER JOIN shops AS s ON b.shop_id = s.id
                    WHERE b.user_id = ? AND b.status = ?
                ";
                $params = [$userId, Common::$status['COMPLETED']];
                $paramTypes = "ii";
            } else {
                return ['error' => 'Invalid user role.'];
            }
    
            $stmt = $connection->prepare($query);
            $stmt->bind_param($paramTypes, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
    
            $history = [];
            while ($row = $result->fetch_assoc()) {
                $history[] = [
                    'booking_id' => $row['booking_id'],
                    'shop_id' => $row['shop_id'],
                    'shop_name' => $row['shop_name'] ?? 'Unknown Shop',
                    'business_permit' => $row['business_permit'],
                    'messenger' => $row['messenger'],
                    'qrcode' => $row['qrcode'],
                    'customer_name' => $row['customer_name'] ?? 'Unknown Customer',
                    'date' => $row['date'] ?? 'N/A',
                    'user_id' => $row['user_id'],
                    'status' => $row['status'],
                    'category' => $row['category'],
                    'latitude' => $row['latitude'] ?? 'N/A',
                    'longitude' => $row['longitude'] ?? 'N/A',
                ];
            }
    
            return $history;
    
        } catch (Exception $e) {
            error_log('Error fetching booking history: ' . $e->getMessage());
            return ['error' => 'An error occurred while fetching booking history.'];
        }
    }

    private function get_all_bookings_history() {
        $db = new SQLConnection();
        $connection = $db->getConnection();
    
        if (!isset($_SESSION['user_id']) || trim($_SESSION['user_id']) === '') {
            return ['error' => 'User is not logged in.'];
        }
    
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== Common::$role['ADMIN']) {
            return ['error' => 'Unauthorized access.'];
        }
    
        $stmt = $connection->prepare("
            SELECT 
                b.id AS booking_id, 
                b.shop_id, 
                s.shop_name, 
                s.business_permit,
                s.messenger,
                s.qrcode,
                b.user_id, 
                p.username AS customer_name, 
                b.date, 
                b.status, 
                b.category,
                sc.category_id AS service_type,     
                b.latitude, 
                b.longitude 
            FROM bookings AS b
            INNER JOIN shops AS s ON b.shop_id = s.id
            INNER JOIN profiles AS p ON b.user_id = p.user_id
            LEFT JOIN shop_categories AS sc       
                ON b.category = sc.category_id         
            WHERE b.status = ?
        ");
    
        $completedStatus = Common::$status['COMPLETED'];
        $stmt->bind_param('i', $completedStatus);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'booking_id'     => $row['booking_id'],
                'shop_id'        => $row['shop_id'],
                'shop_name'      => $row['shop_name']       ?? 'Unknown Shop',
                'business_permit'=> $row['business_permit'],
                'messenger'      => $row['messenger'],
                'qrcode'         => $row['qrcode'],
                'customer_name'  => $row['customer_name']   ?? 'Unknown Customer',
                'date'           => $row['date']            ?? 'N/A',
                'user_id'        => $row['user_id'],
                'status'         => $row['status'],
                'service_type'   => $row['service_type']    ?? 'N/A',  
                'latitude'       => $row['latitude']        ?? 'N/A',
                'longitude'      => $row['longitude']       ?? 'N/A',
            ];
        }
    
        return $history;
    }
    

    private function calculate_distance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; 

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
