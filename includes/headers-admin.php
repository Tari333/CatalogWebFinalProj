<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Method 1: Using filename-based approach (Recommended)
function isActivePage($page) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return ($currentPage == $page) ? 'active' : '';
}

// Helper function to generate URLs
function actionUrl($controller, $action) {
    return "index.php?controller=" . $controller . "&action=" . $action;
}

// Get page title from variable or default
$pageTitle = $pageTitle ?? 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Admin - <?php echo SITE_NAME; ?></title>
    
    <link rel="stylesheet" href="../libs/bootstrap/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css" />
    <!-- Third-party CSS -->
    <link rel="stylesheet" href="../libs/dataTables/datatables.min.css" />
    <link rel="stylesheet" href="../libs/select2/dist/css/select2.min.css" />
    <link rel="stylesheet" href="../libs/sweetalert2/dist/sweetalert2.min.css" />
    <link rel="stylesheet" href="../libs/highcharts/css/highcharts.css" />
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class='bx bx-cube-alt'></i>
            </div>
            <div class="brand"><?php echo SITE_NAME; ?></div>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo isActivePage('dashboard.php'); ?>">
                        <i class='bx bx-home-alt'></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="products.php" class="nav-link <?php echo isActivePage('products.php'); ?>">
                        <i class='bx bx-package'></i>
                        <span>Produk</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="orders.php" class="nav-link <?php echo isActivePage('orders.php'); ?>">
                        <i class='bx bx-receipt'></i>
                        <span>Pesanan</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <div class="nav-item">
                    <a href="users.php" class="nav-link <?php echo isActivePage('users.php'); ?>">
                        <i class='bx bx-group'></i>
                        <span>Users</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="activity-logs.php" class="nav-link <?php echo isActivePage('activity-logs.php'); ?>">
                        <i class='bx bx-history'></i>
                        <span>Log Aktivitas</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-item">
                    <a href="../auth/logout.php" class="nav-link <?php echo isActivePage('logout.php'); ?>">
                        <i class='bx bx-log-out'></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
            <div class="nav-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class='bx bx-menu'></i>
                </button>
                
                <?php /* 
                <form class="search-form">
                    <input type="text" class="search-input" placeholder="Search anything...">
                    <i class='bx bx-search search-icon'></i>
                </form>
                */ ?>
            </div>
            
            <div class="nav-right">
                <?php /* 
                <button class="notification-btn">
                    <i class='bx bx-bell'></i>
                    <div class="notification-badge"></div>
                </button>
                */ ?>
                
                <a href="profile.php" class="user-profile text-decoration-none">
                    <div class="user-avatar">
                        <?php
                            $firstLetter = substr($current_user['full_name'], 0, 1);
                        ?>
                        <span class="avatar-letter"><?php echo strtoupper($firstLetter); ?></span>
                    </div>
                    <div class="d-none d-md-block">
                        <div style="font-size: 0.875rem; font-weight: 500; color: var(--dark);">
                            <?php echo htmlspecialchars($_SESSION['FullName'] ?? 'Admin User'); ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--gray-600);">
                            <?php echo htmlspecialchars($_SESSION['Role'] ?? 'Administrator'); ?>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Page content will be included here -->