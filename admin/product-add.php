<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
updateLastActivity($db);

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_product') {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        $category = sanitize($_POST['category']);
        
        // Validate input
        if (empty($name) || empty($price)) {
            echo json_encode(['success' => false, 'message' => 'Nama dan harga harus diisi']);
            exit();
        }
        
        try {
            $db->beginTransaction();
            
            // Handle image upload
            $image_path = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['image'], '../' . UPLOAD_PATH . 'products/');
                if ($upload) {
                    $image_path = UPLOAD_PATH . 'products/' . $upload;
                } else {
                    throw new Exception('Gagal mengunggah gambar');
                }
            }
            
            // Insert product
            $db->query("INSERT INTO products (name, description, price, stock, category, image, status) VALUES (:name, :description, :price, :stock, :category, :image, 'active')");
            $db->bind(':name', $name);
            $db->bind(':description', $description);
            $db->bind(':price', $price);
            $db->bind(':stock', $stock);
            $db->bind(':category', $category);
            $db->bind(':image', $image_path);
            $db->execute();
            
            $product_id = $db->lastInsertId();
            
            // Generate QR Code
            $qr_data = SITE_URL . "/buyer/product-detail.php?id=" . $product_id;
            $qr_filename = 'product_' . $product_id . '_' . time();
            $qr_path = generateQRCode($qr_data, $qr_filename);
            
            // Update product with QR code
            $db->query("UPDATE products SET qr_code = :qr_code WHERE id = :id");
            $db->bind(':qr_code', $qr_path);
            $db->bind(':id', $product_id);
            $db->execute();
            
            $db->endTransaction();
            
            logActivity($db, $_SESSION['user_id'], 'ADD_PRODUCT', "Added product: $name");
            echo json_encode(['success' => true, 'message' => 'Produk berhasil ditambahkan', 'product_id' => $product_id]);
        } catch (Exception $e) {
            $db->cancelTransaction();
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan produk: ' . $e->getMessage()]);
        }
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
    <title>Tambah Produk Baru - <?php echo SITE_NAME; ?></title>
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

    .image-preview {
        max-width: 200px;
        max-height: 200px;
        border-radius: 8px;
        border: 2px dashed #e3e6f0;
        padding: 10px;
        margin-top: 10px;
        display: none;
    }

    .file-upload-area {
        border: 2px dashed #e3e6f0;
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .file-upload-area:hover {
        border-color: #4e73df;
        background-color: #f8f9ff;
    }

    .file-upload-area.dragover {
        border-color: #4e73df;
        background-color: #f0f3ff;
    }

    .upload-icon {
        font-size: 3rem;
        color: #6c757d;
        margin-bottom: 15px;
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

    .progress-container {
        display: none;
        margin-top: 20px;
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
</style>

<?php include '../includes/headers-admin.php'; ?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>Tambah Produk Baru</h1>
            <p class="text-muted">Tambahkan produk baru ke dalam katalog</p>
        </div>
        <div class="page-actions">
            <span class="current-time" id="currentTime"></span>
        </div>
    </div>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="products.php">Produk</a></li>
            <li class="breadcrumb-item active" aria-current="page">Tambah Produk</li>
        </ol>
    </nav>

    <!-- Form Container -->
    <div class="form-container">
        <form id="productForm" enctype="multipart/form-data">
            <!-- Basic Information Section -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class='bx bx-info-circle'></i>
                    Informasi Dasar
                </h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Nama Produk <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="Masukkan nama produk">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="category" class="form-label">Kategori</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">Pilih Kategori</option>
                            <option value="Elektronik">Elektronik</option>
                            <option value="Fashion">Fashion</option>
                            <option value="Makanan">Makanan & Minuman</option>
                            <option value="Kesehatan">Kesehatan & Kecantikan</option>
                            <option value="Olahraga">Olahraga</option>
                            <option value="Hobi">Hobi & Koleksi</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Deskripsi Produk</label>
                    <textarea class="form-control" id="description" name="description" rows="4" placeholder="Masukkan deskripsi produk (opsional)"></textarea>
                </div>
            </div>

            <!-- Pricing & Stock Section -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class='bx bx-money'></i>
                    Harga & Stok
                </h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="price" class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" required placeholder="0">
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="stock" class="form-label">Jumlah Stok</label>
                        <input type="number" class="form-control" id="stock" name="stock" min="0" value="0" placeholder="0">
                        <small class="form-text text-muted">Kosongkan atau isi 0 jika stok tidak terbatas</small>
                    </div>
                </div>
            </div>

            <!-- Image Upload Section -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class='bx bx-image'></i>
                    Gambar Produk
                </h4>
                <div class="file-upload-area" onclick="document.getElementById('image').click()">
                    <div class="upload-icon">
                        <i class='bx bx-cloud-upload'></i>
                    </div>
                    <h5>Klik untuk upload gambar</h5>
                    <p class="text-muted mb-0">atau drag & drop file gambar di sini</p>
                    <small class="text-muted">Format yang didukung: JPG, PNG, GIF (Max: 5MB)</small>
                </div>
                <input type="file" id="image" name="image" accept="image/*" style="display: none;">
                <img id="imagePreview" class="image-preview" alt="Preview">
                
                <!-- Progress Bar -->
                <div class="progress-container">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <small class="text-muted mt-1">Mengupload gambar...</small>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-between align-items-center">
                <a href="products.php" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Kembali
                </a>
                <div>
                    <button type="button" class="btn btn-outline-primary me-2" onclick="resetForm()">
                        <i class='bx bx-refresh'></i> Reset Form
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class='bx bx-save'></i> Simpan Produk
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footers-admin.php'; ?>

<script>
$(document).ready(function() {
    updateTime();
    setInterval(updateTime, 60000);
    
    // Initialize Select2 for category
    $('#category').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Pilih atau ketik kategori baru',
        allowClear: true,
        tags: true
    });
    
    // File upload handling
    setupFileUpload();
    
    // Form submission
    $('#productForm').on('submit', function(e) {
        e.preventDefault();
        submitProduct();
    });
    
    // Real-time price formatting
    $('#price').on('input', function() {
        formatPriceInput(this);
    });
});

function setupFileUpload() {
    const fileUploadArea = $('.file-upload-area');
    const fileInput = $('#image');
    const imagePreview = $('#imagePreview');
    
    // Drag and drop functionality
    fileUploadArea.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });
    
    fileUploadArea.on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });
    
    fileUploadArea.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            fileInput[0].files = files;
            handleFileSelect(files[0]);
        }
    });
    
    // File input change
    fileInput.on('change', function(e) {
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });
}

