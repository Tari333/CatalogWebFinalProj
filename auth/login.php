<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        if (login($db, $username, $password)) {
            if (isAdmin()) {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: ../buyer/dashboard.php');
            }
            exit();
        } else {
            $error = 'Username atau password salah';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
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
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px 30px;
            text-align: center;
            position: relative;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
        }
        
        .login-header i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0 0 8px 0;
        }
        
        .login-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .login-body {
            padding: 40px 30px 30px;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-floating > .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px 16px;
            height: auto;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-floating > .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-floating > label {
            padding: 12px 16px;
            color: #6c757d;
        }
        
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .demo-section {
            margin-top: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border: 1px solid #dee2e6;
        }
        
        .demo-section h6 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .demo-account {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background: white;
            border-radius: 8px;
            margin-bottom: 8px;
            border: 1px solid #e9ecef;
            font-size: 0.9rem;
        }
        
        .demo-account:last-child {
            margin-bottom: 0;
        }
        
        .demo-account .badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            color: white;
        }
        
        @media (max-width: 576px) {
            .login-card {
                margin: 10px;
                border-radius: 16px;
            }
            
            .login-header {
                padding: 30px 20px 25px;
            }
            
            .login-body {
                padding: 30px 20px 25px;
            }
            
            .login-header h1 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <i class='bx bxs-lock-alt'></i>
                <h1><?php echo SITE_NAME; ?></h1>
                <p>Selamat datang kembali</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class='bx bx-error-circle me-2'></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class='bx bx-check-circle me-2'></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username atau Email" required>
                        <label for="username">
                            <i class='bx bx-user me-2'></i>Username atau Email
                        </label>
                    </div>
                    
                    <div class="form-floating">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password">
                            <i class='bx bx-lock-alt me-2'></i>Password
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class='bx bx-log-in me-2'></i>
                        Masuk
                    </button>
                </form>
                
                <div class="demo-section">
                    <h6>
                        <i class='bx bx-info-circle'></i>
                        Akun Demo
                    </h6>
                    <div class="demo-account">
                        <div>
                            <strong>admin</strong> / admin123
                        </div>
                        <span class="badge bg-primary">Admin</span>
                    </div>
                    <div class="demo-account">
                        <div>
                            <strong>buyer</strong> / buyer123
                        </div>
                        <span class="badge bg-success">Pembeli</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>