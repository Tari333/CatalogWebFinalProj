<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
updateLastActivity($db);

if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit();
}

$user_id = intval($_GET['id']);

// Get user details
$db->query("SELECT * FROM users WHERE id = :id");
$db->bind(':id', $user_id);
$user = $db->single();

if (!$user) {
    header('Location: users.php?error=Pengguna tidak ditemukan');
    exit();
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_user') {
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $full_name = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $role = sanitize($_POST['role']);
        $status = sanitize($_POST['status']);
        
        // Validate input
        if (empty($username) || empty($email) || empty($full_name)) {
            echo json_encode(['success' => false, 'message' => 'Username, email, dan nama lengkap harus diisi']);
            exit();
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
            exit();
        }
        
        try {
            $db->beginTransaction();
            
            // Check if username or email already exists for another user
            $db->query("SELECT COUNT(*) as count FROM users WHERE (username = :username OR email = :email) AND id != :id");
            $db->bind(':username', $username);
            $db->bind(':email', $email);
            $db->bind(':id', $user_id);
            $result = $db->single();
            
            if ($result['count'] > 0) {
                throw new Exception('Username atau email sudah digunakan oleh pengguna lain');
            }
            
            // Update user
            $db->query("UPDATE users SET username = :username, email = :email, full_name = :full_name, phone = :phone, address = :address, role = :role, status = :status WHERE id = :id");
            $db->bind(':username', $username);
            $db->bind(':email', $email);
            $db->bind(':full_name', $full_name);
            $db->bind(':phone', $phone);
            $db->bind(':address', $address);
            $db->bind(':role', $role);
            $db->bind(':status', $status);
            $db->bind(':id', $user_id);
            $db->execute();
            
            $db->endTransaction();
            
            logActivity($db, $_SESSION['user_id'], 'UPDATE_USER', "Updated user: $username");
            echo json_encode(['success' => true, 'message' => 'Pengguna berhasil diperbarui']);
        } catch (Exception $e) {
            $db->cancelTransaction();
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui pengguna: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_POST['action'] === 'reset_password') {
        $new_password = $_POST['new_password'];
        
        if (strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter']);
            exit();
        }
        
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $db->query("UPDATE users SET password = :password WHERE id = :id");
            $db->bind(':password', $hashed_password);
            $db->bind(':id', $user_id);
            $db->execute();
            
            logActivity($db, $_SESSION['user_id'], 'RESET_PASSWORD', "Reset password for user ID: $user_id");
            echo json_encode(['success' => true, 'message' => 'Password berhasil direset']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal mereset password: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_POST['action'] === 'get_user') {
        echo json_encode(['success' => true, 'data' => $user]);
        exit();
    }
}

$current_user = getCurrentUser($db);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengguna - <?php echo SITE_NAME; ?></title>
</head>
<body>

<style>
    .form-container {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }

    .dashboard-container {
        padding: 10px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .page-title h1 {
        font-size: 2rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
    }

    .current-time {
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 500;
    }

    .form-label {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
    }

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #e3e6f0;
        padding: 12px 15px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: #4e73df;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 8px;
        padding: 12px 30px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
        border-radius: 8px;
        padding: 12px 30px;
        font-weight: 600;
    }

    .form-section {
        margin-bottom: 30px;
        padding-bottom: 25px;
        border-bottom: 1px solid #e3e6f0;
    }

    .form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }

    .section-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
    }

    .section-title i {
        margin-right: 10px;
        color: #4e73df;
    }

    .user-id-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
        display: inline-block;
    }

    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .status-active {
        background: #d4edda;
        color: #155724;
    }

    .status-inactive {
        background: #f8d7da;
        color: #721c24;
    }

    .password-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        border: 1px solid #e3e6f0;
    }

    .input-group-text {
        background: #f8f9fa;
        border-color: #e3e6f0;
    }

    .password-strength {
        height: 4px;
        border-radius: 2px;
        background: #e3e6f0;
        margin-top: 8px;
        overflow: hidden;
    }

    .password-strength-bar {
        height: 100%;
        transition: all 0.3s ease;
        width: 0%;
    }

    .strength-weak { background: #dc3545; }
    .strength-medium { background: #ffc107; }
    .strength-strong { background: #28a745; }

    .user-info-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: bold;
    }

    .auto-save-indicator {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 0.85rem;
        z-index: 9999;
        opacity: 0;
    }
</style>

<?php include '../includes/headers-admin.php'; ?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>Edit Pengguna</h1>
            <p class="text-muted">Kelola informasi pengguna dalam sistem</p>
        </div>
        <div class="page-actions">
            <span class="user-id-badge">ID: <?php echo $user['id']; ?></span>
            <span class="current-time ms-3" id="currentTime"></span>
        </div>
    </div>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="users.php">Pengguna</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Pengguna</li>
        </ol>
    </nav>

    <!-- User Info Card -->
    <div class="user-info-card">
        <div class="d-flex align-items-center">
            <div class="user-avatar me-3">
                <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
            </div>
            <div class="flex-grow-1">
                <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p class="mb-1 opacity-75">@<?php echo htmlspecialchars($user['username']); ?></p>
                <small class="opacity-75">
                    <i class='bx bx-envelope'></i> <?php echo htmlspecialchars($user['email']); ?>
                </small>
            </div>
            <div>
                <span class="status-badge <?php echo $user['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                    <?php echo $user['status'] === 'active' ? 'Aktif' : 'Nonaktif'; ?>
                </span>
                <div class="mt-2">
                    <small class="opacity-75">
                        <i class='bx bx-user-check'></i> 
                        <?php echo ucfirst($user['role']); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
        <form id="userForm">
            <!-- Basic Information Section -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class='bx bx-user'></i>
                    Informasi Dasar
                </h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class='bx bx-at'></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class='bx bx-envelope'></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="full_name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    <div class="invalid-feedback"></div>
                </div>
            </div>

            <!-- Contact Information Section -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class='bx bx-phone'></i>
                    Informasi Kontak
                </h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Nomor Telepon</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class='bx bx-phone'></i></span>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="+62">
                        </div>
                    </div>
                    <div class="col-md-6"></div>
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Alamat</label>
                    <textarea class="form-control" id="address" name="address" rows="3" 
                              placeholder="Masukkan alamat lengkap"><?php echo htmlspecialchars($user['address']); ?></textarea>
                </div>
            </div>

            <!-- Role & Status Section -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class='bx bx-shield'></i>
                    Role & Status
                </h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="buyer" <?php echo $user['role'] === 'buyer' ? 'selected' : ''; ?>>Pembeli</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Password Reset Section (only if not editing own profile) -->
            <?php if ($user_id != $_SESSION['user_id']): ?>
            <div class="form-section">
                <h4 class="section-title">
                    <i class='bx bx-lock'></i>
                    Reset Password
                </h4>
                <div class="password-section">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="new_password" class="form-label">Password Baru</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class='bx bx-lock'></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       placeholder="Minimal 6 karakter">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class='bx bx-show'></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                            <small class="text-muted" id="strengthText">Kosongkan jika tidak ingin mengubah password</small>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="button" class="btn btn-warning" id="resetPasswordBtn">
                                <i class='bx bx-refresh'></i> Reset Password
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-between align-items-center">
                <a href="users.php" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Kembali
                </a>
                <div>
                    <button type="button" class="btn btn-outline-warning me-2" onclick="resetForm()">
                        <i class='bx bx-refresh'></i> Reset
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class='bx bx-save'></i> Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footers-admin.php'; ?>

<script>
const userId = <?php echo $user_id; ?>;
let originalFormData = {};

$(document).ready(function() {
    updateTime();
    setInterval(updateTime, 60000);
    
    // Initialize Select2 for dropdowns
    $('#role, #status').select2({
        theme: 'bootstrap-5',
        width: '100%',
        minimumResultsForSearch: Infinity
    });
    
    // Store original form data
    storeOriginalFormData();
    
    // Form submission
    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        updateUser();
    });
    
    // Password toggle
    $('#togglePassword').on('click', function() {
        const passwordField = $('#new_password');
        const icon = $(this).find('i');
        
        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            icon.removeClass('bx-show').addClass('bx-hide');
        } else {
            passwordField.attr('type', 'password');
            icon.removeClass('bx-hide').addClass('bx-show');
        }
    });
    
    // Password strength checker
    $('#new_password').on('input', function() {
        checkPasswordStrength($(this).val());
    });
    
    // Reset password button
    $('#resetPasswordBtn').on('click', function() {
        resetPassword();
    });
    
    // Real-time validation
    setupRealTimeValidation();
    
    // Auto-save functionality
    setupAutoSave();
});

