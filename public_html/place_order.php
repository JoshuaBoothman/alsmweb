<?php
require_once '../config/db_config.php';
session_start();

// --- PRE-CHECKS ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: index.html');
    exit();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=unauthorized');
    exit();
}
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: merchandise.php?error=cartempty');
    exit();
}

// --- INITIALIZE VARIABLES ---
$user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'];
$total_amount = 0;
$shipping_address = trim($_POST['first_name']) . " " . trim($_POST['last_name']) . "\n" . trim($_POST['address']);

if (empty(trim($_POST['first_name'])) || empty(trim($_POST['last_name'])) || empty(trim($_POST['address']))) {
    $_SESSION['error_message'] = 'Please fill out all required shipping address fields.';
    header('Location: checkout.php');
    exit();
}

// --- DATABASE TRANSACTION ---
$pdo->beginTransaction();

try {
    // Step 1: Fetch current prices and calculate total amount
    $variant_ids = array_keys($cart);
    $in_clause = implode(',', array_fill(0, count($variant_ids), '?'));
    
    $sql_prices = "SELECT pv.variant_id, pv.price AS variant_price, p.base_price 
                   FROM product_variants pv 
                   JOIN products p ON pv.product_id = p.product_id 
                   WHERE pv.variant_id IN ($in_clause)";
    $stmt_prices = $pdo->prepare($sql_prices);
    $stmt_prices->execute($variant_ids);
    $price_data = $stmt_prices->fetchAll(PDO::FETCH_ASSOC);

    $price_results = [];
    foreach ($price_data as $row) {
        $actual_price = $row['variant_price'] ?? $row['base_price'];
        $price_results[$row['variant_id']] = $actual_price;
    }
    
    foreach($cart as $variant_id => $item) {
        if(isset($price_results[$variant_id])) {
            $total_amount += $price_results[$variant_id] * $item['quantity'];
        } else {
            throw new Exception("An item in your cart (Variant ID: $variant_id) is no longer available.");
        }
    }


    // Step 2: Create the main order record
    $sql_order = "INSERT INTO orders (user_id, total_amount, shipping_address, order_status) VALUES (:user_id, :total_amount, :shipping_address, :order_status)";
    $stmt_order = $pdo->prepare($sql_order);
    $stmt_order->execute([
        ':user_id' => $user_id,
        ':total_amount' => $total_amount,
        ':shipping_address' => $shipping_address,
        ':order_status' => 'paid' // Assuming payment is successful for now
    ]);
    $order_id = $pdo->lastInsertId();

    // Step 3: Create the order items and update stock
    $sql_order_item = "INSERT INTO orderitems (order_id, product_id, variant_id, quantity, price_at_purchase) VALUES (:order_id, :product_id, :variant_id, :quantity, :price)";
    $stmt_order_item = $pdo->prepare($sql_order_item);

    $sql_update_stock = "UPDATE product_variants SET stock_quantity = stock_quantity - :quantity WHERE variant_id = :variant_id";
    $stmt_update_stock = $pdo->prepare($sql_update_stock);

    foreach ($cart as $variant_id => $item) {
        $stmt_order_item->execute([
            ':order_id' => $order_id,
            ':product_id' => $item['product_id'],
            ':variant_id' => $item['variant_id'],
            ':quantity' => $item['quantity'],
            ':price' => $price_results[$variant_id]
        ]);

        $stmt_update_stock->execute([
            ':quantity' => $item['quantity'],
            ':variant_id' => $item['variant_id']
        ]);
    }

    $pdo->commit();

    // Step 4: Clear the cart and redirect to a confirmation page
    unset($_SESSION['cart']);
    $_SESSION['last_order_id'] = $order_id; 

    header('Location: order_confirmation.php');
    exit();

} catch (Exception $e) {
    // If anything went wrong, roll back the entire transaction
    $pdo->rollBack();
    
    // Restore proper error handling
    $_SESSION['error_message'] = 'There was a problem placing your order. Please try again. Error: ' . $e->getMessage();
    header('Location: checkout.php');
    exit();
}