function handleFileSelect(file) {
    // Validate file type
    if (!file.type.match('image.*')) {
        Swal.fire({
            icon: 'error',
            title: 'File Tidak Valid',
            text: 'Hanya file gambar yang diperbolehkan'
        });
        return;
    }
    
    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
            icon: 'error',
            title: 'File Terlalu Besar',
            text: 'Ukuran file maksimal 5MB'
        });
        return;
    }
    
    // Show preview
    const reader = new FileReader();
    reader.onload = function(e) {
        $('#imagePreview').attr('src', e.target.result).show();
    };
    reader.readAsDataURL(file);
}

function submitProduct() {
    const submitBtn = $('#submitBtn');
    const originalText = submitBtn.html();
    
    // Disable button and show loading
    submitBtn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Menyimpan...');
    
    // Create FormData
    const formData = new FormData();
    formData.append('action', 'add_product');
    formData.append('name', $('#name').val());
    formData.append('description', $('#description').val());
    formData.append('price', $('#price').val());
    formData.append('stock', $('#stock').val());
    formData.append('category', $('#category').val());
    
    if ($('#image')[0].files[0]) {
        formData.append('image', $('#image')[0].files[0]);
    }
    
    $.ajax({
        url: 'product-add.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: response.message,
                    showConfirmButton: true,
                    confirmButtonText: 'Lihat Produk'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'products.php';
                    } else {
                        resetForm();
                    }
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
                text: 'Terjadi kesalahan saat menyimpan produk'
            });
        },
        complete: function() {
            submitBtn.prop('disabled', false).html(originalText);
        }
    });
}

function resetForm() {
    Swal.fire({
        title: 'Reset Form?',
        text: 'Semua data yang telah diisi akan hilang',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Reset!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $('#productForm')[0].reset();
            $('#category').val('').trigger('change');
            $('#imagePreview').hide();
            $('.file-upload-area').removeClass('dragover');
            
            Swal.fire({
                icon: 'success',
                title: 'Form direset',
                timer: 1000,
                showConfirmButton: false
            });
        }
    });
}

function formatPriceInput(input) {
    let value = input.value.replace(/[^0-9.]/g, '');
    input.value = value;
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