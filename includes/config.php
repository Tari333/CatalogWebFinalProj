<?php
// includes/config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecommerce_rad');

define('SITE_URL', 'http://localhost/ecommerce_rad');
define('SITE_NAME', 'E-Commerce');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'marcophilips73@gmail.com');
define('SMTP_PASS', 'ldkf kpxy akzh sxbj');
define('FROM_EMAIL', 'marcophilips73@gmail.com');
define('FROM_NAME', 'E-Commerce RAD System');

// File Upload Settings
define('UPLOAD_PATH', 'assets/images/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Pagination
define('RECORDS_PER_PAGE', 10);

// Session Settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

session_start();
?>