<?php
// public_html/order_confirmation.php

require_once '../config/db_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- SECURITY CHECK: Must have come from place_order.php ---
if (!isset($_SESSION['last_order_id']) && !isset($_SESSION['last_booking_ids']) && !isset($_SESSION['last_event_registration_id'])) {
    header('Location: index.php');
    exit();
}

// --- INITIALIZE VARIABLES ---
$order_id = $_SESSION['last_order_id'] ?? null;
$booking_ids = $_SESSION['last_booking_ids'] ?? [];
$event_reg_id = $_SESSION['last_event_registration_id'] ?? null;

$merch_order = null;
$order_items = [];
$confirmed_bookings = [];
$confirmed_event_rego = null;
$confirmed_attendees = [];
$error_message = '';
$grand_total = 0;

try {
    // 1. Fetch Merchandise Order Details
    if ($order_id) {
        $stmt_order = $pdo->prepare("SELECT * FROM orders WHERE order_id = :order_id AND user_id = :user_id");
        $stmt_order->execute([':order_id' => $order_id, ':user_id' => $_SESSION['user_id']]);
        $merch_order = $stmt_order->fetch(PDO::FETCH_ASSOC);
        if ($merch_order) {
            $grand_total += $merch_order['total_amount'];
            $sql_items = "SELECT oi.quantity, oi.price_at_purchase, p.product_name, (SELECT GROUP_CONCAT(CONCAT(a.name, ': ', ao.value) SEPARATOR ', ') FROM product_variant_options pvo JOIN attribute_options ao ON pvo.option_id = ao.option_id JOIN attributes a ON ao.attribute_id = a.attribute_id WHERE pvo.variant_id = oi.variant_id) AS options_string FROM orderitems oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = :order_id";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([':order_id' => $order_id]);
            $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // 2. Fetch Confirmed Booking Details
    if (!empty($booking_ids)) {
        $in_clause = implode(',', array_fill(0, count($booking_ids), '?'));
        $sql_bookings = "SELECT b.check_in_date, b.check_out_date, b.total_price, cs.name AS campsite_name, cg.name AS campground_name FROM bookings b JOIN campsites cs ON b.campsite_id = cs.campsite_id JOIN campgrounds cg ON cs.campground_id = cg.campground_id WHERE b.booking_id IN ($in_clause) AND b.user_id = ?";
        $stmt_bookings = $pdo->prepare($sql_bookings);
        $params = $booking_ids;
        $params[] = $_SESSION['user_id'];
        $stmt_bookings->execute($params);
        $confirmed_bookings = $stmt_bookings->fetchAll(PDO::FETCH_ASSOC);
        foreach ($confirmed_bookings as $booking) {
            $grand_total += $booking['total_price'];
        }
    }

    // 3. Fetch Event Registration Details
    if ($event_reg_id) {
        $sql_rego = "SELECT er.total_cost, e.event_name FROM eventregistrations er JOIN events e ON er.event_id = e.event_id WHERE er.registration_id = ? AND er.user_id = ?";
        $stmt_rego = $pdo->prepare($sql_rego);
        $stmt_rego->execute([$event_reg_id, $_SESSION['user_id']]);
        $confirmed_event_rego = $stmt_rego->fetch(PDO::FETCH_ASSOC);
        
        if($confirmed_event_rego) {
            // Note: The grand total calculation was already done in place_order.php and stored in the eventregistrations table.
            // We can just use that value.
            // $grand_total += $confirmed_event_rego['total_cost'];
            
            $sql_attendees = "SELECT a.first_name, a.surname, at.type_name FROM attendees a JOIN attendee_types at ON a.type_id = at.type_id WHERE a.eventreg_id = ?";
            $stmt_attendees = $pdo->prepare($sql_attendees);
            $stmt_attendees->execute([$event_reg_id]);
            $confirmed_attendees = $stmt_attendees->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (Exception $e) {
    $error_message = "Error fetching confirmation details: " . $e->getMessage();
}

// --- CLEANUP ---
unset($_SESSION['last_order_id'], $_SESSION['last_booking_ids'], $_SESSION['last_event_registration_id']);

// --- HEADER ---
$page_title = 'Purchase Confirmation';
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-4">
    
    <div class="py-5 text-center">
        <?php if ($error_message): ?>
            <h1 class="text-danger">Error</h1>
            <p class="lead"><?= htmlspecialchars($error_message) ?></p>
        <?php else: ?>
            <h1 class="text-primary">Thank You! Your Order has been Received.</h1>
            <p class="lead">Your order is now pending payment. Please use the details below to complete your purchase via Direct Deposit.</p>
            
            <div class="card mt-4 bg-light text-start w-75 mx-auto">
                <div class="card-body">
                    <h5 class="card-title text-center">Payment Instructions</h5>
                    <p>Please make a payment to the following bank account. <strong>It is crucial that you use your Order ID as the payment reference</strong> so we can identify your payment.</p>
                    <ul class="list-unstyled">
                        <li><strong>Bank:</strong> Your Bank Name</li>
                        <li><strong>Account Name:</strong> Australian Large Scale Models</li>
                        <li><strong>BSB:</strong> 123-456</li>
                        <li><strong>Account Number:</strong> 12345678</li>
                    </ul>
                    <p class="mb-0">Your order will be processed and confirmed once payment has been received. You will receive an email confirmation at that time.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <!-- Event Registration Summary -->
            <?php if ($confirmed_event_rego && !empty($confirmed_attendees)): ?>
                <h4 class="mb-3">Event Registration Summary (For: <?= htmlspecialchars($confirmed_event_rego['event_name']) ?>)</h4>
                <ul class="list-group mb-4">
                    <?php foreach ($confirmed_attendees as $attendee): ?>
                        <li class="list-group-item d-flex justify-content-between lh-sm">
                            <div>
                                <h6 class="my-0"><?= htmlspecialchars($attendee['first_name'] . ' ' . $attendee['surname']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($attendee['type_name']) ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                     <li class="list-group-item d-flex justify-content-between bg-light">
                        <span class="fw-bold">Registration Total</span>
                        <strong class="fw-bold">$<?= htmlspecialchars(number_format($confirmed_event_rego['total_cost'], 2)) ?></strong>
                    </li>
                </ul>
            <?php endif; ?>

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
                                <small class="d-block text-muted"><?= date('D, M j Y', strtotime($booking['check_in_date'])) ?> to <?= date('D, M j Y', strtotime($booking['check_out_date'])) ?></small>
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
                    <span class="fw-bold">Total Amount Due</span>
                    <strong class="fw-bold text-primary">$<?= htmlspecialchars(number_format($grand_total, 2)) ?></strong>
                </div>
            <?php endif; ?>

        </div>
    </div>
</main>

<?php 
require_once __DIR__ . '/../templates/footer.php'; 
?>
