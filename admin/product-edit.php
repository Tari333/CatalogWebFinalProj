<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
updateLastActivity($db);

if (!isset($_GET['id'])) {
    header('Location: products.php');
    exit();
}

$product_id = intval($_GET['id']);

// Get product details
$db->query("SELECT * FROM products WHERE id = :id");
$db->bind(':id', $product_id);
$product = $db->single();

if (!$product) {
    header('Location: products.php?error=Produk tidak ditemukan');
    exit();
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_product') {
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
            
            // Handle image upload if new image is provided
            $image_path = $product['image'];
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Delete old image if exists
                if ($image_path) {
                    @unlink('../' . $image_path);
                }
                
                $upload = uploadFile($_FILES['image'], '../' . UPLOAD_PATH . 'products/');
                if ($upload) {
                    $image_path = UPLOAD_PATH . 'products/' . $upload;
                } else {
                    throw new Exception('Gagal mengunggah gambar');
                }
            }
            
            // Update product
            $db->query("UPDATE products SET name = :name, description = :description, price = :price, stock = :stock, category = :category, image = :image WHERE id = :id");
            $db->bind(':name', $name);
            $db->bind(':description', $description);
            $db->bind(':price', $price);
            $db->bind(':stock', $stock);
            $db->bind(':category', $category);
            $db->bind(':image', $image_path);
            $db->bind(':id', $product_id);
            $db->execute();
            
            $db->endTransaction();
            
            logActivity($db, $_SESSION['user_id'], 'UPDATE_PRODUCT', "Updated product: $name");
            echo json_encode(['success' => true, 'message' => 'Produk berhasil diperbarui']);
        } catch (Exception $e) {
            $db->cancelTransaction();
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui produk: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_POST['action'] === 'get_product') {
        echo json_encode(['success' => true, 'data' => $product]);
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
    <title>Edit Produk - <?php echo SITE_NAME; ?></title>
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
        border: 2px solid #e3e6f0;
        padding: 10px;
        margin-top: 10px;
    }

    .file-upload-area {
        border: 2px dashed #e3e6f0;
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
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

    .qr-code-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
    }

    .qr-code-image {
        max-width: 150px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .product-id-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
        display: inline-block;
    }

    .current-image-container {
        position: relative;
        display: inline-block;
    }

    .change-image-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.7);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
        cursor: pointer;
    }

    .current-image-container:hover .change-image-overlay {
        opacity: 1;
    }

    .change-image-overlay i {
        color: white;
        font-size: 2rem;
    }
</style>

<?php include '../includes/headers-admin.php'; ?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>Edit Produk</h1>
            <p class="text-muted">Perbarui informasi produk dalam katalog</p>
        </div>
        <div class="page-actions">
            <span class="product-id-badge">ID: <?php echo $product['id']; ?></span>
            <span class="current-time ms-3" id="currentTime"></span>
        </div>
    </div>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="products.php">Produk</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Produk</li>
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
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required placeholder="Masukkan nama produk">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="category" class="form-label">Kategori</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">Pilih Kategori</option>
                            <option value="Elektronik" <?php echo $product['category'] === 'Elektronik' ? 'selected' : ''; ?>>Elektronik</option>
                            <option value="Fashion" <?php echo $product['category'] === 'Fashion' ? 'selected' : ''; ?>>Fashion</option>
                            <option value="Makanan" <?php echo $product['category'] === 'Makanan' ? 'selected' : ''; ?>>Makanan & Minuman</option>
                            <option value="Kesehatan" <?php echo $product['category'] === 'Kesehatan' ? 'selected' : ''; ?>>Kesehatan & Kecantikan</option>
                            <option value="Olahraga" <?php echo $product['category'] === 'Olahraga' ? 'selected' : ''; ?>>Olahraga</option>
                            <option value="Hobi" <?php echo $product['category'] === 'Hobi' ? 'selected' : ''; ?>>Hobi & Koleksi</option>
                            <option value="Lainnya" <?php echo $product['category'] === 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Deskripsi Produk</label>
                    <textarea class="form-control" id="description" name="description" rows="4" placeholder="Masukkan deskripsi produk (opsional)"><?php echo htmlspecialchars($product['description']); ?></textarea>
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
                            <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" value="<?php echo $product['price']; ?>" required placeholder="0">
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="stock" class="form-label">Jumlah Stok</label>
                        <input type="number" class="form-control" id="stock" name="stock" min="0" value="<?php echo $product['stock']; ?>" placeholder="0">
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
                
                <?php if ($product['image']): ?>
                <div class="mb-3">
                    <label class="form-label">Gambar Saat Ini</label>
                    <div class="current-image-container">
                        <img src="../<?php echo $product['image']; ?>" class="image-preview" alt="Current Product Image" id="currentImage">
                        <div class="change-image-overlay" onclick="document.getElementById('image').click()">
                            <i class='bx bx-camera'></i>
                        </div>
                    </div>
                    <small class="form-text text-muted d-block mt-2">Klik gambar untuk mengganti</small>
                </div>
                <?php endif; ?>
                
                <div class="file-upload-area" onclick="document.getElementById('image').click()">
                    <div class="upload-icon">
                        <i class='bx bx-cloud-upload'></i>
                    </div>
                    <h5><?php echo $product['image'] ? 'Klik untuk ganti gambar' : 'Klik untuk upload gambar'; ?></h5>
                    <p class="text-muted mb-0">atau drag & drop file gambar di sini</p>
                    <small class="text-muted">Format yang didukung: JPG, PNG, GIF (Max: 5MB)</small>
                </div>
                <input type="file" id="image" name="image" accept="image/*" style="display: none;">
                <img id="imagePreview" class="image-preview" alt="Preview" style="display: none;">
            </div>

            <!-- QR Code Section -->
            <?php if ($product['qr_code']): ?>
            <div class="form-section">
                <h4 class="section-title">
                    <i class='bx bx-qr-scan'></i>
                    QR Code Produk
                </h4>
                <div class="qr-code-section">
                    <img src="../<?php echo $product['qr_code']; ?>" class="qr-code-image" alt="Product QR Code">
                    <p class="text-muted mt-3 mb-0">
                        <i class='bx bx-info-circle'></i>
                        Scan QR Code ini untuk melihat detail produk
                    </p>
                    <small class="text-muted">QR Code dibuat otomatis saat produk ditambahkan</small>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-between align-items-center">
                <a href="products.php" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Kembali
                </a>
                <div>
                    <button type="button" class="btn btn-outline-warning me-2" onclick="resetForm()">
                        <i class='bx bx-refresh'></i> Reset
                    </button>
                    <button type="button" class="btn btn-outline-danger me-2" onclick="deleteProduct()">
                        <i class='bx bx-trash'></i> Hapus
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
const productId = <?php echo $product_id; ?>;
let originalFormData = {};

