<?php
// admin/manage_orders.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// --- DATA FETCHING ---
$orders = [];
$error_message = '';

try {
    // We join the orders and users table to get the customer's username
    $sql = "SELECT 
                o.order_id, o.order_date, o.total_amount, o.order_status, 
                u.username 
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            ORDER BY 
                CASE o.order_status
                    WHEN 'pending_payment' THEN 1
                    WHEN 'paid' THEN 2
                    ELSE 3
                END, 
                o.order_date DESC";

    $stmt = $pdo->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch orders. " . $e->getMessage();
}

// Generate a token for the forms
generate_csrf_token();

// --- HEADER ---
$page_title = 'Manage Orders';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">Manage Customer Orders</h1>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if ($error_message) {
        echo '<div class="alert alert-danger">' . $error_message . '</div>';
    }
    ?>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                            <td><?= htmlspecialchars($order['username']) ?></td>
                            <td><?= date('d M Y, H:i', strtotime($order['order_date'])) ?></td>
                            <td>$<?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></td>
                            <td>
                                <span class="badge 
                                    <?= $order['order_status'] === 'pending_payment' ? 'bg-warning' : 'bg-success' ?>">
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $order['order_status']))) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($order['order_status'] === 'pending_payment'): ?>
                                    <form action="update_order_status.php" method="POST" onsubmit="return confirm('Are you sure you have received payment for this order?');">
                                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Mark as Paid</button>
                                    </form>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No orders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>