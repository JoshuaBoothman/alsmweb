<?php
// public_html/place_order.php

require_once '../config/db_config.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- PRE-CHECKS ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit();
}

// --- INITIALIZE VARIABLES ---
$user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'];
$shipping_address = $_SESSION['shipping_address'] ?? 'No Address Provided';
$merch_total = 0;
$last_merch_order_id = null;
$confirmed_booking_ids = []; // To store IDs of confirmed bookings

// --- DATABASE TRANSACTION ---
$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->beginTransaction();

try {
    // --- PART 1: PROCESS MERCHANDISE ---
    $merch_items = array_filter($cart, fn($item) => $item['type'] === 'merchandise');
    
    if (!empty($merch_items)) {
        $variant_ids = array_column($merch_items, 'variant_id');
        $in_clause = implode(',', array_fill(0, count($variant_ids), '?'));

        $sql_prices = "SELECT pv.variant_id, COALESCE(pv.price, p.base_price) AS final_price, p.product_id FROM product_variants pv JOIN products p ON pv.product_id = p.product_id WHERE pv.variant_id IN ($in_clause)";
        $stmt_prices = $pdo->prepare($sql_prices);
        $stmt_prices->execute($variant_ids);
        $price_data = $stmt_prices->fetchAll(PDO::FETCH_ASSOC);

        $price_results = [];
        $product_id_map = [];
        foreach ($price_data as $row) {
            $price_results[$row['variant_id']] = $row['final_price'];
            $product_id_map[$row['variant_id']] = $row['product_id'];
        }

        foreach($merch_items as $key => $item) {
            $merch_total += $price_results[$item['variant_id']] * $item['quantity'];
        }

        $sql_order = "INSERT INTO orders (user_id, total_amount, shipping_address, order_status) VALUES (?, ?, ?, 'paid')";
        $stmt_order = $pdo->prepare($sql_order);
        $stmt_order->execute([$user_id, $merch_total, $shipping_address]);
        $last_merch_order_id = $pdo->lastInsertId();

        $sql_order_item = "INSERT INTO orderitems (order_id, product_id, variant_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?, ?)";
        $stmt_order_item = $pdo->prepare($sql_order_item);
        $sql_update_stock = "UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE variant_id = ?";
        $stmt_update_stock = $pdo->prepare($sql_update_stock);

        foreach ($merch_items as $key => $item) {
            $variant_id = $item['variant_id'];
            $stmt_order_item->execute([$last_merch_order_id, $product_id_map[$variant_id], $variant_id, $item['quantity'], $price_results[$variant_id]]);
            $stmt_update_stock->execute([$item['quantity'], $variant_id]);
        }
    }

    // --- PART 2: PROCESS CAMPSITE BOOKINGS ---
    $booking_items = array_filter($cart, fn($item) => $item['type'] === 'campsite');

    if (!empty($booking_items)) {
        $confirmed_booking_ids = array_column($booking_items, 'booking_id'); // Capture the IDs
        $in_clause_bookings = implode(',', array_fill(0, count($confirmed_booking_ids), '?'));

        $sql_update_booking = "UPDATE bookings SET status = 'Confirmed' WHERE booking_id IN ($in_clause_bookings) AND user_id = ?";
        $stmt_update_booking = $pdo->prepare($sql_update_booking);
        $params = $confirmed_booking_ids;
        $params[] = $user_id;
        $stmt_update_booking->execute($params);
    }

    // --- PART 3: CREATE PAYMENT RECORD ---
    $stripe_transaction_id = $_SESSION['stripe_payment_intent_id'] ?? 'pi_placeholder_' . uniqid();
    $grand_total = 0;
    // Recalculate grand total for payment record
    foreach($cart as $item) {
        if ($item['type'] === 'merchandise') {
             $grand_total += $price_results[$item['variant_id']] * $item['quantity'];
        } else if ($item['type'] === 'campsite') {
             $grand_total += $item['total_price'];
        }
    }
    $sql_payment = "INSERT INTO payments (order_id, user_id, gateway_name, gateway_transaction_id, payment_status, amount, currency) VALUES (?, ?, 'Stripe', ?, 'successful', ?, 'aud')";
    $stmt_payment = $pdo->prepare($sql_payment);
    $stmt_payment->execute([$last_merch_order_id, $user_id, $stripe_transaction_id, $grand_total]);

    $pdo->commit();

    // --- PART 4: CLEAN UP AND REDIRECT ---
    // Store the necessary IDs in the session for the confirmation page
    $_SESSION['last_order_id'] = $last_merch_order_id;
    $_SESSION['last_booking_ids'] = $confirmed_booking_ids;
    
    // Clear the cart and other temporary data
    unset($_SESSION['cart']);
    unset($_SESSION['shipping_address']);
    unset($_SESSION['stripe_payment_intent_id']);

    header('Location: order_confirmation.php');
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'There was a problem saving your order. Error: ' . $e->getMessage();
    header('Location: checkout.php');
    exit();
}
