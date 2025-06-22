<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$field = sanitize($_POST['field']);
$value = sanitize($_POST['value']);
$user_id = intval($_POST['user_id']);

if (!in_array($field, ['username', 'email'])) {
    echo json_encode(['error' => 'Invalid field']);
    exit();
}

if (empty($value)) {
    echo json_encode(['available' => true]);
    exit();
}

try {
    $db->query("SELECT COUNT(*) as count FROM users WHERE $field = :value AND id != :user_id");
    $db->bind(':value', $value);
    $db->bind(':user_id', $user_id);
    $result = $db->single();
    
    echo json_encode(['available' => $result['count'] == 0]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
}
?>