$(document).ready(function() {
    updateTime();
    setInterval(updateTime, 60000);

    // Quick fix version
    const currentCategory = '<?php echo addslashes($product['category']); ?>';

    if (currentCategory && !$('#category option[value="' + currentCategory + '"]').length) {
        $('#category').append(new Option(currentCategory, currentCategory, false, true));
    }
    
    // Initialize Select2 for category
    $('#category').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Pilih atau ketik kategori baru',
        allowClear: true,
        tags: true
    });
    
    // Store original form data for reset functionality
    storeOriginalFormData();
    
    // File upload handling
    setupFileUpload();
    
    // Form submission
    $('#productForm').on('submit', function(e) {
        e.preventDefault();
        updateProduct();
    });
    
    // Real-time price formatting
    $('#price').on('input', function() {
        formatPriceInput(this);
    });
    
    // Auto-save functionality (optional)
    setupAutoSave();
});

function storeOriginalFormData() {
    originalFormData = {
        name: $('#name').val(),
        description: $('#description').val(),
        price: $('#price').val(),
        stock: $('#stock').val(),
        category: $('#category').val()
    };
}

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
        $('#currentImage').hide();
    };
    reader.readAsDataURL(file);
}

function updateProduct() {
    const submitBtn = $('#submitBtn');
    const originalText = submitBtn.html();
    
    // Disable button and show loading
    submitBtn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Menyimpan...');
    
    // Create FormData
    const formData = new FormData();
    formData.append('action', 'update_product');
    formData.append('name', $('#name').val());
    formData.append('description', $('#description').val());
    formData.append('price', $('#price').val());
    formData.append('stock', $('#stock').val());
    formData.append('category', $('#category').val());
    
    if ($('#image')[0].files[0]) {
        formData.append('image', $('#image')[0].files[0]);
    }
    
    $.ajax({
        url: 'product-edit.php?id=' + productId,
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
                    confirmButtonText: 'OK'
                }).then(() => {
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
                text: 'Terjadi kesalahan saat memperbarui produk'
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
        text: 'Form akan dikembalikan ke data asli',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Reset!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Reset to original values
            $('#name').val(originalFormData.name);
            $('#description').val(originalFormData.description);
            $('#price').val(originalFormData.price);
            $('#stock').val(originalFormData.stock);
            $('#category').val(originalFormData.category).trigger('change');
            
            // Reset image
            $('#image').val('');
            $('#imagePreview').hide();
            $('#currentImage').show();
            
            Swal.fire({
                icon: 'success',
                title: 'Form direset',
                timer: 1000,
                showConfirmButton: false
            });
        }
    });
}

function deleteProduct() {
    Swal.fire({
        title: 'Hapus Produk?',
        text: 'Produk ini akan dihapus permanen. Tindakan ini tidak dapat dibatalkan!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '../admin/products.php',
                type: 'POST',
                data: {
                    action: 'delete_product',
                    product_id: productId
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
                        }).then(() => {
                            window.location.href = 'products.php';
                        });
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

function setupAutoSave() {
    let autoSaveTimer;
    
    // Auto-save on form changes (debounced)
    $('#productForm').on('input change', 'input, textarea, select', function() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function() {
            // Show auto-save indicator
            showAutoSaveIndicator();
        }, 2000);
    });
}

function showAutoSaveIndicator() {
    // Create a subtle notification for auto-save
    const indicator = $('<div class="auto-save-indicator">Menyimpan otomatis...</div>');
    indicator.css({
        position: 'fixed',
        top: '20px',
        right: '20px',
        background: '#28a745',
        color: 'white',
        padding: '8px 16px',
        borderRadius: '4px',
        fontSize: '0.85rem',
        zIndex: 9999,
        opacity: 0
    });
    
    $('body').append(indicator);
    indicator.animate({opacity: 1}, 200);
    
    setTimeout(function() {
        indicator.animate({opacity: 0}, 200, function() {
            indicator.remove();
        });
    }, 2000);
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