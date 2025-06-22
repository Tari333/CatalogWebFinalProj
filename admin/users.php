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
        case 'delete_user':
            $user_id = intval($_POST['user_id']);
            
            // Can't delete self
            if ($user_id == $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Tidak bisa menghapus akun sendiri']);
                exit();
            }
            
            $db->query("DELETE FROM users WHERE id = :id");
            $db->bind(':id', $user_id);
            $success = $db->execute();
            
            if ($success) {
                logActivity($db, $_SESSION['user_id'], 'DELETE_USER', "Deleted user ID: $user_id");
                echo json_encode(['success' => true, 'message' => 'Pengguna berhasil dihapus']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus pengguna']);
            }
            exit();
            
        case 'toggle_status':
            $user_id = intval($_POST['user_id']);
            
            // Can't deactivate self
            if ($user_id == $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Tidak bisa menonaktifkan akun sendiri']);
                exit();
            }
            
            $db->query("UPDATE users SET status = IF(status='active','inactive','active') WHERE id = :id");
            $db->bind(':id', $user_id);
            $success = $db->execute();
            
            if ($success) {
                logActivity($db, $_SESSION['user_id'], 'UPDATE_USER', "Toggled user status ID: $user_id");
                echo json_encode(['success' => true, 'message' => 'Status pengguna berhasil diubah']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mengubah status pengguna']);
            }
            exit();
            
        case 'get_users':
            $db->query("SELECT * FROM users ORDER BY created_at DESC");
            $users = $db->resultset();
            echo json_encode(['success' => true, 'data' => $users]);
            exit();
            
        case 'get_user_stats':
            // Get user statistics
            $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
            $active_users = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'inactive'");
            $inactive_users = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
            $admin_users = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'buyer'");
            $buyer_users = $db->single()['total'];
            
            // Get recent login count (last 7 days)
            $db->query("SELECT COUNT(*) as total FROM users WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $recent_logins = $db->single()['total'];
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'active' => $active_users,
                    'inactive' => $inactive_users,
                    'admin' => $admin_users,
                    'buyer' => $buyer_users,
                    'recent_logins' => $recent_logins,
                    'total' => $active_users + $inactive_users
                ]
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
    <title>Manajemen Pengguna - <?php echo SITE_NAME; ?></title>
</head>
<body>

<style>
    .user-stats-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }

    .user-stats-card:hover {
        transform: translateY(-2px);
    }

    .dashboard-container {
        padding: 10px;
    }

    .stats-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 15px;
    }

    .stats-icon i {
        font-size: 20px;
        color: white;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(45deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 14px;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .status-active {
        background-color: #d1f2eb;
        color: #0c7b3e;
    }

    .status-inactive {
        background-color: #ffeaa7;
        color: #d63031;
    }

    .role-badge {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .role-admin {
        background-color: #e8f4fd;
        color: #0984e3;
    }

    .role-buyer {
        background-color: #f0f3ff;
        color: #6c5ce7;
    }

    .action-buttons {
        display: flex;
        gap: 5px;
    }

    .btn-action {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-edit {
        background-color: #74b9ff;
        color: white;
    }

    .btn-edit:hover {
        background-color: #0984e3;
    }

    .btn-toggle {
        background-color: #fdcb6e;
        color: white;
    }

    .btn-toggle:hover {
        background-color: #f39c12;
    }

    .btn-delete {
        background-color: #fd79a8;
        color: white;
    }

    .btn-delete:hover {
        background-color: #e84393;
    }

    .quick-actions {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .filter-row {
        background-color: #f8f9fa;
    }

    .filter-row th {
        padding: 8px !important;
        border-bottom: 1px solid #dee2e6;
    }

    .filter-row .column-filter {
        width: 100%;
        min-width: 80px;
        font-size: 0.8rem;
        border: 1px solid #ced4da;
        border-radius: 4px;
        padding: 4px 8px;
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

    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .user-details h6 {
        margin: 0;
        font-size: 0.9rem;
        color: #2c3e50;
    }

    .user-details small {
        color: #6c757d;
        font-size: 0.75rem;
    }
</style>

<?php include '../includes/headers-admin.php'; ?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>Manajemen Pengguna</h1>
            <p class="text-muted">Kelola semua pengguna dalam sistem</p>
        </div>
        <div class="page-actions">
            <span class="current-time" id="currentTime"></span>
        </div>
    </div>

    <!-- User Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="user-stats-card">
                <div class="stats-icon bg-primary">
                    <i class='bx bx-user'></i>
                </div>
                <div>
                    <h3 id="totalUsers" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Total Pengguna</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="user-stats-card">
                <div class="stats-icon bg-success">
                    <i class='bx bx-check-circle'></i>
                </div>
                <div>
                    <h3 id="activeUsers" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Pengguna Aktif</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="user-stats-card">
                <div class="stats-icon bg-info">
                    <i class='bx bx-shield'></i>
                </div>
                <div>
                    <h3 id="adminUsers" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Administrator</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="user-stats-card">
                <div class="stats-icon bg-warning">
                    <i class='bx bx-shopping-bag'></i>
                </div>
                <div>
                    <h3 id="buyerUsers" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Pembeli</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="user-stats-card">
                <div class="stats-icon bg-secondary">
                    <i class='bx bx-time'></i>
                </div>
                <div>
                    <h3 id="recentLogins" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Login 7 Hari</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Aksi Cepat</h5>
            <div class="quick-actions">
                <a href="user-add.php" class="btn btn-primary">
                    <i class='bx bx-plus'></i> Tambah Pengguna Baru
                </a>
                <button class="btn btn-success" onclick="refreshData()">
                    <i class='bx bx-refresh'></i> Refresh Data
                </button>
                <button class="btn btn-info" onclick="exportData()">
                    <i class='bx bx-download'></i> Export Data
                </button>
                <div class="ms-auto">
                    <select class="form-select" id="bulkAction" style="width: auto; display: inline-block;">
                        <option value="">Aksi Massal...</option>
                        <option value="activate">Aktifkan Terpilih</option>
                        <option value="deactivate">Nonaktifkan Terpilih</option>
                        <option value="delete">Hapus Terpilih</option>
                    </select>
                    <button class="btn btn-secondary ms-2" onclick="executeBulkAction()">
                        <i class='bx bx-play'></i> Jalankan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Daftar Pengguna</h5>
                <div>
                    <span class="text-muted">Last updated: </span>
                    <span id="lastUpdated" class="text-primary">-</span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>Avatar</th>
                            <th>ID</th>
                            <th>Informasi Pengguna</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Terakhir Login</th>
                            <th>Tanggal Dibuat</th>
                            <th class="no-sort">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footers-admin.php'; ?>

<script>
    let usersTable;
    let selectedUsers = [];

    $(document).ready(function() {
        initializeTable();
        loadUserStats();
        loadUsers();
        updateTime();
        setInterval(updateTime, 60000);
        setInterval(refreshData, 30000); // Auto refresh every 30 seconds
    });

    function initializeTable() {
        if (!$.fn.DataTable.isDataTable("#usersTable")) {
            usersTable = $("#usersTable").DataTable({
                "responsive": true,
                "paging": true,
                "pageLength": 25,
                "ordering": true,
                "searching": true,
                "lengthChange": true,
                "info": true,
                "autoWidth": false,
                "columnDefs": [
                    { "orderable": false, "targets": [0, 1, 8] }, // checkbox, avatar, actions columns
                    { "width": "5%", "targets": 0 },
                    { "width": "8%", "targets": 1 },
                    { "width": "5%", "targets": 2 },
                    { "width": "30%", "targets": 3 },
                    { "width": "10%", "targets": 4 },
                    { "width": "10%", "targets": 5 },
                    { "width": "12%", "targets": 6 },
                    { "width": "12%", "targets": 7 },
                    { "width": "15%", "targets": 8 }
                ],
                "order": [[2, 'desc']], // Order by ID descending
                "language": {
                    "emptyTable": "Belum ada pengguna yang terdaftar",
                    "zeroRecords": "Tidak ada pengguna yang cocok dengan pencarian",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ pengguna",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 pengguna",
                    "infoFiltered": "(difilter dari _MAX_ total pengguna)",
                    "lengthMenu": "Tampilkan _MENU_ pengguna per halaman",
                    "search": "Cari:",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
                    }
                },
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
                "initComplete": function () {
                    // Add filter row
                    var filterRow = $('<tr class="filter-row"></tr>');
                    var headers = $(this.api().table().header()).find('th');

                    headers.each(function (index) {
                        var th = $(this);
                        var filterCell = $('<th></th>');

                        if (index === 0 || index === 1 || index === 8) {
                            // Skip checkbox, avatar, and actions columns
                            filterCell.html('');
                        } else {
                            var input = $('<input>', {
                                type: 'text',
                                class: 'form-control form-control-sm column-filter',
                                placeholder: 'Filter...'
                            });
                            filterCell.append(input);
                        }

                        filterRow.append(filterCell);
                    });

                    $(this.api().table().header()).append(filterRow);

                    // Apply column filtering
                    this.api().columns().every(function (index) {
                        if (index === 0 || index === 1 || index === 8) return; // Skip certain columns

                        var column = this;
                        $('tr.filter-row th:eq(' + index + ') input').on('keyup change clear', function () {
                            if (column.search() !== this.value) {
                                column.search(this.value).draw();
                            }
                        });
                    });
                }
            });
        }
    }

    function loadUserStats() {
        $.ajax({
            url: 'users.php',
            type: 'POST',
            data: { action: 'get_user_stats' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#totalUsers').text(response.stats.total);
                    $('#activeUsers').text(response.stats.active);
                    $('#adminUsers').text(response.stats.admin);
                    $('#buyerUsers').text(response.stats.buyer);
                    $('#recentLogins').text(response.stats.recent_logins);
                }
            }
        });
    }

    function loadUsers() {
        $.ajax({
            url: 'users.php',
            type: 'POST',
            data: { action: 'get_users' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    updateUsersTable(response.data);
                    updateLastUpdated();
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Gagal memuat data pengguna'
                });
            }
        });
    }

    function updateUsersTable(users) {
        if (usersTable) {
            usersTable.clear();
            
            users.forEach(function(user) {
                const initials = getInitials(user.full_name || user.username);
                const avatarHtml = `<div class="user-avatar">${initials}</div>`;
                
                const userInfoHtml = `
                    <div class="user-info">
                        <div class="user-details">
                            <h6>${user.full_name || user.username}</h6>
                            <small>@${user.username}</small>
                        </div>
                    </div>
                `;
                
                const roleHtml = `<span class="role-badge ${user.role === 'admin' ? 'role-admin' : 'role-buyer'}">
                    ${user.role === 'admin' ? 'Administrator' : 'Pembeli'}
                </span>`;
                
                const statusHtml = `<span class="status-badge ${user.status === 'active' ? 'status-active' : 'status-inactive'}">
                    ${user.status === 'active' ? 'Aktif' : 'Nonaktif'}
                </span>`;
                
                const canModify = user.id != <?php echo $_SESSION['user_id']; ?>;
                
                const actionsHtml = `
                    <div class="action-buttons">
                        <button class="btn-action btn-edit" onclick="editUser(${user.id})" title="Edit">
                            <i class='bx bx-edit'></i>
                        </button>
                        ${canModify ? `
                            <button class="btn-action btn-toggle" onclick="toggleUserStatus(${user.id})" title="Toggle Status">
                                <i class='bx ${user.status === 'active' ? 'bx-toggle-right' : 'bx-toggle-left'}'></i>
                            </button>
                            <button class="btn-action btn-delete" onclick="deleteUser(${user.id})" title="Hapus">
                                <i class='bx bx-trash'></i>
                            </button>
                        ` : `
                            <span class="text-muted" style="font-size: 0.7rem;">Akun Anda</span>
                        `}
                    </div>
                `;
                
                usersTable.row.add([
                    canModify ? `<input type="checkbox" class="form-check-input user-checkbox" value="${user.id}">` : '',
                    avatarHtml,
                    user.id,
                    userInfoHtml,
                    roleHtml,
                    statusHtml,
                    formatDate(user.updated_at),
                    formatDate(user.created_at),
                    actionsHtml
                ]);
            });
            
            usersTable.draw();
        }
    }

    function getInitials(name) {
        if (!name) return '?';
        return name.split(' ').map(word => word[0]).join('').toUpperCase().substring(0, 2);
    }

    function editUser(id) {
        window.location.href = `user-edit.php?id=${id}`;
    }

    function toggleUserStatus(id) {
        Swal.fire({
            title: 'Konfirmasi',
            text: 'Yakin ingin mengubah status pengguna ini?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Ubah!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'users.php',
                    type: 'POST',
                    data: {
                        action: 'toggle_status',
                        user_id: id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: response.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            refreshData();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message
                            });
                        }
                    }
                });
            }
        });
    }

    function deleteUser(id) {
        Swal.fire({
            title: 'Konfirmasi Hapus',
            text: 'Yakin ingin menghapus pengguna ini? Tindakan ini tidak dapat dibatalkan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'users.php',
                    type: 'POST',
                    data: {
                        action: 'delete_user',
                        user_id: id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: response.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            refreshData();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message
                            });
                        }
                    }
                });
            }
        });
    }

    function refreshData() {
        loadUserStats();
        loadUsers();
        
        Swal.fire({
            icon: 'success',
            title: 'Data Diperbarui',
            text: 'Data pengguna berhasil diperbarui',
            timer: 1000,
            showConfirmButton: false
        });
    }

    function exportData() {
        Swal.fire({
            icon: 'info',
            title: 'Export Data',
            text: 'Fitur export akan segera tersedia',
            timer: 2000,
            showConfirmButton: false
        });
    }

    function executeBulkAction() {
        const action = $('#bulkAction').val();
        const checkedBoxes = $('.user-checkbox:checked');
        
        if (!action) {
            Swal.fire({
                icon: 'warning',
                title: 'Pilih Aksi',
                text: 'Silakan pilih aksi yang ingin dilakukan'
            });
            return;
        }
        
        if (checkedBoxes.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Tidak Ada Pengguna',
                text: 'Silakan pilih pengguna terlebih dahulu'
            });
            return;
        }
        
        Swal.fire({
            icon: 'info',
            title: 'Bulk Action',
            text: 'Fitur aksi massal akan segera tersedia',
            timer: 2000,
            showConfirmButton: false
        });
    }

    // Checkbox functionality
    $(document).on('change', '#selectAll', function() {
        $('.user-checkbox').prop('checked', this.checked);
    });

    $(document).on('change', '.user-checkbox', function() {
        const totalCheckboxes = $('.user-checkbox').length;
        const checkedCheckboxes = $('.user-checkbox:checked').length;
        
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
        $('#selectAll').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
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

    function updateLastUpdated() {
        const now = new Date();
        const timeString = now.toLocaleString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        document.getElementById('lastUpdated').textContent = timeString;
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
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