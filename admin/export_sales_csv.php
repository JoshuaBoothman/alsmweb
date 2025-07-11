<?php
// admin/export_sales_csv.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Silently fail for security reasons if not an admin.
    exit();
}

require_once '../config/db_config.php';

// --- INITIALIZE VARIABLES ---
$filters = [
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? '',
    'customer' => $_GET['customer'] ?? '',
    'revenue_type' => $_GET['revenue_type'] ?? 'all'
];
$payments = [];

// --- DATA FETCHING LOGIC (Mirrors sales_report.php) ---
try {
    $sql = "SELECT 
                p.payment_id, p.payment_date, p.amount, p.gateway_transaction_id,
                u.username, o.order_id, b.booking_id, er.registration_id
            FROM payments p
            JOIN users u ON p.user_id = u.user_id
            LEFT JOIN orders o ON p.order_id = o.order_id
            LEFT JOIN bookings b ON p.booking_id = b.booking_id
            LEFT JOIN eventregistrations er ON p.payment_id = er.payment_id
            WHERE p.payment_status = 'successful'";
    
    $params = [];

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
            case 'merchandise': $sql .= " AND p.order_id IS NOT NULL"; break;
            case 'booking': $sql .= " AND p.booking_id IS NOT NULL"; break;
            case 'registration': $sql .= " AND er.registration_id IS NOT NULL"; break;
        }
    }

    $sql .= " ORDER BY p.payment_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $raw_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process data for CSV
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
            'Payment ID' => $payment['payment_id'],
            'Date' => $payment['payment_date'],
            'Customer' => $payment['username'],
            'Revenue Type' => $type,
            'Details' => $details,
            'Amount' => $payment['amount'],
            'Gateway ID' => $payment['gateway_transaction_id']
        ];
    }

} catch (PDOException $e) {
    // In case of error, you might want to log it but not output to the user here.
    // For simplicity, we'll just exit.
    exit("Database Error.");
}

// --- CSV GENERATION ---
$filename = "sales_report_" . date('Y-m-d') . ".csv";

// Set headers to force download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Write header row
if (!empty($payments)) {
    fputcsv($output, array_keys($payments[0]));
}

// Write data rows
foreach ($payments as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit();

