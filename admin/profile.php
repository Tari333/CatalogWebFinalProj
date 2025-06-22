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
        case 'update_profile':
            $full_name = sanitize($_POST['full_name']);
            $phone = sanitize($_POST['phone']);
            $address = sanitize($_POST['address']);
            
            try {
                $db->query("UPDATE users SET full_name = :full_name, phone = :phone, address = :address WHERE id = :id");
                $db->bind(':full_name', $full_name);
                $db->bind(':phone', $phone);
                $db->bind(':address', $address);
                $db->bind(':id', $_SESSION['user_id']);
                $db->execute();
                
                // Update session data
                $_SESSION['full_name'] = $full_name;
                
                logActivity($db, $_SESSION['user_id'], 'UPDATE_PROFILE', "Updated profile information");
                
                echo json_encode(['success' => true, 'message' => 'Profil berhasil diperbarui']);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Gagal memperbarui profil: ' . $e->getMessage()]);
                exit();
            }
            
        case 'change_password':
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Get current user data
            $current_user = getCurrentUser($db);
            
            if (md5($current_password) !== $current_user['password']) {
                echo json_encode(['success' => false, 'message' => 'Password saat ini salah']);
                exit();
            }
            
            if ($new_password !== $confirm_password) {
                echo json_encode(['success' => false, 'message' => 'Password baru dan konfirmasi password tidak cocok']);
                exit();
            }
            
            if (strlen($new_password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password baru minimal 6 karakter']);
                exit();
            }
            
            try {
                $db->query("UPDATE users SET password = :password WHERE id = :id");
                $db->bind(':password', md5($new_password));
                $db->bind(':id', $_SESSION['user_id']);
                $db->execute();
                
                logActivity($db, $_SESSION['user_id'], 'CHANGE_PASSWORD', "Changed password");
                
                echo json_encode(['success' => true, 'message' => 'Password berhasil diubah']);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Gagal mengubah password: ' . $e->getMessage()]);
                exit();
            }
            
        case 'get_profile':
            $current_user = getCurrentUser($db);
            echo json_encode(['success' => true, 'data' => $current_user]);
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
    <title>Profil - <?php echo SITE_NAME; ?></title>
</head>
<body>

<style>
    .profile-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }

    .profile-card:hover {
        transform: translateY(-2px);
    }

    .dashboard-container {
        padding: 10px;
    }

    .profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 2rem;
        color: white;
        font-weight: 600;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
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

    .info-item {
        padding: 10px 0;
        border-bottom: 1px solid #f1f1f1;
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        font-weight: 600;
        color: #6c757d;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }

    .info-value {
        color: #2c3e50;
        font-size: 1rem;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .status-active {
        background-color: #d1f2eb;
        color: #0c7b3e;
    }

    .status-inactive {
        background-color: #fadbd8;
        color: #a93226;
    }

    .role-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        background-color: #e3f2fd;
        color: #1976d2;
    }

    .form-floating {
        margin-bottom: 1rem;
    }

    .btn-custom {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        border: none;
        transition: all 0.3s ease;
    }

    .btn-custom:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
</style>

<?php include '../includes/headers-admin.php'; ?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>Profil Pengguna</h1>
            <p class="text-muted">Kelola informasi profil dan keamanan akun</p>
        </div>
        <div class="page-actions">
            <span class="current-time" id="currentTime"></span>
        </div>
    </div>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-4 mb-4">
            <div class="profile-card">
                <div class="text-center">
                    <div class="profile-avatar" id="profileAvatar">
                        <?php echo strtoupper(substr($current_user['full_name'] ?: $current_user['username'], 0, 1)); ?>
                    </div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($current_user['full_name'] ?: $current_user['username']); ?></h4>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($current_user['email']); ?></p>
                    <div class="mb-3">
                        <span class="role-badge"><?php echo $current_user['role'] == 'admin' ? 'Admin' : 'Pembeli'; ?></span>
                        <span class="status-badge <?php echo $current_user['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $current_user['status'] == 'active' ? 'Aktif' : 'Nonaktif'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="mt-4">
                    <div class="info-item">
                        <div class="info-label">Username</div>
                        <div class="info-value"><?php echo htmlspecialchars($current_user['username']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Terdaftar Pada</div>
                        <div class="info-value"><?php echo formatDate($current_user['created_at']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Terakhir Diperbarui</div>
                        <div class="info-value" id="lastUpdated"><?php echo formatDate($current_user['updated_at']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Form -->
        <div class="col-lg-8">
            <div class="row">
                <!-- Update Profile Form -->
                <div class="col-12 mb-4">
                    <div class="profile-card">
                        <h5 class="mb-4">
                            <i class='bx bx-user-circle text-primary'></i> 
                            Informasi Profil
                        </h5>
                        <form id="profileForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="fullName" name="full_name" 
                                               value="<?php echo htmlspecialchars($current_user['full_name']); ?>" 
                                               placeholder="Nama Lengkap" required>
                                        <label for="fullName">Nama Lengkap</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($current_user['phone']); ?>" 
                                               placeholder="Nomor Telepon">
                                        <label for="phone">Nomor Telepon</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-floating">
                                <textarea class="form-control" id="address" name="address" 
                                          placeholder="Alamat" style="height: 100px"><?php echo htmlspecialchars($current_user['address']); ?></textarea>
                                <label for="address">Alamat</label>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-custom">
                                    <i class='bx bx-save'></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change Password Form -->
                <div class="col-12">
                    <div class="profile-card">
                        <h5 class="mb-4">
                            <i class='bx bx-lock-alt text-warning'></i> 
                            Ubah Password
                        </h5>
                        <form id="passwordForm">
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="currentPassword" 
                                       name="current_password" placeholder="Password Saat Ini" required>
                                <label for="currentPassword">Password Saat Ini</label>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="newPassword" 
                                               name="new_password" placeholder="Password Baru" required>
                                        <label for="newPassword">Password Baru</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="confirmPassword" 
                                               name="confirm_password" placeholder="Konfirmasi Password Baru" required>
                                        <label for="confirmPassword">Konfirmasi Password Baru</label>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-warning btn-custom">
                                    <i class='bx bx-key'></i> Ubah Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footers-admin.php'; ?>

<script>
    $(document).ready(function() {
        updateTime();
        setInterval(updateTime, 60000);

        // Profile form submission
        $('#profileForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                action: 'update_profile',
                full_name: $('#fullName').val(),
                phone: $('#phone').val(),
                address: $('#address').val()
            };

            $.ajax({
                url: 'profile.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Update profile display
                            updateProfileDisplay();
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
                        title: 'Error!',
                        text: 'Terjadi kesalahan saat mengirim data'
                    });
                }
            });
        });

        // Password form submission
        $('#passwordForm').on('submit', function(e) {
            e.preventDefault();
            
            const newPassword = $('#newPassword').val();
            const confirmPassword = $('#confirmPassword').val();
            
            if (newPassword !== confirmPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Password baru dan konfirmasi password tidak cocok'
                });
                return;
            }
            
            if (newPassword.length < 6) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Password minimal 6 karakter'
                });
                return;
            }

            const formData = {
                action: 'change_password',
                current_password: $('#currentPassword').val(),
                new_password: newPassword,
                confirm_password: confirmPassword
            };

            $.ajax({
                url: 'profile.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        // Clear password form
                        $('#passwordForm')[0].reset();
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
                        title: 'Error!',
                        text: 'Terjadi kesalahan saat mengirim data'
                    });
                }
            });
        });
    });

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

    function updateProfileDisplay() {
        $.ajax({
            url: 'profile.php',
            type: 'POST',
            data: { action: 'get_profile' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const user = response.data;
                    // Update profile display elements
                    const fullName = user.full_name || user.username;
                    $('#profileAvatar').text(fullName.charAt(0).toUpperCase());
                    $('#lastUpdated').text(formatDate(user.updated_at));
                }
            }
        });
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
</script>

</body>
</html>