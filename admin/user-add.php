<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
updateLastActivity($db);

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_user':
            $username = sanitize($_POST['username']);
            $email = sanitize($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $full_name = sanitize($_POST['full_name']);
            $phone = sanitize($_POST['phone']);
            $address = sanitize($_POST['address']);
            $role = sanitize($_POST['role']);
            
            // Validate input
            if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
                echo json_encode(['success' => false, 'message' => 'Username, email, password, dan nama lengkap harus diisi']);
                exit();
            }
            
            if ($password !== $confirm_password) {
                echo json_encode(['success' => false, 'message' => 'Password dan konfirmasi password tidak cocok']);
                exit();
            }
            
            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter']);
                exit();
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
                exit();
            }
            
            try {
                // Check if username or email already exists
                $db->query("SELECT COUNT(*) as count FROM users WHERE username = :username OR email = :email");
                $db->bind(':username', $username);
                $db->bind(':email', $email);
                $result = $db->single();
                
                if ($result['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Username atau email sudah digunakan']);
                    exit();
                }
                
                // Insert new user
                $db->query("INSERT INTO users (username, email, password, full_name, phone, address, role, status, created_at) VALUES (:username, :email, :password, :full_name, :phone, :address, :role, 'active', NOW())");
                $db->bind(':username', $username);
                $db->bind(':email', $email);
                $db->bind(':password', md5($password));
                $db->bind(':full_name', $full_name);
                $db->bind(':phone', $phone);
                $db->bind(':address', $address);
                $db->bind(':role', $role);
                $db->execute();
                
                $user_id = $db->lastInsertId();
                logActivity($db, $_SESSION['user_id'], 'ADD_USER', "Added user: $username");
                
                echo json_encode(['success' => true, 'message' => 'Pengguna berhasil ditambahkan', 'user_id' => $user_id]);
                exit();
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Gagal menambahkan pengguna: ' . $e->getMessage()]);
                exit();
            }
            break;
            
        case 'check_availability':
            $field = $_POST['field'];
            $value = sanitize($_POST['value']);
            
            if ($field === 'username') {
                $db->query("SELECT COUNT(*) as count FROM users WHERE username = :value");
            } elseif ($field === 'email') {
                $db->query("SELECT COUNT(*) as count FROM users WHERE email = :value");
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid field']);
                exit();
            }
            
            $db->bind(':value', $value);
            $result = $db->single();
            
            echo json_encode([
                'success' => true, 
                'available' => $result['count'] == 0,
                'message' => $result['count'] > 0 ? ucfirst($field) . ' sudah digunakan' : ucfirst($field) . ' tersedia'
            ]);
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
    <title>Tambah Pengguna - <?php echo SITE_NAME; ?></title>
</head>
<body>

