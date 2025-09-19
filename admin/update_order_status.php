<?php
// admin/update_order_status.php

session_start();

require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// 1. Security Checks: Only admins can access this, and it must be a POST request.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Silently fail if not an admin.
    header("Location: /alsmweb/public_html/login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Silently fail if not a POST request.
    header("Location: manage_orders.php");
    exit();
}

// 2. Validate the CSRF token
validate_csrf_token();

// 3. Get the Order ID from the form submission
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

if (!$order_id) {
    $_SESSION['error_message'] = "Invalid Order ID provided.";
    header("Location: manage_orders.php");
    exit();
}

// 4. Perform the database updates within a transaction
$pdo->beginTransaction();
try {
    // First, get the order details we need to create the payment record
    $stmt_order = $pdo->prepare("SELECT user_id, total_amount FROM orders WHERE order_id = ?");
    $stmt_order->execute([$order_id]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found.");
    }

    // A. Create the payment record
    $sql_payment = "INSERT INTO payments (user_id, order_id, payment_status, amount, currency, gateway_name) 
                    VALUES (?, ?, 'successful', ?, 'aud', 'Direct Deposit')";
    $stmt_payment = $pdo->prepare($sql_payment);
    $stmt_payment->execute([$order['user_id'], $order_id, $order['total_amount']]);
    $payment_id = $pdo->lastInsertId();

    // B. Update the original order status and link it to the new payment record
    
    $sql_update_order = "UPDATE orders SET order_status = 'paid' WHERE order_id = ?";
    $stmt_update_order = $pdo->prepare($sql_update_order);
    $stmt_update_order->execute([$order_id]);

    // If everything was successful, commit the changes
    $pdo->commit();
    $_SESSION['success_message'] = "Order #{$order_id} has been successfully marked as paid.";

} catch (Exception $e) {
    
    // If anything went wrong, roll back all the changes
    $pdo->rollBack();
    $_SESSION['error_message'] = "Failed to update order status. Error: " . $e->getMessage();
    
}

// 5. Redirect back to the orders page
header("Location: manage_orders.php");
exit();