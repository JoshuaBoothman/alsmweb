<?php
// public_html/api/paypal_create_order.php

// 1. Setup - Autoloading and Environment
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// 2. Import necessary classes from the new SDK
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment; // Use ProductionEnvironment for live
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;

// 3. Start session and perform security checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'User not authenticated or cart is empty.']);
    exit();
}

try {
    // 4. Calculate the cart total securely on the server-side
    $cart = $_SESSION['cart'];
    $total_amount = 0.00;
    
    $merch_variant_ids = [];
    $booking_ids = [];
    $registration_packages = [];
    foreach ($cart as $key => $item) {
        if ($item['type'] === 'merchandise') { $merch_variant_ids[] = $item['variant_id']; } 
        elseif ($item['type'] === 'campsite') { $booking_ids[] = $item['booking_id']; } 
        elseif ($item['type'] === 'registration') { $registration_packages[$key] = $item; }
    }
    if (!empty($merch_variant_ids)) {
        $in_clause = implode(',', array_fill(0, count($merch_variant_ids), '?'));
        $sql_merch = "SELECT pv.variant_id, COALESCE(pv.price, p.base_price) AS final_price FROM product_variants pv JOIN products p ON pv.product_id = p.product_id WHERE pv.variant_id IN ($in_clause)";
        $stmt_merch = $pdo->prepare($sql_merch);
        $stmt_merch->execute($merch_variant_ids);
        $merch_prices = $stmt_merch->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($merch_prices as $variant_id => $price) {
            $total_amount += $price * $_SESSION['cart']['merch_' . $variant_id]['quantity'];
        }
    }
    if (!empty($booking_ids)) {
        $in_clause_bookings = implode(',', array_fill(0, count($booking_ids), '?'));
        $sql_bookings = "SELECT booking_id, total_price FROM bookings WHERE booking_id IN ($in_clause_bookings)";
        $stmt_bookings = $pdo->prepare($sql_bookings);
        $stmt_bookings->execute($booking_ids);
        $booking_prices = $stmt_bookings->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($booking_prices as $booking_id => $price) { $total_amount += $price; }
    }
    if (!empty($registration_packages)) {
        $sql_types = "SELECT type_id, price FROM attendee_types";
        $attendee_type_prices = $pdo->query($sql_types)->fetchAll(PDO::FETCH_KEY_PAIR);
        $sql_sub_events = "SELECT sub_event_id, cost FROM subevents";
        $sub_event_costs = $pdo->query($sql_sub_events)->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($registration_packages as $key => $package) {
            foreach ($package['details']['attendees'] as $attendee) {
                $total_amount += $attendee_type_prices[$attendee['type_id']] ?? 0;
            }
            foreach ($package['details']['sub_events'] as $sub_event_id => $attendee_indices) {
                $total_amount += count($attendee_indices) * ($sub_event_costs[$sub_event_id] ?? 0);
            }
        }
    }
    
    if ($total_amount <= 0) {
        throw new Exception("Cart total must be greater than zero.");
    }

    // 5. Set up the PayPal HTTP client
    $environment = new SandboxEnvironment(PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET);
    $client = new PayPalHttpClient($environment);

    // 6. Build the request to create a PayPal order
    $request = new OrdersCreateRequest();
    $request->prefer('return=representation');
    $request->body = [
        "intent" => "CAPTURE",
        "purchase_units" => [[
            "amount" => [
                "currency_code" => "AUD",
                "value" => number_format($total_amount, 2, '.', '')
            ]
        ]]
    ];

    // 7. Execute the request and get the response
    $response = $client->execute($request);

    // 8. Send the new Order ID back to the browser
    header('Content-Type: application/json');
    echo json_encode(['orderID' => $response->result->id]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    error_log($e->getMessage());
    echo json_encode(['error' => 'An error occurred while creating the order. Please try again.']);
}