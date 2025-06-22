<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
updateLastActivity($db);

// Get dashboard statistics
// Total users
$db->query("SELECT COUNT(*) as total FROM users WHERE role = 'buyer'");
$total_buyers = $db->single()['total'];

$db->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$total_admins = $db->single()['total'];

$db->query("SELECT COUNT(*) as total FROM users");
$total_users = $db->single()['total'];

// Active users (logged in within last 24 hours)
$db->query("SELECT COUNT(DISTINCT user_id) as total FROM user_sessions WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$active_users = $db->single()['total'];

// Online users (active within last 5 minutes)
$online_users = getOnlineUsersCount($db);

// Products statistics
$db->query("SELECT COUNT(*) as total FROM products WHERE status = 'active'");
$total_products = $db->single()['total'];

$db->query("SELECT COUNT(*) as total FROM products WHERE status = 'inactive'");
$inactive_products = $db->single()['total'];

$db->query("SELECT COUNT(*) as total FROM products WHERE stock = 0");
$out_of_stock = $db->single()['total'];

$db->query("SELECT COUNT(*) as total FROM products WHERE stock < 10 AND stock > 0");
$low_stock = $db->single()['total'];

// Orders statistics
$db->query("SELECT COUNT(*) as total FROM orders");
$total_orders = $db->single()['total'];

$db->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$pending_orders = $db->single()['total'];

$db->query("SELECT COUNT(*) as total FROM orders WHERE status = 'confirmed'");
$confirmed_orders = $db->single()['total'];

$db->query("SELECT COUNT(*) as total FROM orders WHERE payment_status = 'pending'");
$pending_payments = $db->single()['total'];

// Sales statistics
$db->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'");
$total_sales = $db->single()['total'] ?? 0;

$db->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled' AND DATE(created_at) = CURDATE()");
$today_sales = $db->single()['total'] ?? 0;

$db->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled' AND WEEK(created_at) = WEEK(NOW())");
$week_sales = $db->single()['total'] ?? 0;

$db->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled' AND MONTH(created_at) = MONTH(NOW())");
$month_sales = $db->single()['total'] ?? 0;

// Recent activities
$db->query("SELECT al.*, u.username, u.full_name FROM activity_logs al 
           LEFT JOIN users u ON al.user_id = u.id 
           ORDER BY al.created_at DESC LIMIT 10");
$recent_activities = $db->resultset();

// Recent orders
$db->query("SELECT o.*, u.username, u.full_name FROM orders o 
           JOIN users u ON o.user_id = u.id 
           ORDER BY o.created_at DESC LIMIT 10");
$recent_orders = $db->resultset();

// Top products
$db->query("SELECT p.name, p.price, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as total_revenue
           FROM products p 
           JOIN order_items oi ON p.id = oi.product_id 
           JOIN orders o ON oi.order_id = o.id 
           WHERE o.status != 'cancelled'
           GROUP BY p.id 
           ORDER BY total_sold DESC LIMIT 5");
$top_products = $db->resultset();

// Monthly sales chart data
$db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as total
           FROM orders 
           WHERE status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
           GROUP BY DATE_FORMAT(created_at, '%Y-%m')
           ORDER BY month");
$monthly_sales = $db->resultset();

// Order status distribution
$db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
$order_status_data = $db->resultset();

$current_user = getCurrentUser($db);
?>

<style>
    /* DataTable Filter Row Styling */
    .filter-row {
        background-color: #f4f4f4;
    }

    .filter-row th {
        padding: 5px !important;
    }

    .filter-row .column-filter {
        width: 100%;
        min-width: 90px;
        font-size: 8pt;
        border: 1px solid #d2d6de;
    }

    /* Ensure filter inputs are visible and styled consistently */
    #topProductsTable thead tr.filter-row th input {
        display: block;
        width: 100%;
        height: calc(1.5em + .5rem + 2px);
        padding: .25rem .5rem;
        font-size: 8pt;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid #ced4da;
        border-radius: .25rem;
        transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
    }
    
    .dashboard-container {
        padding: 10px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
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

    .stats-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        transition: transform 0.2s;
    }

    .stats-card:hover {
        transform: translateY(-2px);
    }

    .stats-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20px;
    }

    .stats-icon i {
        font-size: 24px;
        color: white;
    }

    .stats-info h3 {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 5px;
        color: #2c3e50;
    }

    .stats-info p {
        color: #6c757d;
        margin: 0;
        font-weight: 500;
        font-size: 0.8rem;
    }

    .card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        border: none;
    }

    .card-header {
        background: transparent;
        border-bottom: 1px solid #eee;
        padding: 20px 25px;
    }

    .card-body {
        padding: 25px;
    }

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .quick-action-btn {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        background: #f8f9fa;
        border-radius: 8px;
        text-decoration: none;
        color: #495057;
        transition: all 0.2s;
        position: relative;
    }

    .quick-action-btn:hover {
        background: #e9ecef;
        color: #495057;
        transform: translateY(-1px);
    }

    .quick-action-btn i {
        font-size: 20px;
        margin-right: 10px;
            color: #006A4E;
    }

    .quick-action-btn .badge {
        position: absolute;
        top: -5px;
        right: -5px;
        font-size: 0.7rem;
    }

    .status-item {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 10px;
    }

    .summary-item {
        padding: 15px 0;
        border-bottom: 1px solid #eee;
    }

    .summary-item:last-child {
        border-bottom: none;
    }

    .summary-label {
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 5px;
    }

    .summary-value {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 3px;
    }

    .summary-percentage {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .highcharts-background {
        fill: transparent !important;
    }

    .highcharts-button-box {
        fill: transparent !important;
    }
</style>

<?php include '../includes/headers-admin.php';?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>Dashboard</h1>
            <p class="text-muted">Overview of your catalog system</p>
        </div>
        <div class="page-actions">
            <span class="current-time" id="currentTime"></span>
        </div>
    </div>

    <!-- Welcome Message -->
    <div class="alert alert-success mb-4">
        <h4 class="alert-heading">Selamat datang, <?php echo htmlspecialchars($current_user['full_name']); ?>!</h4>
        <p class="mb-0">Terakhir login: <?php echo formatDate($current_user['updated_at']); ?></p>
    </div>

    <!-- Key Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-3">Statistik Utama</h2>
        </div>
        
        <!-- Users Stats -->
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Users</h5>
                        <div class="bg-primary bg-opacity-10 p-2 rounded">
                            <i class='bx bx-group text-primary'></i>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted">Total Users</small>
                            <h4 class="mb-0"><?php echo $total_users; ?></h4>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">Admin</small>
                            <h4 class="mb-0"><?php echo $total_admins; ?></h4>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">Buyers</small>
                            <h4 class="mb-0"><?php echo $total_buyers; ?></h4>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">Online</small>
                            <h4 class="mb-0 text-success"><?php echo $online_users; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Products Stats -->
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Produk</h5>
                        <div class="bg-info bg-opacity-10 p-2 rounded">
                            <i class='bx bx-package text-info'></i>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted">Total Produk</small>
                            <h4 class="mb-0"><?php echo $total_products; ?></h4>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">Aktif</small>
                            <h4 class="mb-0 text-success"><?php echo $total_products; ?></h4>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">Nonaktif</small>
                            <h4 class="mb-0 text-danger"><?php echo $inactive_products; ?></h4>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">Stok Habis</small>
                            <h4 class="mb-0 text-danger"><?php echo $out_of_stock; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Orders Stats -->
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Pesanan</h5>
                        <div class="bg-warning bg-opacity-10 p-2 rounded">
                            <i class='bx bx-receipt text-warning'></i>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted">Total Pesanan</small>
                            <h4 class="mb-0"><?php echo $total_orders; ?></h4>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">Pending</small>
                            <h4 class="mb-0 text-warning"><?php echo $pending_orders; ?></h4>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">Dikonfirmasi</small>
                            <h4 class="mb-0 text-success"><?php echo $confirmed_orders; ?></h4>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">Pembayaran</small>
                            <h4 class="mb-0 text-danger"><?php echo $pending_payments; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sales Stats -->
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Penjualan</h5>
                        <div class="bg-success bg-opacity-10 p-2 rounded">
                            <i class='bx bx-dollar-circle text-success'></i>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-2">
                            <small class="text-muted">Total Penjualan</small>
                            <h4 class="mb-0"><?php echo formatCurrency($total_sales); ?></h4>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">Hari Ini</small>
                            <h5 class="mb-0 text-success"><?php echo formatCurrency($today_sales); ?></h5>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">Minggu Ini</small>
                            <h5 class="mb-0"><?php echo formatCurrency($week_sales); ?></h5>
                        </div>
                        <div class="col-12 mb-2">
                            <small class="text-muted">Bulan Ini</small>
                            <h5 class="mb-0"><?php echo formatCurrency($month_sales); ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">Penjualan Bulanan (12 Bulan Terakhir)</h5>
                    <div id="monthlySalesChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">Status Pesanan</h5>
                    <div id="orderStatusChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Products -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Produk Terlaris</h5>
                        <a href="products.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="topProductsTable">
                            <thead>
                                <tr>
                                    <th>Nama Produk</th>
                                    <th>Harga</th>
                                    <th>Total Terjual</th>
                                    <th>Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo formatCurrency($product['price']); ?></td>
                                        <td><?php echo $product['total_sold']; ?></td>
                                        <td><?php echo formatCurrency($product['total_revenue']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities and Orders -->
    <div class="row">
        <!-- Recent Activities -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">Aktivitas Terbaru</h5>
                    <div class="activity-feed" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                                    <small class="text-muted"><?php echo formatDate($activity['created_at']); ?></small>
                                </div>
                                <small class="text-muted d-block mb-1">
                                    <?php echo $activity['full_name'] ? htmlspecialchars($activity['full_name']) : 'System'; ?>
                                </small>
                                <?php if ($activity['description']): ?>
                                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($activity['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Pesanan Terbaru</h5>
                        <a href="orders.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    
                    <div class="order-list" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($recent_orders)): ?>
                            <div class="text-center py-4">Belum ada pesanan</div>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="order-item mb-3 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong>
                                            <div class="text-muted"><?php echo htmlspecialchars($order['full_name']); ?></div>
                                        </div>
                                        <span class="badge bg-<?php echo $order['status'] == 'pending' ? 'warning' : ($order['status'] == 'confirmed' ? 'success' : 'primary'); ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-end mt-2">
                                        <div>
                                            <small class="text-muted"><?php echo formatDate($order['created_at']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold"><?php echo formatCurrency($order['total_amount']); ?></div>
                                            <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-secondary">Detail</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Aksi Cepat</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="product-add.php" class="btn btn-primary">
                            <i class='bx bx-plus'></i> Tambah Produk
                        </a>
                        <a href="user-add.php" class="btn btn-success">
                            <i class='bx bx-user-plus'></i> Tambah User
                        </a>
                        <a href="orders.php?status=pending" class="btn btn-warning">
                            <i class='bx bx-time'></i> Pesanan Pending
                        </a>
                        <a href="products.php?filter=low_stock" class="btn btn-danger">
                            <i class='bx bx-error'></i> Stok Menipis
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footers-admin.php';?>

<script>
    // Initialize DataTable
    $(document).ready(function() {
        initializeCharts();
        // ... your existing DataTable and other initialization code

        if (!$.fn.DataTable.isDataTable("#topProductsTable")) {
            $("#topProductsTable").DataTable({
                "responsive": true,
                "paging": true,
                "ordering": true,
                "columnDefs": [
                    { "orderable": false, "targets": "no-sort" }
                ],
                "order": [],  // Remove default ordering
                "language": {
                    "emptyTable": "No Data to Display"
                },
                "info": false,
                "scrollX": false,
                "searching": true,
                "lengthChange": true,
                "fixedHeader": true,
                "initComplete": function (settings, json) {
                    // Wrap table for horizontal scrolling
                    $("#topProductsTable").wrap("<div style='overflow:auto; width:100%; position:relative;'></div>");

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

        updateTime();
        setInterval(updateTime, 60000); // Update every minute
    });

    // Update current time
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

    function initializeCharts() {
        // Monthly Sales Chart
        const monthlySalesData = <?php echo json_encode($monthly_sales); ?>;
        const hasMonthlyData = monthlySalesData && monthlySalesData.length > 0;
        
        if (hasMonthlyData) {
            monthlySalesChart = Highcharts.chart('monthlySalesChart', {
                chart: {
                    type: 'line',
                    height: 300
                },
                title: {
                    text: null
                },
                xAxis: {
                    categories: monthlySalesData.map(item => item.month)
                },
                yAxis: {
                    title: {
                        text: 'Total Penjualan'
                    }
                },
                tooltip: {
                    valuePrefix: 'Rp ',
                    valueDecimals: 2
                },
                series: [{
                    name: 'Penjualan',
                    data: monthlySalesData.map(item => parseFloat(item.total) || 0),
                    color: '#006A4E',
                    lineWidth: 3,
                    marker: {
                        radius: 5
                    }
                }],
                credits: {
                    enabled: false
                },
                legend: {
                    enabled: false
                }
            });
        } else {
            displayNoDataMessage('monthlySalesChart', 'No Monthly Sales Data Available');
        }

        // Order Status Chart
        const orderStatusData = <?php echo json_encode($order_status_data); ?>;
        const hasOrderStatusData = orderStatusData && orderStatusData.length > 0;
        
        if (hasOrderStatusData) {
            const chartData = orderStatusData.map(status => ({
                name: status.status.charAt(0).toUpperCase() + status.status.slice(1),
                y: parseInt(status.count) || 0,
                color: getStatusColor(status.status)
            }));

            orderStatusChart = Highcharts.chart('orderStatusChart', {
                chart: {
                    type: 'pie',
                    height: 300
                },
                title: {
                    text: null
                },
                tooltip: {
                    pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>'
                },
                plotOptions: {
                    pie: {
                        allowPointSelect: true,
                        cursor: 'pointer',
                        dataLabels: {
                            enabled: true,
                            format: '<b>{point.name}</b>: {point.percentage:.1f} %'
                        }
                    }
                },
                series: [{
                    name: 'Status',
                    colorByPoint: true,
                    data: chartData
                }],
                credits: {
                    enabled: false
                }
            });
        } else {
            displayNoDataMessage('orderStatusChart', 'No Order Status Data Available');
        }
    }

    function getStatusColor(status) {
        const colors = {
            'pending': '#F59E0B',
            'confirmed': '#03C03C',
            'cancelled': '#EF4444',
            'processing': '#3B82F6',
            'shipped': '#8B5CF6',
            'delivered': '#10B981',
            'default': '#6B7280'
        };
        return colors[status] || colors.default;
    }

    function displayNoDataMessage(containerId, message) {
        document.getElementById(containerId).innerHTML = `
            <div class="d-flex align-items-center justify-content-center h-100" style="min-height: 300px;">
                <div class="text-center">
                    <i class="bx bx-bar-chart-alt-2" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                    <p class="text-muted mb-0">${message}</p>
                </div>
            </div>
        `;
    }

    // Function to update chart data
    function updateChartData(newMonthlySales, newOrderStatus) {
        // Update Monthly Sales Chart
        if (newMonthlySales && newMonthlySales.length > 0) {
            if (monthlySalesChart) {
                monthlySalesChart.xAxis[0].setCategories(newMonthlySales.map(item => item.month));
                monthlySalesChart.series[0].setData(newMonthlySales.map(item => parseFloat(item.total) || 0));
            } else {
                // Recreate chart if it was previously showing "No Data"
                $('#monthlySalesChart').empty();
                initializeCharts();
            }
        } else {
            if (monthlySalesChart) {
                monthlySalesChart.destroy();
                monthlySalesChart = null;
            }
            displayNoDataMessage('monthlySalesChart', 'No Monthly Sales Data Available');
        }

        // Update Order Status Chart
        if (newOrderStatus && newOrderStatus.length > 0) {
            const chartData = newOrderStatus.map(status => ({
                name: status.status.charAt(0).toUpperCase() + status.status.slice(1),
                y: parseInt(status.count) || 0,
                color: getStatusColor(status.status)
            }));

            if (orderStatusChart) {
                orderStatusChart.series[0].setData(chartData);
            } else {
                // Recreate chart if it was previously showing "No Data"
                $('#orderStatusChart').empty();
                initializeCharts();
            }
        } else {
            if (orderStatusChart) {
                orderStatusChart.destroy();
                orderStatusChart = null;
            }
            displayNoDataMessage('orderStatusChart', 'No Order Status Data Available');
        }
    }

    // Auto refresh data every 30 seconds
    setInterval(function() {
        $.ajax({
            url: 'dashboard.php',
            type: 'GET',
            dataType: 'html',
            success: function(data) {
                console.log(data);
                // Parse the response and update specific elements
                // This is a simplified version - in a real app you'd want to update specific components
                updateChartData(data.monthly_sales, data.order_status_data);
                $('.main-content').html($(data).find('.main-content').html());
                
                // Show notification
                Swal.fire({
                    icon: 'success',
                    title: 'Data diperbarui',
                    showConfirmButton: false,
                    timer: 800
                });
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal memuat data terbaru',
                    showConfirmButton: false,
                    timer: 800
                });
            }
        });
    }, 30000);
</script>
</body>
</html>