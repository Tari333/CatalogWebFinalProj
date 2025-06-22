<?php
// includes/auth.php
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

// Check if user is buyer
function isBuyer() {
    return isLoggedIn() && $_SESSION['role'] === 'buyer';
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit();
    }
}

// Require admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit();
    }
}

// Require buyer
function requireBuyer() {
    requireLogin();
    if (!isBuyer()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit();
    }
}

// Login user
function login($db, $username, $password) {
    $db->query("SELECT * FROM users WHERE (username = :username OR email = :usernamee) AND status = 'active'");
    $db->bind(':username', $username);
    $db->bind(':usernamee', $username);
    $user = $db->single();
    
    if ($user && md5($password) === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        
        // Update user session
        updateUserSession($db, $user['id'], session_id());
        
        // Log activity
        logActivity($db, $user['id'], 'LOGIN', 'User logged in');
        
        // Send login notification email
        $subject = 'Login Berhasil - ' . SITE_NAME;
        $body = "
        <h2>Halo {$user['full_name']},</h2>
        <p>Anda telah berhasil login ke sistem pada " . date('d/m/Y H:i:s') . "</p>
        <p>Jika ini bukan Anda, segera hubungi administrator.</p>
        <p>Terima kasih,<br>" . SITE_NAME . "</p>
        ";
        sendEmail($user['email'], $subject, $body);
        
        return true;
    }
    
    return false;
}

// Logout user
function logout($db) {
    if (isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
        
        // Delete user session
        $db->query("DELETE FROM user_sessions WHERE user_id = :user_id");
        $db->bind(':user_id', $user_id);
        $db->execute();
        
        // Log activity
        logActivity($db, $user_id, 'LOGOUT', 'User logged out');
        
        // Destroy session
        session_destroy();
    }
    
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

// Get current user
function getCurrentUser($db) {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db->query("SELECT * FROM users WHERE id = :id");
    $db->bind(':id', $_SESSION['user_id']);
    return $db->single();
}

// Update last activity
function updateLastActivity($db) {
    if (isLoggedIn()) {
        $db->query("UPDATE user_sessions SET last_activity = NOW() WHERE user_id = :user_id");
        $db->bind(':user_id', $_SESSION['user_id']);
        $db->execute();
    }
}
?>