function storeOriginalFormData() {
    originalFormData = {
        username: $('#username').val(),
        email: $('#email').val(),
        full_name: $('#full_name').val(),
        phone: $('#phone').val(),
        address: $('#address').val(),
        role: $('#role').val(),
        status: $('#status').val()
    };
}

function updateUser() {
    const submitBtn = $('#submitBtn');
    const originalText = submitBtn.html();
    
    // Basic validation
    if (!validateForm()) {
        return;
    }
    
    // Disable button and show loading
    submitBtn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Menyimpan...');
    
    const formData = {
        action: 'update_user',
        username: $('#username').val(),
        email: $('#email').val(),
        full_name: $('#full_name').val(),
        phone: $('#phone').val(),
        address: $('#address').val(),
        role: $('#role').val(),
        status: $('#status').val()
    };
    
    $.ajax({
        url: 'user-edit.php?id=' + userId,
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: response.message,
                    showConfirmButton: true,
                    confirmButtonText: 'OK'
                }).then(() => {
                    // Update original form data
                    storeOriginalFormData();
                    // Refresh page to show updated data
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: response.message
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Terjadi kesalahan saat memperbarui pengguna'
            });
        },
        complete: function() {
            submitBtn.prop('disabled', false).html(originalText);
        }
    });
}

