<?php
// public_html/order_confirmation.php

require_once '../config/db_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- SECURITY CHECK: Must have come from place_order.php ---
if (!isset($_SESSION['last_order_id']) && !isset($_SESSION['last_booking_ids'])) {
    header('Location: index.php');
    exit();
}

// --- INITIALIZE VARIABLES ---
$order_id = $_SESSION['last_order_id'] ?? null;
$booking_ids = $_SESSION['last_booking_ids'] ?? [];

$merch_order = null;
$order_items = [];
$confirmed_bookings = [];
$error_message = '';
$grand_total = 0; // Initialize grand total

try {
    // 1. Fetch Merchandise Order Details (if an order was made)
    if ($order_id) {
        $stmt_order = $pdo->prepare("SELECT * FROM orders WHERE order_id = :order_id AND user_id = :user_id");
        $stmt_order->execute([':order_id' => $order_id, ':user_id' => $_SESSION['user_id']]);
        $merch_order = $stmt_order->fetch(PDO::FETCH_ASSOC);

        if ($merch_order) {
            $grand_total += $merch_order['total_amount']; // Add to grand total
            $sql_items = "
                SELECT 
                    oi.quantity, oi.price_at_purchase,
                    p.product_name,
                    (SELECT GROUP_CONCAT(CONCAT(a.name, ': ', ao.value) SEPARATOR ', ') 
                     FROM product_variant_options pvo
                     JOIN attribute_options ao ON pvo.option_id = ao.option_id
                     JOIN attributes a ON ao.attribute_id = a.attribute_id
                     WHERE pvo.variant_id = oi.variant_id) AS options_string
                FROM orderitems oi
                JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = :order_id";
            
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([':order_id' => $order_id]);
            $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // 2. Fetch Confirmed Booking Details (if bookings were made)
    if (!empty($booking_ids)) {
        $in_clause = implode(',', array_fill(0, count($booking_ids), '?'));
        $sql_bookings = "
            SELECT b.check_in_date, b.check_out_date, b.total_price, cs.name AS campsite_name, cg.name AS campground_name
            FROM bookings b
            JOIN campsites cs ON b.campsite_id = cs.campsite_id
            JOIN campgrounds cg ON cs.campground_id = cg.campground_id
            WHERE b.booking_id IN ($in_clause) AND b.user_id = ?
        ";
        $stmt_bookings = $pdo->prepare($sql_bookings);
        $params = $booking_ids;
        $params[] = $_SESSION['user_id'];
        $stmt_bookings->execute($params);
        $confirmed_bookings = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);
        
        // Add booking totals to the grand total
        foreach ($confirmed_bookings as $booking) {
            $grand_total += $booking['total_price'];
        }
    }

} catch (Exception $e) {
    $error_message = "Error fetching confirmation details: " . $e->getMessage();
}

// --- CLEANUP ---
unset($_SESSION['last_order_id']);
unset($_SESSION['last_booking_ids']);

// --- HEADER ---
$page_title = 'Purchase Confirmation';
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-4">
    <div class="py-5 text-center">
        <?php if ($error_message): ?>
            <h1 class="text-danger">Error</h1>
            <p class="lead"><?= htmlspecialchars($error_message) ?></p>
            <a href="index.php" class="btn btn-primary">Go to Homepage</a>
        <?php else: ?>
            <h1 class="text-success">Thank You!</h1>
            <h2>Your Purchase is Confirmed</h2>
            <p class="lead">Your purchase summary is detailed below. A confirmation email is on its way to you.</p>
        <?php endif; ?>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-md-8">
            <!-- Merchandise Order Summary -->
            <?php if ($merch_order && !empty($order_items)): ?>
                <h4 class="mb-3">Merchandise Order Summary (Order #<?= htmlspecialchars($merch_order['order_id']) ?>)</h4>
                <ul class="list-group mb-4">
                    <?php foreach ($order_items as $item): ?>
                        <li class="list-group-item d-flex justify-content-between lh-sm">
                            <div>
                                <h6 class="my-0"><?= htmlspecialchars($item['product_name']) ?> (x<?= $item['quantity'] ?>)</h6>
                                <small class="text-muted"><?= htmlspecialchars($item['options_string']) ?></small>
                            </div>
                            <span class="text-muted">$<?= htmlspecialchars(number_format($item['price_at_purchase'] * $item['quantity'], 2)) ?></span>
                        </li>
                    <?php endforeach; ?>
                    <li class="list-group-item d-flex justify-content-between bg-light">
                        <span class="fw-bold">Merchandise Total</span>
                        <strong class="fw-bold">$<?= htmlspecialchars(number_format($merch_order['total_amount'], 2)) ?></strong>
                    </li>
                </ul>
            <?php endif; ?>

            <!-- Campsite Booking Summary -->
            <?php if (!empty($confirmed_bookings)): ?>
                <h4 class="mb-3">Campsite Booking Summary</h4>
                <ul class="list-group mb-3">
                    <?php foreach ($confirmed_bookings as $booking): ?>
                        <li class="list-group-item d-flex justify-content-between lh-sm">
                            <div>
                                <h6 class="my-0"><?= htmlspecialchars($booking['campsite_name']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($booking['campground_name']) ?></small>
                                <small class="d-block text-muted">
                                    <?= date('D, M j Y', strtotime($booking['check_in_date'])) ?> to <?= date('D, M j Y', strtotime($booking['check_out_date'])) ?>
                                </small>
                            </div>
                            <span class="text-muted fw-bold">$<?= htmlspecialchars(number_format($booking['total_price'], 2)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Grand Total Summary -->
            <?php if ($grand_total > 0): ?>
                <hr>
                <div class="d-flex justify-content-between fs-4">
                    <span class="fw-bold">Grand Total Paid</span>
                    <strong class="fw-bold text-success">$<?= htmlspecialchars(number_format($grand_total, 2)) ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php 
require_once __DIR__ . '/../templates/footer.php'; 
?>
