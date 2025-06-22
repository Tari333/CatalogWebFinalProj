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
        case 'get_orders':
            $status_filter = isset($_POST['status_filter']) ? sanitize($_POST['status_filter']) : '';
            $payment_filter = isset($_POST['payment_filter']) ? sanitize($_POST['payment_filter']) : '';
            
            $query = "SELECT o.*, u.username, u.full_name FROM orders o 
                      JOIN users u ON o.user_id = u.id WHERE 1=1";
            $params = [];
            
            if ($status_filter) {
                $query .= " AND o.status = :status";
                $params[':status'] = $status_filter;
            }
            
            if ($payment_filter) {
                $query .= " AND o.payment_status = :payment_status";
                $params[':payment_status'] = $payment_filter;
            }
            
            $query .= " ORDER BY o.created_at DESC";
            
            $db->query($query);
            foreach ($params as $key => $value) {
                $db->bind($key, $value);
            }
            $orders = $db->resultset();
            
            echo json_encode(['success' => true, 'data' => $orders]);
            exit();
            
        case 'update_order_status':
            $order_id = intval($_POST['order_id']);
            $new_status = sanitize($_POST['new_status']);
            
            $valid_statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
            
            if (in_array($new_status, $valid_statuses)) {
                $db->query("UPDATE orders SET status = :status WHERE id = :id");
                $db->bind(':status', $new_status);
                $db->bind(':id', $order_id);
                $success = $db->execute();
                
                if ($success) {
                    logActivity($db, $_SESSION['user_id'], 'UPDATE_ORDER_STATUS', "Order ID: $order_id, New Status: $new_status");
                    echo json_encode(['success' => true, 'message' => 'Status pesanan berhasil diperbarui']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status pesanan']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
            }
            exit();
            
        case 'verify_payment':
            $order_id = intval($_POST['order_id']);
            $verification_status = sanitize($_POST['verification_status']);
            
            $valid_statuses = ['verified', 'rejected'];
            
            if (in_array($verification_status, $valid_statuses)) {
                $db->query("UPDATE orders SET payment_status = :status WHERE id = :id");
                $db->bind(':status', $verification_status);
                $db->bind(':id', $order_id);
                $success = $db->execute();
                
                if ($success) {
                    logActivity($db, $_SESSION['user_id'], 'UPDATE_PAYMENT_STATUS', "Order ID: $order_id, Payment Status: $verification_status");
                    echo json_encode(['success' => true, 'message' => 'Status pembayaran berhasil diperbarui']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status pembayaran']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
            }
            exit();
            
        case 'get_order_stats':
            // Get order statistics
            $db->query("SELECT COUNT(*) as total FROM orders");
            $total_orders = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
            $pending_orders = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM orders WHERE status = 'confirmed'");
            $confirmed_orders = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM orders WHERE status = 'delivered'");
            $delivered_orders = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM orders WHERE payment_status = 'pending'");
            $pending_payments = $db->single()['total'];
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'total' => $total_orders,
                    'pending' => $pending_orders,
                    'confirmed' => $confirmed_orders,
                    'delivered' => $delivered_orders,
                    'pending_payments' => $pending_payments
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
    <title>Manajemen Pesanan - <?php echo SITE_NAME; ?></title>
</head>
<body>

<style>
    .order-stats-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }

    .order-stats-card:hover {
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

    .status-badge {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .status-pending {
        background-color: #fff3cd;
        color: #856404;
    }

    .status-confirmed {
        background-color: #d4edda;
        color: #155724;
    }

    .status-processing {
        background-color: #cce5ff;
        color: #004085;
    }

    .status-shipped {
        background-color: #e2e3e5;
        color: #383d41;
    }

    .status-delivered {
        background-color: #d1f2eb;
        color: #0c7b3e;
    }

    .status-cancelled {
        background-color: #f8d7da;
        color: #721c24;
    }

    .payment-verified {
        background-color: #d1f2eb;
        color: #0c7b3e;
    }

    .payment-pending {
        background-color: #fff3cd;
        color: #856404;
    }

    .payment-rejected {
        background-color: #f8d7da;
        color: #721c24;
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

    .btn-view {
        background-color: #17a2b8;
        color: white;
    }

    .btn-view:hover {
        background-color: #138496;
    }

    .btn-status {
        background-color: #28a745;
        color: white;
    }

    .btn-status:hover {
        background-color: #218838;
    }

    .btn-payment {
        background-color: #ffc107;
        color: #212529;
    }

    .btn-payment:hover {
        background-color: #e0a800;
    }

    .quick-actions {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
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

    .order-number {
        font-weight: 600;
        color: #495057;
    }

    .customer-info {
        font-size: 0.9rem;
        color: #6c757d;
    }
</style>

<?php include '../includes/headers-admin.php'; ?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>Manajemen Pesanan</h1>
            <p class="text-muted">Kelola semua pesanan pelanggan</p>
        </div>
        <div class="page-actions">
            <span class="current-time" id="currentTime"></span>
        </div>
    </div>

    <!-- Order Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="order-stats-card">
                <div class="stats-icon bg-primary">
                    <i class='bx bx-receipt'></i>
                </div>
                <div>
                    <h3 id="totalOrders" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Total Pesanan</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="order-stats-card">
                <div class="stats-icon bg-warning">
                    <i class='bx bx-time'></i>
                </div>
                <div>
                    <h3 id="pendingOrders" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Menunggu</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="order-stats-card">
                <div class="stats-icon bg-info">
                    <i class='bx bx-check-circle'></i>
                </div>
                <div>
                    <h3 id="confirmedOrders" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Dikonfirmasi</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="order-stats-card">
                <div class="stats-icon bg-success">
                    <i class='bx bx-package'></i>
                </div>
                <div>
                    <h3 id="deliveredOrders" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Diterima</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="order-stats-card">
                <div class="stats-icon bg-danger">
                    <i class='bx bx-credit-card'></i>
                </div>
                <div>
                    <h3 id="pendingPayments" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Pembayaran Pending</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Filter & Aksi Cepat</h5>
            <div class="row">
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label">Status Pesanan</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Semua Status</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Dikonfirmasi</option>
                        <option value="processing">Diproses</option>
                        <option value="shipped">Dikirim</option>
                        <option value="delivered">Diterima</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="paymentFilter" class="form-label">Status Pembayaran</label>
                    <select class="form-select" id="paymentFilter">
                        <option value="">Semua Status</option>
                        <option value="pending">Pending</option>
                        <option value="verified">Terverifikasi</option>
                        <option value="rejected">Ditolak</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">&nbsp;</label>
                    <div class="quick-actions">
                        <button class="btn btn-primary" onclick="applyFilters()">
                            <i class='bx bx-filter'></i> Terapkan Filter
                        </button>
                        <button class="btn btn-success" onclick="refreshData()">
                            <i class='bx bx-refresh'></i> Refresh Data
                        </button>
                        <button class="btn btn-info" onclick="exportData()">
                            <i class='bx bx-download'></i> Export Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Daftar Pesanan</h5>
                <div>
                    <span class="text-muted">Last updated: </span>
                    <span id="lastUpdated" class="text-primary">-</span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover" id="ordersTable">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Pembayaran</th>
                            <th>Tanggal</th>
                            <th class="no-sort">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footers-admin.php'; ?>

<script>
    let ordersTable;
    let currentFilters = {
        status: '',
        payment_status: ''
    };

    $(document).ready(function() {
        initializeTable();
        loadOrderStats();
        loadOrders();
        updateTime();
        setInterval(updateTime, 60000);
        setInterval(refreshData, 30000); // Auto refresh every 30 seconds
        
        // Initialize Select2 for filters
        $('#statusFilter, #paymentFilter').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    });

    function initializeTable() {
        if (!$.fn.DataTable.isDataTable("#ordersTable")) {
            ordersTable = $("#ordersTable").DataTable({
                "responsive": true,
                "paging": true,
                "pageLength": 25,
                "ordering": true,
                "searching": true,
                "lengthChange": true,
                "info": true,
                "autoWidth": false,
                "columnDefs": [
                    { "orderable": false, "targets": [6] }, // actions column
                    { "width": "15%", "targets": 0 },
                    { "width": "20%", "targets": 1 },
                    { "width": "12%", "targets": 2 },
                    { "width": "12%", "targets": 3 },
                    { "width": "12%", "targets": 4 },
                    { "width": "12%", "targets": 5 },
                    { "width": "17%", "targets": 6 }
                ],
                "order": [[5, 'desc']], // Order by date descending
                "language": {
                    "emptyTable": "Belum ada pesanan yang tersedia",
                    "zeroRecords": "Tidak ada pesanan yang cocok dengan pencarian",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ pesanan",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 pesanan",
                    "infoFiltered": "(difilter dari _MAX_ total pesanan)",
                    "lengthMenu": "Tampilkan _MENU_ pesanan per halaman",
                    "search": "Cari:",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
                    }
                }
            });
        }
    }

    function loadOrderStats() {
        $.ajax({
            url: 'orders.php',
            type: 'POST',
            data: { action: 'get_order_stats' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#totalOrders').text(response.stats.total);
                    $('#pendingOrders').text(response.stats.pending);
                    $('#confirmedOrders').text(response.stats.confirmed);
                    $('#deliveredOrders').text(response.stats.delivered);
                    $('#pendingPayments').text(response.stats.pending_payments);
                }
            }
        });
    }

    function loadOrders() {
        $.ajax({
            url: 'orders.php',
            type: 'POST',
            data: { 
                action: 'get_orders',
                status_filter: currentFilters.status,
                payment_filter: currentFilters.payment_status
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    updateOrdersTable(response.data);
                    updateLastUpdated();
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Gagal memuat data pesanan'
                });
            }
        });
    }

    function updateOrdersTable(orders) {
        if (ordersTable) {
            ordersTable.clear();
            
            orders.forEach(function(order) {
                const statusClass = `status-${order.status}`;
                const paymentClass = `payment-${order.payment_status}`;
                
                const statusHtml = `<span class="status-badge ${statusClass}">
                    ${getStatusText(order.status)}
                </span>`;
                
                const paymentHtml = `<span class="status-badge ${paymentClass}">
                    ${getPaymentStatusText(order.payment_status)}
                </span>`;
                
                const actionsHtml = `
                    <div class="action-buttons">
                        <button class="btn-action btn-view" onclick="viewOrder(${order.id})" title="Detail">
                            <i class='bx bx-eye'></i>
                        </button>
                        <button class="btn-action btn-status" onclick="updateOrderStatus(${order.id}, '${order.status}')" title="Update Status">
                            <i class='bx bx-edit'></i>
                        </button>
                        ${order.payment_proof && order.payment_status === 'pending' ? 
                            `<button class="btn-action btn-payment" onclick="verifyPayment(${order.id})" title="Verifikasi Pembayaran">
                                <i class='bx bx-credit-card'></i>
                            </button>` : ''
                        }
                    </div>
                `;
                
                ordersTable.row.add([
                    `<span class="order-number">${order.order_number}</span>`,
                    `<div>
                        <strong>${order.full_name}</strong>
                        <div class="customer-info">@${order.username}</div>
                    </div>`,
                    formatCurrency(order.total_amount),
                    statusHtml,
                    paymentHtml,
                    formatDate(order.created_at),
                    actionsHtml
                ]);
            });
            
            ordersTable.draw();
        }
    }

    function applyFilters() {
        currentFilters.status = $('#statusFilter').val();
        currentFilters.payment_status = $('#paymentFilter').val();
        loadOrders();
    }

    function viewOrder(id) {
        window.location.href = `order-detail.php?id=${id}`;
    }

    function updateOrderStatus(id, currentStatus) {
        const statuses = [
            { value: 'pending', text: 'Pending' },
            { value: 'confirmed', text: 'Dikonfirmasi' },
            { value: 'processing', text: 'Diproses' },
            { value: 'shipped', text: 'Dikirim' },
            { value: 'delivered', text: 'Diterima' },
            { value: 'cancelled', text: 'Dibatalkan' }
        ];

        let optionsHtml = '';
        statuses.forEach(status => {
            optionsHtml += `<option value="${status.value}" ${status.value === currentStatus ? 'selected' : ''}>${status.text}</option>`;
        });

        Swal.fire({
            title: 'Update Status Pesanan',
            html: `<select id="newStatus" class="form-select">${optionsHtml}</select>`,
            showCancelButton: true,
            confirmButtonText: 'Update',
            cancelButtonText: 'Batal',
            preConfirm: () => {
                return document.getElementById('newStatus').value;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'orders.php',
                    type: 'POST',
                    data: {
                        action: 'update_order_status',
                        order_id: id,
                        new_status: result.value
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

    function verifyPayment(id) {
        Swal.fire({
            title: 'Verifikasi Pembayaran',
            text: 'Pilih status verifikasi pembayaran',
            icon: 'question',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Verifikasi',
            denyButtonText: 'Tolak',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#28a745',
            denyButtonColor: '#dc3545'
        }).then((result) => {
            if (result.isConfirmed || result.isDenied) {
                const status = result.isConfirmed ? 'verified' : 'rejected';
                
                $.ajax({
                    url: 'orders.php',
                    type: 'POST',
                    data: {
                        action: 'verify_payment',
                        order_id: id,
                        verification_status: status
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
        loadOrderStats();
        loadOrders();
        
        Swal.fire({
            icon: 'success',
            title: 'Data Diperbarui',
            text: 'Data pesanan berhasil diperbarui',
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

    function getStatusText(status) {
        const statusMap = {
            'pending': 'Pending',
            'confirmed': 'Dikonfirmasi',
            'processing': 'Diproses',
            'shipped': 'Dikirim',
            'delivered': 'Diterima',
            'cancelled': 'Dibatalkan'
        };
        return statusMap[status] || status;
    }

    function getPaymentStatusText(status) {
        const statusMap = {
            'pending': 'Pending',
            'verified': 'Terverifikasi',
            'rejected': 'Ditolak'
        };
        return statusMap[status] || status;
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(amount);
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