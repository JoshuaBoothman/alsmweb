<?php
// admin/sales_report.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';

// --- INITIALIZE VARIABLES ---
$payments = [];
$error_message = '';
$filters = [
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? '',
    'customer' => $_GET['customer'] ?? '',
    'revenue_type' => $_GET['revenue_type'] ?? 'all'
];

// --- DATA FETCHING LOGIC ---
try {
    // Base SQL query
    $sql = "SELECT 
                p.payment_id,
                p.payment_date,
                p.amount,
                p.gateway_transaction_id,
                u.username,
                o.order_id,
                b.booking_id,
                er.registration_id
            FROM payments p
            JOIN users u ON p.user_id = u.user_id
            LEFT JOIN orders o ON p.order_id = o.order_id
            LEFT JOIN bookings b ON p.booking_id = b.booking_id
            LEFT JOIN eventregistrations er ON p.payment_id = er.payment_id
            WHERE p.payment_status = 'successful'";
    
    $params = [];

    // Append filters to the query
    if (!empty($filters['start_date'])) {
        $sql .= " AND p.payment_date >= :start_date";
        $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
    }
    if (!empty($filters['end_date'])) {
        $sql .= " AND p.payment_date <= :end_date";
        $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
    }
    if (!empty($filters['customer'])) {
        $sql .= " AND u.username LIKE :customer";
        $params[':customer'] = '%' . $filters['customer'] . '%';
    }
    if ($filters['revenue_type'] !== 'all') {
        switch ($filters['revenue_type']) {
            case 'merchandise':
                $sql .= " AND p.order_id IS NOT NULL";
                break;
            case 'booking':
                $sql .= " AND p.booking_id IS NOT NULL";
                break;
            case 'registration':
                 $sql .= " AND er.registration_id IS NOT NULL";
                break;
        }
    }

    $sql .= " ORDER BY p.payment_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $raw_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process raw data to create user-friendly details
    foreach ($raw_payments as $payment) {
        $type = 'Unknown';
        $details = 'N/A';

        if (!is_null($payment['order_id'])) {
            $type = 'Merchandise Order';
            $details = 'Order #' . $payment['order_id'];
        } elseif (!is_null($payment['booking_id'])) {
            $type = 'Campsite Booking';
            $details = 'Booking #' . $payment['booking_id'];
        } elseif (!is_null($payment['registration_id'])) {
            $type = 'Event Registration';
            $details = 'Registration #' . $payment['registration_id'];
        }
        
        $payments[] = [
            'payment_id' => $payment['payment_id'],
            'date' => date('d M Y, H:i', strtotime($payment['payment_date'])),
            'customer' => $payment['username'],
            'type' => $type,
            'details' => $details,
            'amount' => $payment['amount'],
            'gateway_id' => $payment['gateway_transaction_id']
        ];
    }

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}


// --- HEADER ---
$page_title = 'Consolidated Sales Report';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Consolidated Sales Report</h1>
        <a href="export_sales_csv.php?<?= http_build_query($filters) ?>" class="btn btn-success">Export to CSV</a>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Filter Form -->
    <div class="card bg-light p-3 mb-4">
        <form action="sales_report.php" method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($filters['start_date']) ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($filters['end_date']) ?>">
            </div>
            <div class="col-md-2">
                <label for="revenue_type" class="form-label">Revenue Type</label>
                <select class="form-select" id="revenue_type" name="revenue_type">
                    <option value="all" <?= $filters['revenue_type'] == 'all' ? 'selected' : '' ?>>All Types</option>
                    <option value="merchandise" <?= $filters['revenue_type'] == 'merchandise' ? 'selected' : '' ?>>Merchandise</option>
                    <option value="booking" <?= $filters['revenue_type'] == 'booking' ? 'selected' : '' ?>>Campsite Booking</option>
                    <option value="registration" <?= $filters['revenue_type'] == 'registration' ? 'selected' : '' ?>>Event Registration</option>
                </select>
            </div>
             <div class="col-md-2">
                <label for="customer" class="form-label">Customer Username</label>
                <input type="text" class="form-control" id="customer" name="customer" value="<?= htmlspecialchars($filters['customer']) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <!-- NEW: Clear Filters Button -->
                <a href="sales_report.php" class="btn btn-outline-secondary w-100 ms-2">Clear</a>
            </div>
        </form>
    </div>

    <!-- Results Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Payment ID</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Revenue Type</th>
                    <th>Details</th>
                    <th>Amount</th>
                    <th>Gateway ID</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($payments)): ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= htmlspecialchars($payment['payment_id']) ?></td>
                            <td><?= htmlspecialchars($payment['date']) ?></td>
                            <td><?= htmlspecialchars($payment['customer']) ?></td>
                            <td><?= htmlspecialchars($payment['type']) ?></td>
                            <td><?= htmlspecialchars($payment['details']) ?></td>
                            <td>$<?= htmlspecialchars(number_format($payment['amount'], 2)) ?></td>
                            <td>
                                <a href="https://dashboard.stripe.com/test/payments/<?= htmlspecialchars($payment['gateway_id']) ?>" target="_blank" title="View on Stripe">
                                    <?= htmlspecialchars(substr($payment['gateway_id'], 0, 15)) ?>...
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No payments found matching the criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
