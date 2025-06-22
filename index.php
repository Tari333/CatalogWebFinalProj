<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Clean expired sessions
cleanExpiredSessions($db);

// Redirect based on user role
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/dashboard.php');
    } elseif (isBuyer()) {
        header('Location: buyer/products.php');
    }
    exit();
}

// Redirect to login if not logged in
header('Location: auth/login.php');
exit();
?>