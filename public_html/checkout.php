<?php
// public_html/checkout.php

require_once '../config/db_config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../lib/functions/security_helpers.php'; // 1. Include CSRF helpers

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- SECURITY CHECKS ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'checkout.php';
    $_SESSION['error_message'] = 'You must be logged in to proceed to checkout.';
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: merchandise.php?error=cartempty');
    exit();
}

// --- INITIALIZE VARIABLES ---
$user = null;
$cart_total = 0;
$error_message = '';

try {
    // $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Fetch logged-in user's data to pre-fill the form
    $stmt_user = $pdo->prepare("SELECT * FROM Users WHERE user_id = :user_id");
    $stmt_user->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // --- 2. PROCESS CART & CALCULATE TOTAL ---
    $merch_variant_ids = [];
    $booking_ids = [];
    $registration_packages = [];

    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['type'] === 'merchandise') {
            $merch_variant_ids[] = $item['variant_id'];
        } elseif ($item['type'] === 'campsite') {
            $booking_ids[] = $item['booking_id'];
        } elseif ($item['type'] === 'registration') {
            $registration_packages[$key] = $item;
        }
    }

    // Process Merchandise
    if (!empty($merch_variant_ids)) {
        $in_clause = implode(',', array_fill(0, count($merch_variant_ids), '?'));
        $sql_merch = "SELECT pv.variant_id, COALESCE(pv.price, p.base_price) AS final_price FROM product_variants pv JOIN products p ON pv.product_id = p.product_id WHERE pv.variant_id IN ($in_clause)";
        $stmt_merch = $pdo->prepare($sql_merch);
        $stmt_merch->execute($merch_variant_ids);
        $merch_prices = $stmt_merch->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($merch_prices as $variant_id => $price) {
            $cart_key = 'merch_' . $variant_id;
            $quantity = $_SESSION['cart'][$cart_key]['quantity'];
            $cart_total += $price * $quantity;
        }
    }

    // Process Bookings
    if (!empty($booking_ids)) {
        $in_clause_bookings = implode(',', array_fill(0, count($booking_ids), '?'));
        $sql_bookings = "SELECT booking_id, total_price FROM bookings WHERE booking_id IN ($in_clause_bookings)";
        $stmt_bookings = $pdo->prepare($sql_bookings);
        $stmt_bookings->execute($booking_ids);
        $booking_prices = $stmt_bookings->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($booking_prices as $booking_id => $price) {
            $cart_total += $price;
        }
    }
    
    // Process Event Registrations
    if (!empty($registration_packages)) {
        $sql_types = "SELECT type_id, price FROM attendee_types";
        $attendee_type_prices = $pdo->query($sql_types)->fetchAll(PDO::FETCH_KEY_PAIR);

        $sql_sub_events = "SELECT sub_event_id, cost FROM subevents";
        $sub_event_costs = $pdo->query($sql_sub_events)->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($registration_packages as $key => $package) {
            foreach ($package['details']['attendees'] as $attendee) {
                $cart_total += $attendee_type_prices[$attendee['type_id']] ?? 0;
            }
            foreach ($package['details']['sub_events'] as $sub_event_id => $attendee_indices) {
                $cart_total += count($attendee_indices) * ($sub_event_costs[$sub_event_id] ?? 0);
            }
        }
    }

    // 4. Process Sub-Event Addons
    $addon_packages = array_filter($_SESSION['cart'], fn($item) => $item['type'] === 'sub_event_addon');
    if (!empty($addon_packages)) {
        // Fetch all sub-event costs once for efficiency
        $sql_addon_costs = "SELECT sub_event_id, cost FROM subevents";
        $addon_costs_map = $pdo->query($sql_addon_costs)->fetchAll(PDO::FETCH_KEY_PAIR);

        // Loop through each add-on package in the cart
        foreach ($addon_packages as $key => $package) {
            $addon_total = 0;
            // Loop through the selections in the package to calculate its total
            foreach($package['details'] as $sub_event_id => $attendee_ids) {
                $cost = $addon_costs_map[$sub_event_id] ?? 0;
                $addon_total += count($attendee_ids) * $cost;
            }
            // Add the package's total to the grand total
            $cart_total += $addon_total;
        }
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// 2. Generate a CSRF token to be used by the form
generate_csrf_token();

// --- HEADER (custom, to include Stripe.js) ---
$page_title = 'Checkout';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'ALSM' ?></title>
    <link rel="stylesheet" href="/alsmweb/public_html/assets/css/reset.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/alsmweb/public_html/assets/css/style.css">
    <script src="https://www.paypal.com/sdk/js?client-id=<?= PAYPAL_CLIENT_ID ?>&currency=AUD"></script>
</head>
<body>
    <?php require __DIR__ . '/../templates/header.php'; ?>

    <main class="container mt-4">
        <h1 class="mb-4">Checkout</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php else: ?>
            <div class="row g-5">
                <div class="col-md-7 col-lg-8">
                    <form id="payment-form" action="place_order.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <h4 class="mb-3">Shipping Address</h4>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="firstName" class="form-label">First name</label>
                                <input type="text" class="form-control" id="firstName" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label for="lastName" class="form-label">Last name</label>
                                <input type="text" class="form-control" id="lastName" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-12">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" required>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h4 class="mb-3">Payment Method</h4>
                        <div class="my-3">
                            <div class="form-check">
                                <input id="directdeposit" name="paymentMethod" type="radio" class="form-check-input" value="dd" checked required>
                                <label class="form-check-label" for="directdeposit">Direct Deposit</label>
                            </div>
                            <div class="form-check">
                                <input id="paypal" name="paymentMethod" type="radio" class="form-check-input" value="paypal" required>
                                <label class="form-check-label" for="paypal">PayPal</label>
                            </div>
                        </div>
                        
                        <hr class="my-4">

                        <button id="direct-deposit-submit" class="w-100 btn btn-primary btn-lg" type="submit">Place Order (Direct Deposit)</button>

                        <div id="paypal-button-container" class="mt-3" style="display: none;">
                            </div>
                    </form>
                </div>

                <div class="col-md-5 col-lg-4 order-md-last">
                    <h4 class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-primary">Order Total</span>
                    </h4>
                    <ul class="list-group mb-3">
                        <li class="list-group-item d-flex justify-content-between fs-4">
                            <span>Total (AUD)</span>
                            <strong>$<?= htmlspecialchars(number_format($cart_total, 2)) ?></strong>
                        </li>
                    </ul>
                     <a href="view_cart.php">Edit Cart</a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php // Custom footer to include the checkout-specific JavaScript ?>
    <footer>
        <p>&copy; 2025 Australian Large Scale Models</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/alsmweb/public_html/assets/js/main.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ddRadio = document.getElementById('directdeposit');
            const paypalRadio = document.getElementById('paypal');
            const ddSubmitButton = document.getElementById('direct-deposit-submit');
            const paypalContainer = document.getElementById('paypal-button-container');
            const mainForm = document.getElementById('payment-form');
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;

            function togglePaymentMethod() {
                if (paypalRadio.checked) {
                    ddSubmitButton.style.display = 'none';
                    paypalContainer.style.display = 'block';
                    mainForm.onsubmit = (e) => e.preventDefault();
                } else {
                    ddSubmitButton.style.display = 'block';
                    paypalContainer.style.display = 'none';
                    mainForm.onsubmit = null;
                }
            }

            ddRadio.addEventListener('change', togglePaymentMethod);
            paypalRadio.addEventListener('change', togglePaymentMethod);
            togglePaymentMethod();

            paypal.Buttons({
                createOrder: function(data, actions) {
                    return fetch('/alsmweb/public_html/api/paypal_create_order.php', {
                        method: 'post'
                    }).then(res => res.json()).then(orderData => orderData.orderID);
                },
                onApprove: function(data, actions) {
                    return fetch('/alsmweb/public_html/api/paypal_capture_order.php', {
                        method: 'post',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ orderID: data.orderID })
                    }).then(res => res.json()).then(orderData => {
                        if (orderData.status === 'success') {
                            window.location.href = orderData.redirect_url;
                        } else {
                            alert('There was an issue processing your payment. Please try again.');
                            console.error('Capture Error:', orderData);
                        }
                    });
                },
                onError: function(err) {
                    console.error('An error occurred with the PayPal button:', err);
                    alert('An error occurred. Please try refreshing the page or select Direct Deposit.');
                }
            }).render('#paypal-button-container');
        });
        </script>
</body>
</html>