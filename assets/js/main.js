// assets/js/main.js

// Auto refresh data every 30 seconds
setInterval(function() {
    if (typeof refreshData === 'function') {
        refreshData();
    }
}, 30000);

// Show notification
function showNotification(message, type = 'success') {
    const notification = `
        <div class="alert alert-${type} alert-dismissible fade show notification" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', notification);
    
    // Auto remove after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.notification');
        if (alerts.length > 0) {
            alerts[0].remove();
        }
    }, 5000);
}

// Confirm delete
function confirmDelete(url, message = 'Apakah Anda yakin ingin menghapus data ini?') {
    if (confirm(message)) {
        window.location.href = url;
    }
}

// Toggle product view (catalog/list)
function toggleView(view) {
    const catalogView = document.getElementById('catalog-view');
    const listView = document.getElementById('list-view');
    const catalogBtn = document.getElementById('catalog-btn');
    const listBtn = document.getElementById('list-btn');
    
    if (view === 'catalog') {
        catalogView.style.display = 'block';
        listView.style.display = 'none';
        catalogBtn.classList.add('active');
        listBtn.classList.remove('active');
        localStorage.setItem('productView', 'catalog');
    } else {
        catalogView.style.display = 'none';
        listView.style.display = 'block';
        catalogBtn.classList.remove('active');
        listBtn.classList.add('active');
        localStorage.setItem('productView', 'list');
    }
}

// Load saved view preference
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('productView') || 'catalog';
    if (document.getElementById('catalog-view') && document.getElementById('list-view')) {
        toggleView(savedView);
    }
});

// AJAX functions
function ajaxRequest(url, method = 'GET', data = null, callback = null) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                if (callback) {
                    callback(xhr.responseText);
                }
            } else {
                console.error('AJAX Error: ' + xhr.status);
            }
        }
    };
    
    xhr.send(data);
}

// Update quantity in cart
function updateQuantity(productId, quantity) {
    if (quantity < 1) {
        if (confirm('Hapus produk dari keranjang?')) {
            removeFromCart(productId);
        }
        return;
    }
    
    const data = 'product_id=' + productId + '&quantity=' + quantity + '&action=update';
    ajaxRequest('be-logic/cart.php', 'POST', data, function(response) {
        const result = JSON.parse(response);
        if (result.success) {
            location.reload();
        } else {
            showNotification(result.message, 'danger');
        }
    });
}

// Remove from cart
function removeFromCart(productId) {
    const data = 'product_id=' + productId + '&action=remove';
    ajaxRequest('be-logic/cart.php', 'POST', data, function(response) {
        const result = JSON.parse(response);
        if (result.success) {
            location.reload();
        } else {
            showNotification(result.message, 'danger');
        }
    });
}

// Add to cart
function addToCart(productId, quantity = 1) {
    const data = 'product_id=' + productId + '&quantity=' + quantity + '&action=add';
    ajaxRequest('be-logic/cart.php', 'POST', data, function(response) {
        const result = JSON.parse(response);
        if (result.success) {
            showNotification('Produk berhasil ditambahkan ke keranjang');
            updateCartCounter();
        } else {
            showNotification(result.message, 'danger');
        }
    });
}

// Update cart counter
function updateCartCounter() {
    ajaxRequest('be-logic/cart.php?action=count', 'GET', null, function(response) {
        const result = JSON.parse(response);
        const counter = document.getElementById('cart-counter');
        if (counter) {
            counter.textContent = result.count;
            counter.style.display = result.count > 0 ? 'inline' : 'none';
        }
    });
}

// Initialize cart counter on page load
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('cart-counter')) {
        updateCartCounter();
    }
});

// QR Scanner functions
function startQRScanner() {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function(stream) {
                const video = document.getElementById('qr-video');
                video.srcObject = stream;
                video.play();
                
                // Start scanning (this would need a QR code library like jsQR)
                scanQRCode(video);
            })
            .catch(function(err) {
                console.error('Error accessing camera: ', err);
                showNotification('Tidak dapat mengakses kamera', 'danger');
            });
    } else {
        showNotification('Browser tidak mendukung akses kamera', 'danger');
    }
}

function scanQRCode(video) {
    // This would implement QR code scanning logic
    // For now, we'll just show a placeholder
    showNotification('Fitur QR Scanner akan diimplementasikan dengan library jsQR', 'info');
}

// File upload preview
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Format currency
function formatCurrency(amount) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Search functionality
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tr');
    
    input.addEventListener('keyup', function() {
        const filter = input.value.toUpperCase();
        
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            let txtValue = row.textContent || row.innerText;
            
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// Initialize search on page load
document.addEventListener('DOMContentLoaded', function() {
    const searchInputs = document.querySelectorAll('[data-search-table]');
    searchInputs.forEach(function(input) {
        const tableId = input.getAttribute('data-search-table');
        searchTable(input.id, tableId);
    });
});

// Loading state
function showLoading() {
    const loading = document.querySelector('.loading');
    if (loading) {
        loading.style.display = 'block';
    }
}

function hideLoading() {
    const loading = document.querySelector('.loading');
    if (loading) {
        loading.style.display = 'none';
    }
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(function(input) {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Auto-save functionality
function autoSave(formId, url) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(function(input) {
        input.addEventListener('change', function() {
            const formData = new FormData(form);
            formData.append('auto_save', '1');
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Data tersimpan otomatis', 'info');
                }
            })
            .catch(error => {
                console.error('Auto-save error:', error);
            });
        });
    });
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showNotification('Teks berhasil disalin ke clipboard');
    }).catch(function(err) {
        console.error('Gagal menyalin teks: ', err);
    });
}

// Print functionality
function printDiv(divId) {
    const printContent = document.getElementById(divId);
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent.innerHTML;
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}