function resetPassword() {
    const password = $('#new_password').val();
    
    if (!password) {
        Swal.fire({
            icon: 'warning',
            title: 'Password Kosong',
            text: 'Masukkan password baru terlebih dahulu'
        });
        return;
    }
    
    if (password.length < 6) {
        Swal.fire({
            icon: 'warning',
            title: 'Password Terlalu Pendek',
            text: 'Password minimal 6 karakter'
        });
        return;
    }
    
    Swal.fire({
        title: 'Reset Password?',
        text: 'Password pengguna akan direset ke password baru',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Reset!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'user-edit.php?id=' + userId,
                type: 'POST',
                data: {
                    action: 'reset_password',
                    new_password: password
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: response.message
                        });
                        $('#new_password').val('');
                        $('#strengthBar').css('width', '0%');
                        $('#strengthText').text('Kosongkan jika tidak ingin mengubah password');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: response.message
                        });
                    }
                }
            });
        }
    });
}

function validateForm() {
    let isValid = true;
    
    // Clear previous validation
    $('.form-control, .form-select').removeClass('is-invalid');
    $('.invalid-feedback').text('');
    
    // Username validation
    if (!$('#username').val().trim()) {
        $('#username').addClass('is-invalid');
        $('#username').siblings('.invalid-feedback').text('Username harus diisi');
        isValid = false;
    }
    
    // Email validation
    const email = $('#email').val().trim();
    if (!email) {
        $('#email').addClass('is-invalid');
        $('#email').siblings('.invalid-feedback').text('Email harus diisi');
        isValid = false;
    } else if (!isValidEmail(email)) {
        $('#email').addClass('is-invalid');
        $('#email').siblings('.invalid-feedback').text('Format email tidak valid');
        isValid = false;
    }
    
    // Full name validation
    if (!$('#full_name').val().trim()) {
        $('#full_name').addClass('is-invalid');
        $('#full_name').siblings('.invalid-feedback').text('Nama lengkap harus diisi');
        isValid = false;
    }
    
    return isValid;
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function setupRealTimeValidation() {
    $('#username, #email, #full_name').on('blur', function() {
        const field = $(this);
        const value = field.val().trim();
        
        field.removeClass('is-invalid is-valid');
        field.siblings('.invalid-feedback').text('');
        
        if (!value) {
            field.addClass('is-invalid');
            field.siblings('.invalid-feedback').text('Field ini harus diisi');
        } else if (field.attr('id') === 'email' && !isValidEmail(value)) {
            field.addClass('is-invalid');
            field.siblings('.invalid-feedback').text('Format email tidak valid');
        } else {
            field.addClass('is-valid');
        }
    });
}

function checkPasswordStrength(password) {
    const strengthBar = $('#strengthBar');
    const strengthText = $('#strengthText');
    
    if (!password) {
        strengthBar.css('width', '0%');
        strengthText.text('Kosongkan jika tidak ingin mengubah password');
        return;
    }
    
    let strength = 0;
    let feedback = '';
    
    if (password.length >= 6) strength += 25;
    if (password.match(/[a-z]/)) strength += 25;
    if (password.match(/[A-Z]/)) strength += 25;
    if (password.match(/[0-9]/)) strength += 25;
    
    strengthBar.css('width', strength + '%');
    
    if (strength < 50) {
        strengthBar.removeClass().addClass('password-strength-bar strength-weak');
        feedback = 'Password lemah';
    } else if (strength < 75) {
        strengthBar.removeClass().addClass('password-strength-bar strength-medium');
        feedback = 'Password sedang';
    } else {
        strengthBar.removeClass().addClass('password-strength-bar strength-strong');
        feedback = 'Password kuat';
    }
    
    strengthText.text(feedback);
}

function resetForm() {
    Swal.fire({
        title: 'Reset Form?',
        text: 'Form akan dikembalikan ke data asli',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Reset!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Reset to original values
            $('#username').val(originalFormData.username);
            $('#email').val(originalFormData.email);
            $('#full_name').val(originalFormData.full_name);
            $('#phone').val(originalFormData.phone);
            $('#address').val(originalFormData.address);
            $('#role').val(originalFormData.role).trigger('change');
            $('#status').val(originalFormData.status).trigger('change');
            
            // Clear validation states
            $('.form-control, .form-select').removeClass('is-invalid is-valid');
            $('.invalid-feedback').text('');
            
            // Reset password field
            $('#new_password').val('');
            $('#strengthBar').css('width', '0%');
            $('#strengthText').text('Kosongkan jika tidak ingin mengubah password');
            
            Swal.fire({
                icon: 'success',
                title: 'Form Direset!',
                text: 'Form berhasil dikembalikan ke data asli',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
}

function setupAutoSave() {
    let autoSaveTimeout;
    const autoSaveIndicator = $('<div class="auto-save-indicator">Auto-saved</div>').appendTo('body');
    
    $('#userForm input, #userForm textarea, #userForm select').on('input change', function() {
        clearTimeout(autoSaveTimeout);
        
        // Don't auto-save if form is invalid or hasn't changed
        if (!hasFormChanged() || !validateForm()) {
            return;
        }
        
        autoSaveTimeout = setTimeout(function() {
            // Auto-save logic here if needed
            autoSaveIndicator.fadeIn().delay(2000).fadeOut();
        }, 3000);
    });
}

function hasFormChanged() {
    const currentData = {
        username: $('#username').val(),
        email: $('#email').val(),
        full_name: $('#full_name').val(),
        phone: $('#phone').val(),
        address: $('#address').val(),
        role: $('#role').val(),
        status: $('#status').val()
    };
    
    return JSON.stringify(currentData) !== JSON.stringify(originalFormData);
}

function updateTime() {
    const now = new Date();
    const options = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Jakarta'
    };
    
    $('#currentTime').text(now.toLocaleDateString('id-ID', options));
}

// Keyboard shortcuts
$(document).on('keydown', function(e) {
    // Ctrl+S to save
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        $('#userForm').submit();
    }
    
    // Escape to reset form
    if (e.key === 'Escape') {
        resetForm();
    }
});

// Prevent accidental page leave if form has changes
window.addEventListener('beforeunload', function(e) {
    if (hasFormChanged()) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Enhanced form validation with debouncing
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Real-time username and email availability check
const checkAvailability = debounce(function(field, value) {
    if (!value || value === originalFormData[field]) return;
    
    $.ajax({
        url: './be-logic/check-availability.php',
        type: 'POST',
        data: {
            field: field,
            value: value,
            user_id: userId
        },
        dataType: 'json',
        success: function(response) {
            const fieldElement = $('#' + field);
            const feedback = fieldElement.siblings('.invalid-feedback');
            
            if (!response.available) {
                fieldElement.removeClass('is-valid').addClass('is-invalid');
                feedback.text(field === 'username' ? 'Username sudah digunakan' : 'Email sudah digunakan');
            } else if (fieldElement.hasClass('is-invalid') && !fieldElement.val().trim() === '') {
                fieldElement.removeClass('is-invalid').addClass('is-valid');
                feedback.text('');
            }
        }
    });
}, 500);

// Enhanced real-time validation
$('#username').on('input', function() {
    const value = $(this).val().trim();
    if (value && value !== originalFormData.username) {
        checkAvailability('username', value);
    }
});

$('#email').on('input', function() {
    const value = $(this).val().trim();
    if (value && isValidEmail(value) && value !== originalFormData.email) {
        checkAvailability('email', value);
    }
});

// Loading states for better UX
function showLoading(element, text = 'Loading...') {
    element.prop('disabled', true).data('original-text', element.html()).html(`<i class="bx bx-loader-alt bx-spin"></i> ${text}`);
}

function hideLoading(element) {
    element.prop('disabled', false).html(element.data('original-text'));
}
</script>

</body>
</html>
            