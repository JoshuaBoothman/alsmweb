<?php
session_start();
require_once '../config/db_config.php';
require_once '../vendor/autoload.php';

// Security Check: Ensure user is a logged-in administrator
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

$payments = [];
$error_message = '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL query to get all successful payments, joining with users and orders tables
    // to get customer names and order details.
    $sql = "SELECT
                p.payment_id,
                p.gateway_transaction_id,
                p.amount,
                p.currency,
                p.payment_status,
                p.payment_date,
                o.order_id,
                u.username
            FROM
                payments p
            JOIN
                orders o ON p.order_id = o.order_id
            JOIN
                users u ON p.user_id = u.user_id
            WHERE
                p.payment_status = 'successful'
            ORDER BY
                p.payment_date DESC";

    $stmt = $pdo->query($sql);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch payments. " . $e->getMessage();
}

$page_title = 'View Payments - Admin';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">View All Payments</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Payment ID</th>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
                <th>Stripe Transaction ID</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($payments)): ?>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= htmlspecialchars($payment['payment_id']) ?></td>
                        <td>
                            <a href="manage_orders.php?id=<?= $payment['order_id'] ?>"><?= htmlspecialchars($payment['order_id']) ?></a>
                        </td>
                        <td><?= htmlspecialchars($payment['username']) ?></td>
                        <td>$<?= htmlspecialchars(number_format($payment['amount'], 2)) ?> <?= strtoupper(htmlspecialchars($payment['currency'])) ?></td>
                        <td><span class="badge bg-success"><?= ucfirst(htmlspecialchars($payment['payment_status'])) ?></span></td>
                        <td><?= htmlspecialchars(date('d M Y, g:i A', strtotime($payment['payment_date']))) ?></td>
                        <td>
                            <a href="https://dashboard.stripe.com/test/payments/<?= htmlspecialchars($payment['gateway_transaction_id']) ?>" target="_blank">
                                <?= htmlspecialchars($payment['gateway_transaction_id']) ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">No successful payments found yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="text-end">
         <a href="/alsmweb/admin/index.php" class="btn btn-secondary">&laquo; Back to Admin Dashboard</a>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
