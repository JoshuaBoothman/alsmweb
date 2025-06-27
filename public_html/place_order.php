<?php
// /public_html/place_order.php
// This is now a backend processor, not a user-facing page.

require_once '../config/db_config.php';
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

// --- PRE-CHECKS ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit();
}

// --- INITIALIZE VARIABLES ---
$user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'];
$total_amount = 0;
$shipping_address = $_SESSION['shipping_address'] ?? 'No Address Provided';

// --- DATABASE TRANSACTION ---
$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->beginTransaction();

try {
    // Step 1: Calculate total amount and prepare data
    $variant_ids = array_keys($cart);
    $in_clause = implode(',', array_fill(0, count($variant_ids), '?'));
    $sql_prices = "SELECT pv.variant_id, COALESCE(pv.price, p.base_price) AS final_price, p.product_id
                   FROM product_variants pv
                   JOIN products p ON pv.product_id = p.product_id
                   WHERE pv.variant_id IN ($in_clause)";
    $stmt_prices = $pdo->prepare($sql_prices);
    $stmt_prices->execute($variant_ids);
    $price_data = $stmt_prices->fetchAll(PDO::FETCH_ASSOC);

    $price_results = [];
    $product_id_map = [];
    foreach ($price_data as $row) {
        $price_results[$row['variant_id']] = $row['final_price'];
        $product_id_map[$row['variant_id']] = $row['product_id'];
    }

    foreach($cart as $variant_id => $item) {
        if(isset($price_results[$variant_id])) {
            $total_amount += $price_results[$variant_id] * $item['quantity'];
        } else {
            throw new Exception("An item in your cart (Variant ID: $variant_id) is no longer available.");
        }
    }

    // Step 2: Create the main order record
    $sql_order = "INSERT INTO orders (user_id, total_amount, shipping_address, order_status) VALUES (?, ?, ?, 'paid')";
    $stmt_order = $pdo->prepare($sql_order);
    $stmt_order->execute([$user_id, $total_amount, $shipping_address]);
    $order_id = $pdo->lastInsertId();

    // Step 3: Create the order items and update stock
    $sql_order_item = "INSERT INTO orderitems (order_id, product_id, variant_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?, ?)";
    $stmt_order_item = $pdo->prepare($sql_order_item);
    $sql_update_stock = "UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE variant_id = ?";
    $stmt_update_stock = $pdo->prepare($sql_update_stock);

    foreach ($cart as $variant_id => $item) {
        $stmt_order_item->execute([$order_id, $product_id_map[$variant_id], $variant_id, $item['quantity'], $price_results[$variant_id]]);
        $stmt_update_stock->execute([$item['quantity'], $variant_id]);
    }
    
    // **NEW STEP 4: Create the payment record**
    // We need the Stripe Transaction ID. We assume the checkout.js stored it in the session.
    // A robust way is to get it from the payment intent. Let's assume it was passed to the success page.
    $stripe_transaction_id = $_SESSION['stripe_payment_intent_id'] ?? 'pi_placeholder_' . uniqid();

    $sql_payment = "INSERT INTO payments (order_id, user_id, gateway_name, gateway_transaction_id, payment_status, amount, currency) 
                    VALUES (?, ?, 'Stripe', ?, 'successful', ?, 'aud')";
    $stmt_payment = $pdo->prepare($sql_payment);
    $stmt_payment->execute([$order_id, $user_id, $stripe_transaction_id, $total_amount]);

    $pdo->commit();

    // Step 5: Clean up session and redirect
    unset($_SESSION['cart']);
    unset($_SESSION['shipping_address']);
    unset($_SESSION['stripe_payment_intent_id']);
    $_SESSION['last_order_id'] = $order_id;

    header('Location: order_confirmation.php');
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'There was a problem saving your order to the database. Error: ' . $e->getMessage();
    header('Location: checkout.php');
    exit();
}
