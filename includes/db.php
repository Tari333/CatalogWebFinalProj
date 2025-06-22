<?php
// includes/db.php
require_once 'config.php';

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $dbh;
    private $error;
    private $stmt;

    public function __construct() {
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname . ';charset=utf8mb4';
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        );

        try {
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
        } catch(PDOException $e) {
            $this->error = $e->getMessage();
            die('Database connection failed: ' . $this->error);
        }
    }

    public function query($query) {
        $this->stmt = $this->dbh->prepare($query);
    }

    public function bind($param, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }

    public function execute() {
        return $this->stmt->execute();
    }

    public function resultset() {
        $this->execute();
        return $this->stmt->fetchAll();
    }

    public function single() {
        $this->execute();
        return $this->stmt->fetch();
    }

    public function rowCount() {
        return $this->stmt->rowCount();
    }

    public function lastInsertId() {
        return $this->dbh->lastInsertId();
    }

    public function beginTransaction() {
        return $this->dbh->beginTransaction();
    }

    public function endTransaction() {
        return $this->dbh->commit();
    }

    public function cancelTransaction() {
        return $this->dbh->rollback();
    }
}

// Create database instance
$db = new Database();

// Database Schema Setup
function setupDatabase() {
    global $db;
    
    // Users table
    $db->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        role ENUM('admin', 'buyer') DEFAULT 'buyer',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $db->execute();

    // Products table
    $db->query("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        description TEXT,
        price DECIMAL(12,2) NOT NULL,
        stock INT DEFAULT 0,
        category VARCHAR(100),
        image VARCHAR(255),
        qr_code VARCHAR(255),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $db->execute();

    // Orders table
    $db->query("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        order_number VARCHAR(50) UNIQUE NOT NULL,
        total_amount DECIMAL(12,2) NOT NULL,
        status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        payment_proof VARCHAR(255),
        payment_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        notes TEXT,
        qr_code VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $db->execute();

    // Order items table
    $db->query("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(12,2) NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    $db->execute();

    // Activity logs table
    $db->query("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $db->execute();

    // User sessions table for online tracking
    $db->query("CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_id VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $db->execute();

    // Create default admin user
    $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $result = $db->single();
    
    if ($result['count'] == 0) {
        $adminPassword = md5('admin123');
        $db->query("INSERT INTO users (username, email, password, full_name, role, status) VALUES (:username, :email, :password, :full_name, :role, :status)");
        $db->bind(':username', 'admin');
        $db->bind(':email', 'admin@ecommerce.com');
        $db->bind(':password', $adminPassword);
        $db->bind(':full_name', 'Administrator');
        $db->bind(':role', 'admin');
        $db->bind(':status', 'active');
        $db->execute();
    }

    // Create demo buyer user
    $db->query("SELECT COUNT(*) as count FROM users WHERE username = 'buyer@demo.com'");
    $result = $db->single();

    if ($result['count'] == 0) {
        $buyerPassword = md5('buyer123');
        $db->query("INSERT INTO users (username, email, password, full_name, phone, address, role, status) VALUES (:username, :email, :password, :full_name, :phone, :address, :role, :status)");
        $db->bind(':username', 'buyer@demo.com');
        $db->bind(':email', 'buyer@demo.com');
        $db->bind(':password', $buyerPassword);
        $db->bind(':full_name', 'Demo Buyer');
        $db->bind(':phone', '+1234567890');
        $db->bind(':address', '123 Demo Street, Demo City, Demo State');
        $db->bind(':role', 'buyer');
        $db->bind(':status', 'active');
        $db->execute();
    }
}

// Run database setup
try {
    setupDatabase();
} catch(Exception $e) {
    error_log("Database setup error: " . $e->getMessage());
}
?>