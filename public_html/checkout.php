<?php
// public_html/checkout.php

require_once '../config/db_config.php';
require_once __DIR__ . '/../vendor/autoload.php';

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
$cart_items = [];
$cart_total = 0;
$error_message = '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Fetch logged-in user's data to pre-fill the form
    $stmt_user = $pdo->prepare("SELECT * FROM Users WHERE user_id = :user_id");
    $stmt_user->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // --- 2. PROCESS CART & CALCULATE TOTAL ---
    // This logic is now almost identical to view_cart.php to ensure consistency.
    $merch_variant_ids = [];
    $booking_ids = [];

    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['type'] === 'merchandise') {
            $merch_variant_ids[] = $item['variant_id'];
        } elseif ($item['type'] === 'campsite') {
            $booking_ids[] = $item['booking_id'];
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

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

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
    <!-- Stripe.js Library -->
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
    <?php require __DIR__ . '/../templates/header.php'; // Include the navigation bar ?>

    <main class="container mt-4">
        <h1 class="mb-4">Checkout</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php else: ?>
            <div class="row g-5">
                <!-- Shipping Information & Payment Form -->
                <div class="col-md-7 col-lg-8">
                    <form id="payment-form">
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

                        <h4 class="mb-3">Payment Details</h4>
                        <div id="card-element" class="form-control p-3">
                          <!-- A Stripe Element will be inserted here. -->
                        </div>

                        <div id="card-errors" role="alert" class="text-danger mt-2"></div>

                        <hr class="my-4">

                        <button id="submit-button" class="w-100 btn btn-primary btn-lg">Pay $<?= htmlspecialchars(number_format($cart_total, 2)) ?></button>
                    </form>
                </div>

                <!-- Order Summary Sidebar (This part doesn't need the full cart details, just the total) -->
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
    <script src="/alsmweb/public_html/assets/js/checkout.js" defer></script>
</body>
</html>
