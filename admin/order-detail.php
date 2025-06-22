<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
updateLastActivity($db);

if (!isset($_GET['id'])) {
    header('Location: orders.php');
    exit();
}

$order_id = intval($_GET['id']);

// Get order details
$db->query("SELECT o.*, u.username, u.full_name, u.email, u.phone, u.address FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = :id");
$db->bind(':id', $order_id);
$order = $db->single();

if (!$order) {
    header('Location: orders.php?error=Pesanan tidak ditemukan');
    exit();
}

// Get order items
$db->query("SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = :order_id");
$db->bind(':order_id', $order_id);
$items = $db->resultset();

// Handle status update
if (isset($_POST['update_status'])) {
    $new_status = sanitize($_POST['status']);
    
    $db->query("UPDATE orders SET status = :status WHERE id = :id");
    $db->bind(':status', $new_status);
    $db->bind(':id', $order_id);
    $db->execute();
    
    logActivity($db, $_SESSION['user_id'], 'UPDATE_ORDER_STATUS', "Order ID: $order_id, New Status: $new_status");
    
    // Refresh order data
    $db->query("SELECT o.*, u.username, u.full_name, u.email, u.phone, u.address FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = :id");
    $db->bind(':id', $order_id);
    $order = $db->single();
}

// Handle payment verification
if (isset($_POST['verify_payment'])) {
    $verification_status = sanitize($_POST['payment_status']);
    
    $db->query("UPDATE orders SET payment_status = :status WHERE id = :id");
    $db->bind(':status', $verification_status);
    $db->bind(':id', $order_id);
    $db->execute();
    
    logActivity($db, $_SESSION['user_id'], 'UPDATE_PAYMENT_STATUS', "Order ID: $order_id, Payment Status: $verification_status");
    
    // Refresh order data
    $db->query("SELECT o.*, u.username, u.full_name, u.email, u.phone, u.address FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = :id");
    $db->bind(':id', $order_id);
    $order = $db->single();
}

// Handle notes update
if (isset($_POST['update_notes'])) {
    $notes = sanitize($_POST['notes']);
    
    $db->query("UPDATE orders SET notes = :notes WHERE id = :id");
    $db->bind(':notes', $notes);
    $db->bind(':id', $order_id);
    $db->execute();
    
    logActivity($db, $_SESSION['user_id'], 'UPDATE_ORDER_NOTES', "Order ID: $order_id");
    
    // Refresh order data
    $db->query("SELECT o.*, u.username, u.full_name, u.email, u.phone, u.address FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = :id");
    $db->bind(':id', $order_id);
    $order = $db->single();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo $order['order_number']; ?> - <?php echo SITE_NAME; ?></title>
</head>
<body>
    <h1>Detail Pesanan #<?php echo $order['order_number']; ?></h1>
    
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="products.php">Produk</a></li>
        <li><a href="orders.php">Pesanan</a></li>
        <li><a href="users.php">Pengguna</a></li>
        <li><a href="activity-logs.php">Log Aktivitas</a></li>
        <li><a href="profile.php">Profil</a></li>
        <li><a href="../auth/logout.php">Logout</a></li>
    </ul>
    
    <div>
        <h3>Informasi Pesanan</h3>
        <table border="1">
            <tr>
                <th>No. Pesanan</th>
                <td><?php echo $order['order_number']; ?></td>
            </tr>
            <tr>
                <th>Tanggal</th>
                <td><?php echo formatDate($order['created_at']); ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td><?php echo ucfirst($order['status']); ?></td>
            </tr>
            <tr>
                <th>Status Pembayaran</th>
                <td><?php echo ucfirst($order['payment_status']); ?></td>
            </tr>
            <tr>
                <th>Total</th>
                <td><?php echo formatCurrency($order['total_amount']); ?></td>
            </tr>
            <?php if ($order['qr_code']): ?>
            <tr>
                <th>QR Code Pesanan</th>
                <td><img src="../<?php echo $order['qr_code']; ?>" alt="Order QR Code" width="100"></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <h3>Informasi Pelanggan</h3>
        <table border="1">
            <tr>
                <th>Nama</th>
                <td><?php echo htmlspecialchars($order['full_name']); ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?php echo htmlspecialchars($order['email']); ?></td>
            </tr>
            <tr>
                <th>Telepon</th>
                <td><?php echo htmlspecialchars($order['phone']); ?></td>
            </tr>
            <tr>
                <th>Alamat</th>
                <td><?php echo nl2br(htmlspecialchars($order['address'])); ?></td>
            </tr>
        </table>
        
        <h3>Item Pesanan</h3>
        <table border="1">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Harga</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($item['name']); ?>
                            <?php if ($item['image']): ?>
                                <br><img src="../<?php echo $item['image']; ?>" width="50">
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatCurrency($item['price']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo formatCurrency($item['subtotal']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                    <td><?php echo formatCurrency($order['total_amount']); ?></td>
                </tr>
            </tbody>
        </table>
        
        <?php if ($order['payment_proof']): ?>
            <h3>Bukti Pembayaran</h3>
            <img src="../<?php echo $order['payment_proof']; ?>" alt="Bukti Pembayaran" style="max-width: 500px;">
        <?php endif; ?>
        
        <h3>Catatan</h3>
        <form method="POST">
            <textarea name="notes" rows="4"><?php echo htmlspecialchars($order['notes']); ?></textarea>
            <button type="submit" name="update_notes">Simpan Catatan</button>
        </form>
        
        <?php if ($order['payment_proof'] && $order['payment_status'] == 'pending'): ?>
            <h3>Verifikasi Pembayaran</h3>
            <form method="POST">
                <select name="payment_status">
                    <option value="verified">Verifikasi</option>
                    <option value="rejected">Tolak</option>
                </select>
                <button type="submit" name="verify_payment">Submit</button>
            </form>
        <?php endif; ?>
        
        <h3>Ubah Status Pesanan</h3>
        <form method="POST">
            <select name="status">
                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $order['status'] == 'confirmed' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Diproses</option>
                <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Dikirim</option>
                <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Diterima</option>
                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
            </select>
            <button type="submit" name="update_status">Update Status</button>
        </form>
        
        <div>
            <a href="orders.php">Kembali ke Daftar Pesanan</a>
        </div>
    </div>
</body>
</html>