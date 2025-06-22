<?php
// includes/functions.php
require_once 'config.php';
require_once '../libs/phpmailer/PHPMailer/src/Exception.php';
require_once '../libs/phpmailer/PHPMailer/src/PHPMailer.php';
require_once '../libs/phpmailer/PHPMailer/src/SMTP.php';
require_once '../libs/phpqrcode/qrlib.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Generate random string
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

// Generate order number
function generateOrderNumber() {
    return 'ORD' . date('YmdHis') . rand(100, 999);
}

// Generate QR Code
function generateQRCode($data, $filename, $size = 4) {
    $qr_path = '../assets/images/qrcodes/';
    if (!file_exists($qr_path)) {
        mkdir($qr_path, 0777, true);
    }
    
    $file = $qr_path . $filename . '.png';
    QRcode::png($data, $file, QR_ECLEVEL_L, $size);
    return 'assets/images/qrcodes/' . $filename . '.png';
}

// Send email notification
function sendEmail($to, $subject, $body, $isHTML = true) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        return false;
    }
}

// Log activity
function logActivity($db, $user_id, $action, $description = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $db->query("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (:user_id, :action, :description, :ip, :user_agent)");
    $db->bind(':user_id', $user_id);
    $db->bind(':action', $action);
    $db->bind(':description', $description);
    $db->bind(':ip', $ip);
    $db->bind(':user_agent', $user_agent);
    $db->execute();
}

// Update user session
function updateUserSession($db, $user_id, $session_id) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Delete old sessions for this user
    $db->query("DELETE FROM user_sessions WHERE user_id = :user_id");
    $db->bind(':user_id', $user_id);
    $db->execute();
    
    // Insert new session
    $db->query("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) VALUES (:user_id, :session_id, :ip, :user_agent)");
    $db->bind(':user_id', $user_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':ip', $ip);
    $db->bind(':user_agent', $user_agent);
    $db->execute();
}

// Check if user is online (active in last 5 minutes)
function isUserOnline($db, $user_id) {
    $db->query("SELECT COUNT(*) as count FROM user_sessions WHERE user_id = :user_id AND last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $db->bind(':user_id', $user_id);
    $result = $db->single();
    return $result['count'] > 0;
}

// Get online users count
function getOnlineUsersCount($db) {
    $db->query("SELECT COUNT(DISTINCT user_id) as count FROM user_sessions WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $result = $db->single();
    return $result['count'];
}

// Format currency
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Format date
function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Upload file
function uploadFile($file, $path, $allowed_types = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
    
    $filename = $file['name'];
    $tmp_name = $file['tmp_name'];
    $size = $file['size'];
    $error = $file['error'];
    
    if ($error !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if ($size > MAX_FILE_SIZE) {
        return false;
    }
    
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types)) {
        return false;
    }
    
    $new_filename = uniqid() . '.' . $ext;
    $destination = $path . $new_filename;
    
    if (move_uploaded_file($tmp_name, $destination)) {
        return $new_filename;
    }
    
    return false;
}

// Generate profile avatar
function generateAvatar($name, $size = 50) {
    $initials = strtoupper(substr($name, 0, 1));
    $colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F'];
    $color = $colors[ord($initials) % count($colors)];
    
    return "data:image/svg+xml;base64," . base64_encode("
    <svg width='{$size}' height='{$size}' xmlns='http://www.w3.org/2000/svg'>
        <circle cx='" . ($size/2) . "' cy='" . ($size/2) . "' r='" . ($size/2) . "' fill='{$color}'/>
        <text x='50%' y='50%' text-anchor='middle' dy='0.35em' font-family='Arial, sans-serif' font-size='" . ($size/2) . "' fill='white'>{$initials}</text>
    </svg>");
}

// Pagination
// Pagination function with proper type checking
function paginate($db, $query, $page = 1, $records_per_page = null) {
    // Set default if RECORDS_PER_PAGE constant exists, otherwise use 10
    if ($records_per_page === null) {
        $records_per_page = defined('RECORDS_PER_PAGE') ? RECORDS_PER_PAGE : 10;
    }
    
    // Ensure parameters are integers
    $page = (int) $page;
    $records_per_page = (int) $records_per_page;
    
    // Validate parameters
    if ($page < 1) $page = 1;
    if ($records_per_page < 1) $records_per_page = 10;
    
    $offset = ($page - 1) * $records_per_page;
    
    try {
        // Get total records
        $count_query = preg_replace('/SELECT .* FROM/', 'SELECT COUNT(*) as total FROM', $query);
        $db->query($count_query);
        $result = $db->single();
        $total_records = isset($result['total']) ? (int) $result['total'] : 0;
        
        // Get paginated records
        $paginated_query = $query . " LIMIT {$records_per_page} OFFSET {$offset}";
        $db->query($paginated_query);
        $records = $db->resultset();
        
        $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;
        
        return [
            'records' => $records,
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'records_per_page' => $records_per_page
        ];
        
    } catch (Exception $e) {
        // Log error and return empty result
        error_log("Pagination error: " . $e->getMessage());
        return [
            'records' => [],
            'total_records' => 0,
            'total_pages' => 1,
            'current_page' => 1,
            'records_per_page' => $records_per_page
        ];
    }
}

// Clean expired sessions
function cleanExpiredSessions($db) {
    $db->query("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $db->execute();
}
?>