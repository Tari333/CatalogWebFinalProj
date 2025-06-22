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
        case 'delete_product':
            $product_id = intval($_POST['product_id']);
            
            $db->query("SELECT image, qr_code FROM products WHERE id = :id");
            $db->bind(':id', $product_id);
            $product = $db->single();
            
            // Delete product image and QR code if exists
            if ($product['image']) {
                @unlink('../' . $product['image']);
            }
            if ($product['qr_code']) {
                @unlink('../' . $product['qr_code']);
            }
            
            // Delete product from database
            $db->query("DELETE FROM products WHERE id = :id");
            $db->bind(':id', $product_id);
            $success = $db->execute();
            
            if ($success) {
                logActivity($db, $_SESSION['user_id'], 'DELETE_PRODUCT', "Deleted product ID: $product_id");
                echo json_encode(['success' => true, 'message' => 'Produk berhasil dihapus']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus produk']);
            }
            exit();
            
        case 'toggle_status':
            $product_id = intval($_POST['product_id']);
            
            $db->query("UPDATE products SET status = IF(status='active','inactive','active') WHERE id = :id");
            $db->bind(':id', $product_id);
            $success = $db->execute();
            
            if ($success) {
                logActivity($db, $_SESSION['user_id'], 'UPDATE_PRODUCT', "Toggled product status ID: $product_id");
                echo json_encode(['success' => true, 'message' => 'Status produk berhasil diubah']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mengubah status produk']);
            }
            exit();
            
        case 'get_products':
            $db->query("SELECT * FROM products ORDER BY created_at DESC");
            $products = $db->resultset();
            echo json_encode(['success' => true, 'data' => $products]);
            exit();
            
        case 'get_product_stats':
            // Get product statistics
            $db->query("SELECT COUNT(*) as total FROM products WHERE status = 'active'");
            $active_products = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM products WHERE status = 'inactive'");
            $inactive_products = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM products WHERE stock = 0");
            $out_of_stock = $db->single()['total'];
            
            $db->query("SELECT COUNT(*) as total FROM products WHERE stock < 10 AND stock > 0");
            $low_stock = $db->single()['total'];
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'active' => $active_products,
                    'inactive' => $inactive_products,
                    'out_of_stock' => $out_of_stock,
                    'low_stock' => $low_stock,
                    'total' => $active_products + $inactive_products
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
    <title>Manajemen Produk - <?php echo SITE_NAME; ?></title>
</head>
<body>

<style>
    .product-stats-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }

    .product-stats-card:hover {
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

    .product-image {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 8px;
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

    .stock-low {
        color: #e17055;
        font-weight: 600;
    }

    .stock-out {
        color: #d63031;
        font-weight: 600;
    }

    .stock-normal {
        color: #00b894;
        font-weight: 600;
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
</style>

<?php include '../includes/headers-admin.php'; ?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>Manajemen Produk</h1>
            <p class="text-muted">Kelola semua produk dalam sistem katalog</p>
        </div>
        <div class="page-actions">
            <span class="current-time" id="currentTime"></span>
        </div>
    </div>

    <!-- Product Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="product-stats-card">
                <div class="stats-icon bg-primary">
                    <i class='bx bx-package'></i>
                </div>
                <div>
                    <h3 id="totalProducts" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Total Produk</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="product-stats-card">
                <div class="stats-icon bg-success">
                    <i class='bx bx-check-circle'></i>
                </div>
                <div>
                    <h3 id="activeProducts" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Produk Aktif</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="product-stats-card">
                <div class="stats-icon bg-warning">
                    <i class='bx bx-error-circle'></i>
                </div>
                <div>
                    <h3 id="lowStockProducts" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Stok Menipis</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="product-stats-card">
                <div class="stats-icon bg-danger">
                    <i class='bx bx-x-circle'></i>
                </div>
                <div>
                    <h3 id="outOfStockProducts" class="mb-1">0</h3>
                    <p class="text-muted mb-0">Stok Habis</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Aksi Cepat</h5>
            <div class="quick-actions">
                <a href="product-add.php" class="btn btn-primary">
                    <i class='bx bx-plus'></i> Tambah Produk Baru
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

    <!-- Products Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Daftar Produk</h5>
                <div>
                    <span class="text-muted">Last updated: </span>
                    <span id="lastUpdated" class="text-primary">-</span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover" id="productsTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>Gambar</th>
                            <th>ID</th>
                            <th>Nama Produk</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Status</th>
                            <th>Tanggal Dibuat</th>
                            <th class="no-sort">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody">
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footers-admin.php'; ?>

<script>
    let productsTable;
    let selectedProducts = [];

    $(document).ready(function() {
        initializeTable();
        loadProductStats();
        loadProducts();
        updateTime();
        setInterval(updateTime, 60000);
        setInterval(refreshData, 30000); // Auto refresh every 30 seconds
    });

    function initializeTable() {
        if (!$.fn.DataTable.isDataTable("#productsTable")) {
            productsTable = $("#productsTable").DataTable({
                "responsive": true,
                "paging": true,
                "pageLength": 25,
                "ordering": true,
                "searching": true,
                "lengthChange": true,
                "info": true,
                "autoWidth": false,
                "columnDefs": [
                    { "orderable": false, "targets": [0, 1, 8] }, // checkbox, image, actions columns
                    { "width": "5%", "targets": 0 },
                    { "width": "8%", "targets": 1 },
                    { "width": "5%", "targets": 2 },
                    { "width": "25%", "targets": 3 },
                    { "width": "12%", "targets": 4 },
                    { "width": "8%", "targets": 5 },
                    { "width": "10%", "targets": 6 },
                    { "width": "12%", "targets": 7 },
                    { "width": "15%", "targets": 8 }
                ],
                "order": [[2, 'desc']], // Order by ID descending
                "language": {
                    "emptyTable": "Belum ada produk yang tersedia",
                    "zeroRecords": "Tidak ada produk yang cocok dengan pencarian",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ produk",
                    "infoEmpty": "Menampilgi 0 sampai 0 dari 0 produk",
                    "infoFiltered": "(difilter dari _MAX_ total produk)",
                    "lengthMenu": "Tampilkan _MENU_ produk per halaman",
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
                            // Skip checkbox, image, and actions columns
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

    function loadProductStats() {
        $.ajax({
            url: 'products.php',
            type: 'POST',
            data: { action: 'get_product_stats' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#totalProducts').text(response.stats.total);
                    $('#activeProducts').text(response.stats.active);
                    $('#lowStockProducts').text(response.stats.low_stock);
                    $('#outOfStockProducts').text(response.stats.out_of_stock);
                }
            }
        });
    }

    function loadProducts() {
        $.ajax({
            url: 'products.php',
            type: 'POST',
            data: { action: 'get_products' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    updateProductsTable(response.data);
                    updateLastUpdated();
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Gagal memuat data produk'
                });
            }
        });
    }

    function updateProductsTable(products) {
        if (productsTable) {
            productsTable.clear();
            
            products.forEach(function(product) {
                const imageHtml = product.image ? 
                    `<img src="../${product.image}" class="product-image" alt="${product.name}">` : 
                    '<div class="product-image bg-light d-flex align-items-center justify-content-center"><i class="bx bx-image text-muted"></i></div>';
                
                const statusHtml = `<span class="status-badge ${product.status === 'active' ? 'status-active' : 'status-inactive'}">
                    ${product.status === 'active' ? 'Aktif' : 'Nonaktif'}
                </span>`;
                
                let stockClass = 'stock-normal';
                if (product.stock === 0) stockClass = 'stock-out';
                else if (product.stock < 10) stockClass = 'stock-low';
                
                const stockHtml = `<span class="${stockClass}">${product.stock}</span>`;
                
                const actionsHtml = `
                    <div class="action-buttons">
                        <button class="btn-action btn-edit" onclick="editProduct(${product.id})" title="Edit">
                            <i class='bx bx-edit'></i>
                        </button>
                        <button class="btn-action btn-toggle" onclick="toggleProductStatus(${product.id})" title="Toggle Status">
                            <i class='bx ${product.status === 'active' ? 'bx-toggle-right' : 'bx-toggle-left'}'></i>
                        </button>
                        <button class="btn-action btn-delete" onclick="deleteProduct(${product.id})" title="Hapus">
                            <i class='bx bx-trash'></i>
                        </button>
                    </div>
                `;
                
                productsTable.row.add([
                    `<input type="checkbox" class="form-check-input product-checkbox" value="${product.id}">`,
                    imageHtml,
                    product.id,
                    product.name,
                    formatCurrency(product.price),
                    stockHtml,
                    statusHtml,
                    formatDate(product.created_at),
                    actionsHtml
                ]);
            });
            
            productsTable.draw();
        }
    }

    function editProduct(id) {
        window.location.href = `product-edit.php?id=${id}`;
    }

    function toggleProductStatus(id) {
        Swal.fire({
            title: 'Konfirmasi',
            text: 'Yakin ingin mengubah status produk ini?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Ubah!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'products.php',
                    type: 'POST',
                    data: {
                        action: 'toggle_status',
                        product_id: id
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

    function deleteProduct(id) {
        Swal.fire({
            title: 'Konfirmasi Hapus',
            text: 'Yakin ingin menghapus produk ini? Tindakan ini tidak dapat dibatalkan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'products.php',
                    type: 'POST',
                    data: {
                        action: 'delete_product',
                        product_id: id
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
        loadProductStats();
        loadProducts();
        
        Swal.fire({
            icon: 'success',
            title: 'Data Diperbarui',
            text: 'Data produk berhasil diperbarui',
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
        const checkedBoxes = $('.product-checkbox:checked');
        
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
                title: 'Tidak Ada Produk',
                text: 'Silakan pilih produk terlebih dahulu'
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
        $('.product-checkbox').prop('checked', this.checked);
    });

    $(document).on('change', '.product-checkbox', function() {
        const totalCheckboxes = $('.product-checkbox').length;
        const checkedCheckboxes = $('.product-checkbox:checked').length;
        
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