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
        case 'get_activity_logs':
            $user_filter = isset($_POST['user_id']) ? intval($_POST['user_id']) : '';
            $action_filter = isset($_POST['action_type']) ? sanitize($_POST['action_type']) : '';
            
            // Build query with filters
            $query = "SELECT al.*, u.username, u.full_name FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id WHERE 1=1";
            $params = [];
            
            if ($user_filter) {
                $query .= " AND al.user_id = :user_id";
                $params[':user_id'] = $user_filter;
            }
            
            if ($action_filter) {
                $query .= " AND al.action = :action";
                $params[':action'] = $action_filter;
            }
            
            $query .= " ORDER BY al.created_at DESC";
            
            $db->query($query);
            foreach ($params as $key => $value) {
                $db->bind($key, $value);
            }
            $logs = $db->resultset();
            
            echo json_encode(['success' => true, 'data' => $logs]);
            exit();
            
        case 'get_activity_stats':
            // Get activity statistics
            $db->query("SELECT COUNT(*) as total FROM activity_logs WHERE DATE(created_at) = CURDATE()");
            $today_activities = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM activity_logs WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
            $yesterday_activities = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM activity_logs WHERE WEEK(created_at, 1) = WEEK(CURDATE(), 1)");
            $this_week_activities = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM activity_logs");
            $total_activities = $db->single()['total'];
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'today' => $today_activities,
                    'yesterday' => $yesterday_activities,
                    'this_week' => $this_week_activities,
                    'total' => $total_activities
                ]
            ]);
            exit();
            
        case 'get_filter_data':
            // Get distinct actions
            $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
            $actions = $db->resultset();
            
            // Get users
            $db->query("SELECT id, username, full_name FROM users ORDER BY username");
            $users = $db->resultset();
            
            echo json_encode([
                'success' => true,
                'actions' => $actions,
                'users' => $users
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
    <title>Log Aktivitas - <?php echo SITE_NAME; ?></title>
</head>
<body>

<style>
    .activity-stats-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }

    .activity-stats-card:hover {
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

    .filter-row {
        background-color: #f8f9fa;
    }

    .filter-row th {
        padding: 8px !important;
        border-bottom: 1px solid #dee2e6;
    }

    .filter-row .column-filter {
        width: 100%;
        min-width: 90px;
        font-size: 0.8rem;
        border: 1px solid #ced4da;
        border-radius: 4px;
        padding: 4px 8px;
    }

    .action-badge {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .action-login {
        background-color: #d1f2eb;
        color: #0c7b3e;
    }

    .action-logout {
        background-color: #fadbd8;
        color: #a93226;
    }

    .action-create {
        background-color: #d4edda;
        color: #155724;
    }

    .action-update {
        background-color: #fff3cd;
        color: #856404;
    }

    .action-delete {
        background-color: #f8d7da;
        color: #721c24;
    }

    .action-default {
        background-color: #e2e3e5;
        color: #383d41;
    }

    .ip-address {
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
        color: #6c757d;
    }
</style>

<?php include '../includes/headers-admin.php'; ?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>Log Aktivitas</h1>
            <p class="text-muted">Monitor semua aktivitas sistem</p>
        </div>
        <div class="page-actions">
            <span class="current-time" id="currentTime"></span>
        </div>
    </div>

    <!-- Activity Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="activity-stats-card">
                <div class="stats-icon bg-primary">
                    <i class='bx bx-calendar'></i>
                </div>
                <div>
                    <h3 id="todayActivities" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Hari Ini</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="activity-stats-card">
                <div class="stats-icon bg-success">
                    <i class='bx bx-calendar-minus'></i>
                </div>
                <div>
                    <h3 id="yesterdayActivities" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Kemarin</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="activity-stats-card">
                <div class="stats-icon bg-warning">
                    <i class='bx bx-calendar-week'></i>
                </div>
                <div>
                    <h3 id="thisWeekActivities" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Minggu Ini</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="activity-stats-card">
                <div class="stats-icon bg-info">
                    <i class='bx bx-history'></i>
                </div>
                <div>
                    <h3 id="totalActivities" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Total Aktivitas</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Filter Log</h5>
            <div class="row">
                <div class="col-md-4">
                    <label for="userFilter" class="form-label">Pengguna:</label>
                    <select id="userFilter" class="form-select">
                        <option value="">Semua Pengguna</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="actionFilter" class="form-label">Aksi:</label>
                    <select id="actionFilter" class="form-select">
                        <option value="">Semua Aksi</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-primary me-2" onclick="applyFilters()">
                        <i class='bx bx-filter'></i> Filter
                    </button>
                    <button class="btn btn-outline-secondary me-2" onclick="resetFilters()">
                        <i class='bx bx-reset'></i> Reset
                    </button>
                    <button class="btn btn-success" onclick="refreshData()">
                        <i class='bx bx-refresh'></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Logs Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Daftar Log Aktivitas</h5>
                <div>
                    <span class="text-muted">Last updated: </span>
                    <span id="lastUpdated" class="text-primary">-</span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover" id="activityLogsTable">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Pengguna</th>
                            <th>Aksi</th>
                            <th>Deskripsi</th>
                            <th class="no-sort">IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="activityLogsTableBody">
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footers-admin.php'; ?>

<script>
    let activityLogsTable;

    $(document).ready(function() {
        initializeTable();
        loadFilterData();
        loadActivityStats();
        loadActivityLogs();
        updateTime();
        setInterval(updateTime, 60000);
        setInterval(refreshData, 30000); // Auto refresh every 30 seconds
    });

    function initializeTable() {
        if (!$.fn.DataTable.isDataTable("#activityLogsTable")) {
            activityLogsTable = $("#activityLogsTable").DataTable({
                "responsive": true,
                "paging": true,
                "ordering": true,
                "columnDefs": [
                    { "orderable": false, "targets": "no-sort" }
                ],
                "order": [[0, 'desc']], // Order by date descending
                "language": {
                    "emptyTable": "Belum ada log aktivitas",
                    "zeroRecords": "Tidak ada log yang cocok dengan filter",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ log",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 log",
                    "infoFiltered": "(difilter dari _MAX_ total log)",
                    "lengthMenu": "Tampilkan _MENU_ log per halaman",
                    "search": "Cari:",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
                    }
                },
                "info": false,
                "scrollX": false,
                "searching": true,
                "lengthChange": true,
                "fixedHeader": true,
                "initComplete": function (settings, json) {
                    // Wrap table for horizontal scrolling
                    $("#activityLogsTable").wrap("<div style='overflow:auto; width:100%; position:relative;'></div>");
                    
                    // Add filter row below header
                    var filterRow = $('<tr class="filter-row"></tr>');
                    var headers = $(this.api().table().header()).find('th');
                    
                    headers.each(function (index) {
                        var th = $(this);
                        var filterCell = $('<th></th>');
                        var input = $('<input>', {
                            type: 'text',
                            class: 'form-control form-control-sm column-filter',
                            placeholder: 'Filter ' + th.text(),
                            style: 'min-width: 90px;'
                        });
                        filterCell.append(input);
                        filterRow.append(filterCell);
                    });
                    
                    // Insert filter row
                    $(this.api().table().header()).append(filterRow);
                    
                    // Apply column filtering
                    this.api().columns().every(function (index) {
                        var column = this;
                        $('tr.filter-row th:eq(' + index + ') input').on('keyup change clear', function () {
                            if (column.search() !== this.value) {
                                column
                                    .search(this.value)
                                    .draw();
                            }
                        });
                    });
                }
            });
        }
    }

    function loadFilterData() {
        $.ajax({
            url: 'activity-logs.php',
            type: 'POST',
            data: { action: 'get_filter_data' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Populate user filter
                    const userSelect = $('#userFilter');
                    userSelect.empty().append('<option value="">Semua Pengguna</option>');
                    response.users.forEach(function(user) {
                        userSelect.append(`<option value="${user.id}">${user.username} (${user.full_name || user.username})</option>`);
                    });
                    
                    // Populate action filter
                    const actionSelect = $('#actionFilter');
                    actionSelect.empty().append('<option value="">Semua Aksi</option>');
                    response.actions.forEach(function(action) {
                        actionSelect.append(`<option value="${action.action}">${action.action}</option>`);
                    });
                }
            }
        });
    }

    function loadActivityStats() {
        $.ajax({
            url: 'activity-logs.php',
            type: 'POST',
            data: { action: 'get_activity_stats' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#todayActivities').text(response.stats.today);
                    $('#yesterdayActivities').text(response.stats.yesterday);
                    $('#thisWeekActivities').text(response.stats.this_week);
                    $('#totalActivities').text(response.stats.total);
                }
            }
        });
    }

    function loadActivityLogs() {
        const userFilter = $('#userFilter').val();
        const actionFilter = $('#actionFilter').val();
        
        $.ajax({
            url: 'activity-logs.php',
            type: 'POST',
            data: {
                action: 'get_activity_logs',
                user_id: userFilter,
                action_type: actionFilter
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    updateActivityLogsTable(response.data);
                    updateLastUpdated();
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Gagal memuat data log aktivitas'
                });
            }
        });
    }

    function updateActivityLogsTable(logs) {
        if (activityLogsTable) {
            activityLogsTable.clear();
            
            logs.forEach(function(log) {
                const userDisplay = log.user_id ? 
                    (log.full_name || log.username) : 
                    '<span class="text-muted">System</span>';
                
                const actionBadge = getActionBadge(log.action);
                
                activityLogsTable.row.add([
                    formatDate(log.created_at),
                    userDisplay,
                    actionBadge,
                    log.description,
                    `<span class="ip-address">${log.ip_address}</span>`
                ]);
            });
            
            activityLogsTable.draw();
        }
    }

    function getActionBadge(action) {
        const actionLower = action.toLowerCase();
        let badgeClass = 'action-default';
        
        if (actionLower.includes('login')) {
            badgeClass = 'action-login';
        } else if (actionLower.includes('logout')) {
            badgeClass = 'action-logout';
        } else if (actionLower.includes('create') || actionLower.includes('add')) {
            badgeClass = 'action-create';
        } else if (actionLower.includes('update') || actionLower.includes('edit')) {
            badgeClass = 'action-update';
        } else if (actionLower.includes('delete') || actionLower.includes('remove')) {
            badgeClass = 'action-delete';
        }
        
        return `<span class="action-badge ${badgeClass}">${action}</span>`;
    }

    function applyFilters() {
        loadActivityLogs();
        Swal.fire({
            icon: 'success',
            title: 'Filter Diterapkan',
            text: 'Log aktivitas berhasil difilter',
            timer: 1000,
            showConfirmButton: false
        });
    }

    function resetFilters() {
        $('#userFilter').val('').trigger('change');
        $('#actionFilter').val('').trigger('change');
        loadActivityLogs();
        
        Swal.fire({
            icon: 'success',
            title: 'Filter Direset',
            text: 'Semua filter berhasil direset',
            timer: 1000,
            showConfirmButton: false
        });
    }

    function refreshData() {
        loadActivityStats();
        loadActivityLogs();
        
        Swal.fire({
            icon: 'success',
            title: 'Data Diperbarui',
            text: 'Data log aktivitas berhasil diperbarui',
            timer: 1000,
            showConfirmButton: false
        });
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
        return date.toLocaleString('id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
</script>

</body>
</html>