<style>
    .form-container {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        padding: 30px;
        margin-bottom: 30px;
    }

    .form-section {
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #f0f2f5;
    }

    .form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .section-title i {
        color: #667eea;
    }

    .form-floating {
        margin-bottom: 20px;
    }

    .form-floating > .form-control {
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 16px 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-floating > .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .form-floating > label {
        padding: 16px 12px;
        font-weight: 500;
        color: #6c757d;
    }

    .form-floating > .form-control:focus ~ label,
    .form-floating > .form-control:not(:placeholder-shown) ~ label {
        color: #667eea;
        transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
    }

    .availability-check {
        position: relative;
    }

    .availability-indicator {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 5;
    }

    .availability-available {
        color: #28a745;
    }

    .availability-taken {
        color: #dc3545;
    }

    .availability-checking {
        color: #6c757d;
    }

    .password-strength {
        margin-top: 8px;
        font-size: 0.875rem;
    }

    .strength-weak { color: #dc3545; }
    .strength-medium { color: #ffc107; }
    .strength-strong { color: #28a745; }

    .action-buttons {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #f0f2f5;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 12px;
        padding: 12px 30px;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary {
        background: #6c757d;
        border: none;
        border-radius: 12px;
        padding: 12px 30px;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-2px);
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding: 20px 0;
    }

    .page-title h1 {
        font-size: 2rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 5px;
    }

    .breadcrumb-nav {
        background: none;
        padding: 0;
        margin: 0;
    }

    .breadcrumb-nav .breadcrumb-item {
        font-size: 0.9rem;
    }

    .breadcrumb-nav .breadcrumb-item.active {
        color: #667eea;
        font-weight: 600;
    }

    .current-time {
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 500;
    }

    .form-check-input:checked {
        background-color: #667eea;
        border-color: #667eea;
    }

    .role-description {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 8px;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #667eea;
    }

    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.9);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<?php include '../includes/headers-admin.php'; ?>

<div class="dashboard-container">
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>Tambah Pengguna Baru</h1>
            <nav class="breadcrumb-nav" aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="users.php">Manajemen Pengguna</a></li>
                    <li class="breadcrumb-item active">Tambah Pengguna</li>
                </ol>
            </nav>
        </div>
        <div class="page-actions">
            <span class="current-time" id="currentTime"></span>
        </div>
    </div>

    <!-- Add User Form -->
    <div class="form-container">
        <form id="addUserForm">
            <!-- Account Information Section -->
            <div class="form-section">
                <div class="section-title">
                    <i class='bx bx-user-circle'></i>
                    Informasi Akun
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating availability-check">
                            <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                            <label for="username">Username *</label>
                            <div class="availability-indicator" id="usernameIndicator"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating availability-check">
                            <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                            <label for="email">Email *</label>
                            <div class="availability-indicator" id="emailIndicator"></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            <label for="password">Password *</label>
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Konfirmasi Password" required>
                            <label for="confirm_password">Konfirmasi Password *</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personal Information Section -->
            <div class="form-section">
                <div class="section-title">
                    <i class='bx bx-id-card'></i>
                    Informasi Personal
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Nama Lengkap" required>
                            <label for="full_name">Nama Lengkap *</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="Nomor Telepon">
                            <label for="phone">Nomor Telepon</label>
                        </div>
                    </div>
                </div>

                <div class="form-floating">
                    <textarea class="form-control" id="address" name="address" placeholder="Alamat" style="height: 100px"></textarea>
                    <label for="address">Alamat</label>
                </div>
            </div>

            <!-- Role & Permissions Section -->
            <div class="form-section">
                <div class="section-title">
                    <i class='bx bx-shield'></i>
                    Role & Permissions
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Pilih Role</option>
                                <option value="admin">Administrator</option>
                                <option value="buyer" selected>Pembeli</option>
                            </select>
                            <label for="role">Role Pengguna *</label>
                        </div>
                        <div class="role-description" id="roleDescription">
                            Pilih role untuk melihat deskripsi
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check" style="margin-top: 20px;">
                            <input class="form-check-input" type="checkbox" id="send_welcome_email" name="send_welcome_email" checked>
                            <label class="form-check-label" for="send_welcome_email">
                                Kirim email selamat datang
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="force_password_change" name="force_password_change">
                            <label class="form-check-label" for="force_password_change">
                                Wajib ganti password saat login pertama
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="button" class="btn btn-secondary" onclick="cancelAddUser()">
                    <i class='bx bx-x'></i> Batal
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class='bx bx-save'></i> Simpan Pengguna
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footers-admin.php'; ?>

<script>
    let debounceTimer;
    let formSubmitted = false;

    $(document).ready(function() {
        initializeForm();
        updateTime();
        setInterval(updateTime, 60000);
    });

    function initializeForm() {
        // Username availability check
        $('#username').on('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                checkAvailability('username', $(this).val());
            }, 500);
        });

        // Email availability check
        $('#email').on('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                checkAvailability('email', $(this).val());
            }, 500);
        });

        // Password strength check
        $('#password').on('input', function() {
            checkPasswordStrength($(this).val());
        });

        // Confirm password validation
        $('#confirm_password').on('input', function() {
            validatePasswordMatch();
        });

        // Role description update
        $('#role').on('change', function() {
            updateRoleDescription($(this).val());
        });

        // Form submission
        $('#addUserForm').on('submit', function(e) {
            e.preventDefault();
            if (!formSubmitted) {
                submitForm();
            }
        });

        // Set initial role description
        updateRoleDescription('buyer');
    }

    function checkAvailability(field, value) {
        if (!value || value.length < 3) {
            $(`#${field}Indicator`).html('');
            return;
        }

        $(`#${field}Indicator`).html('<i class="bx bx-loader-alt bx-spin availability-checking"></i>');

        $.ajax({
            url: 'user-add.php',
            type: 'POST',
            data: {
                action: 'check_availability',
                field: field,
                value: value
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const iconClass = response.available ? 'bx-check availability-available' : 'bx-x availability-taken';
                    $(`#${field}Indicator`).html(`<i class="bx ${iconClass}" title="${response.message}"></i>`);
                }
            },
            error: function() {
                $(`#${field}Indicator`).html('<i class="bx bx-error availability-taken" title="Error checking availability"></i>');
            }
        });
    }

    function checkPasswordStrength(password) {
        const strengthDiv = $('#passwordStrength');
        
        if (!password) {
            strengthDiv.html('');
            return;
        }

        let strength = 0;
        let feedback = [];

        // Length check
        if (password.length >= 8) strength++;
        else feedback.push('minimal 8 karakter');

        // Lowercase check
        if (/[a-z]/.test(password)) strength++;
        else feedback.push('huruf kecil');

        // Uppercase check
        if (/[A-Z]/.test(password)) strength++;
        else feedback.push('huruf besar');

        // Number check
        if (/\d/.test(password)) strength++;
        else feedback.push('angka');

        // Special character check
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
        else feedback.push('karakter khusus');

        let strengthText, strengthClass;
        if (strength <= 2) {
            strengthText = 'Lemah';
            strengthClass = 'strength-weak';
        } else if (strength <= 3) {
            strengthText = 'Sedang';
            strengthClass = 'strength-medium';
        } else {
            strengthText = 'Kuat';
            strengthClass = 'strength-strong';
        }

        let html = `<span class="${strengthClass}">Kekuatan password: ${strengthText}</span>`;
        if (feedback.length > 0 && strength < 4) {
            html += `<br><small class="text-muted">Tambahkan: ${feedback.join(', ')}</small>`;
        }

        strengthDiv.html(html);
    }

    function validatePasswordMatch() {
        const password = $('#password').val();
        const confirmPassword = $('#confirm_password').val();
        const confirmField = $('#confirm_password');

        if (!confirmPassword) {
            confirmField.removeClass('is-valid is-invalid');
            return;
        }

        if (password === confirmPassword) {
            confirmField.removeClass('is-invalid').addClass('is-valid');
        } else {
            confirmField.removeClass('is-valid').addClass('is-invalid');
        }
    }

    function updateRoleDescription(role) {
        const descriptions = {
            'admin': 'Administrator memiliki akses penuh ke semua fitur sistem termasuk manajemen pengguna, produk, dan pengaturan.',
            'buyer': 'Pembeli dapat melihat produk, melakukan pemesanan, dan mengelola akun mereka sendiri.'
        };

        $('#roleDescription').text(descriptions[role] || 'Pilih role untuk melihat deskripsi');
    }

    function submitForm() {
        // Validate form
        if (!validateForm()) {
            return;
        }

        formSubmitted = true;
        showLoading(true);
        
        const formData = {
            action: 'add_user',
            username: $('#username').val(),
            email: $('#email').val(),
            password: $('#password').val(),
            confirm_password: $('#confirm_password').val(),
            full_name: $('#full_name').val(),
            phone: $('#phone').val(),
            address: $('#address').val(),
            role: $('#role').val()
        };

        $.ajax({
            url: 'user-add.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: response.message,
                        showCancelButton: true,
                        confirmButtonText: 'Tambah Lagi',
                        cancelButtonText: 'Kembali ke Daftar',
                        confirmButtonColor: '#667eea',
                        cancelButtonColor: '#6c757d'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            resetForm();
                        } else {
                            window.location.href = 'users.php';
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.message,
                        confirmButtonColor: '#667eea'
                    });
                }
                
                formSubmitted = false;
            },
            error: function() {
                showLoading(false);
                formSubmitted = false;
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Terjadi kesalahan saat menambahkan pengguna',
                    confirmButtonColor: '#667eea'
                });
            }
        });
    }

    function validateForm() {
        const username = $('#username').val().trim();
        const email = $('#email').val().trim();
        const password = $('#password').val();
        const confirmPassword = $('#confirm_password').val();
        const fullName = $('#full_name').val().trim();
        const role = $('#role').val();

        // Check required fields
        if (!username || !email || !password || !fullName || !role) {
            Swal.fire({
                icon: 'warning',
                title: 'Lengkapi Data',
                text: 'Harap lengkapi semua field yang wajib diisi',
                confirmButtonColor: '#667eea'
            });
            return false;
        }

        // Check password match
        if (password !== confirmPassword) {
            Swal.fire({
                icon: 'warning',
                title: 'Password Tidak Cocok',
                text: 'Password dan konfirmasi password harus sama',
                confirmButtonColor: '#667eea'
            });
            return false;
        }

        // Check password length
        if (password.length < 6) {
            Swal.fire({
                icon: 'warning',
                title: 'Password Terlalu Pendek',
                text: 'Password minimal harus 6 karakter',
                confirmButtonColor: '#667eea'
            });
            return false;
        }

        // Check email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            Swal.fire({
                icon: 'warning',
                title: 'Email Tidak Valid',
                text: 'Format email tidak valid',
                confirmButtonColor: '#667eea'
            });
            return false;
        }

        return true;
    }

    function resetForm() {
        $('#addUserForm')[0].reset();
        $('.form-control').removeClass('is-valid is-invalid');
        $('.availability-indicator').html('');
        $('#passwordStrength').html('');
        updateRoleDescription('buyer');
        formSubmitted = false;
    }

    function cancelAddUser() {
        Swal.fire({
            title: 'Batalkan Penambahan?',
            text: 'Data yang sudah diisi akan hilang',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Batalkan',
            cancelButtonText: 'Lanjutkan Mengisi',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#667eea'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'users.php';
            }
        });
    }

    function showLoading(show) {
        if (show) {
            $('#loadingOverlay').css('display', 'flex');
            $('#submitBtn').prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Menyimpan...');
        } else {
            $('#loadingOverlay').hide();
            $('#submitBtn').prop('disabled', false).html('<i class="bx bx-save"></i> Simpan Pengguna');
        }
    }

    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleString('id-ID', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        document.getElementById('currentTime').textContent = timeString;
    }
</script>

</body>
</html>