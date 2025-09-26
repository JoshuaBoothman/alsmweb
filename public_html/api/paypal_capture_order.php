<?php
// public_html/api/paypal_capture_order.php

// 1. Setup - Autoloading and Environment
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// 2. Import necessary classes from the SDK
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

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

// 4. Get the Order ID from the frontend
$data = json_decode(file_get_contents('php://input'), true);
$orderID = $data['orderID'] ?? null;

if (!$orderID) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'PayPal Order ID not provided.']);
    exit();
}

$pdo->beginTransaction();
try {
    // 5. Set up the PayPal HTTP client
    $environment = new SandboxEnvironment(PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET);
    $client = new PayPalHttpClient($environment);

    // 6. Build the request to capture the payment
    $request = new OrdersCaptureRequest($orderID);
    $request->prefer('return=representation');

    // 7. Execute the request and get the response
    $response = $client->execute($request);
    $result = $response->result;

    // 8. CRITICAL: Verify the payment was completed successfully
    if ($response->statusCode !== 201 || $result->status !== 'COMPLETED') {
         throw new Exception('Payment was not completed successfully with PayPal. Status: ' . ($result->status ?? 'Unknown'));
    }

    // 9. VERIFY AMOUNT - Recalculate cart total to ensure it matches the captured amount
    $cart = $_SESSION['cart'];
    $server_total_amount = 0.00;
    
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
            $server_total_amount += $price * $_SESSION['cart']['merch_' . $variant_id]['quantity'];
        }
    }
    if (!empty($booking_ids)) {
        $in_clause_bookings = implode(',', array_fill(0, count($booking_ids), '?'));
        $sql_bookings = "SELECT booking_id, total_price FROM bookings WHERE booking_id IN ($in_clause_bookings)";
        $stmt_bookings = $pdo->prepare($sql_bookings);
        $stmt_bookings->execute($booking_ids);
        $booking_prices = $stmt_bookings->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($booking_prices as $booking_id => $price) { $server_total_amount += $price; }
    }
    if (!empty($registration_packages)) {
        $sql_types = "SELECT type_id, price FROM attendee_types";
        $attendee_type_prices = $pdo->query($sql_types)->fetchAll(PDO::FETCH_KEY_PAIR);
        $sql_sub_events = "SELECT sub_event_id, cost FROM subevents";
        $sub_event_costs = $pdo->query($sql_sub_events)->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($registration_packages as $key => $package) {
            foreach ($package['details']['attendees'] as $attendee) {
                $server_total_amount += $attendee_type_prices[$attendee['type_id']] ?? 0;
            }
            foreach ($package['details']['sub_events'] as $sub_event_id => $attendee_indices) {
                $server_total_amount += count($attendee_indices) * ($sub_event_costs[$sub_event_id] ?? 0);
            }
        }
    }

    $capture = $result->purchase_units[0]->payments->captures[0];
    $paypal_transaction_id = $capture->id;
    $paypal_total_amount = $capture->amount->value;

    if (bccomp((string)$server_total_amount, (string)$paypal_total_amount, 2) !== 0) {
        error_log("CRITICAL: PayPal amount mismatch. OrderID: {$orderID}. Server total: {$server_total_amount}, PayPal total: {$paypal_total_amount}");
        throw new Exception("Payment amount verification failed.");
    }

    // 10. Save the full order to your local database
    $user_id = $_SESSION['user_id'];
    
    $sql_payment = "INSERT INTO payments (user_id, gateway_name, gateway_transaction_id, payment_status, amount, currency) VALUES (?, 'PayPal', ?, 'successful', ?, 'aud')";
    $stmt_payment = $pdo->prepare($sql_payment);
    $stmt_payment->execute([$user_id, $paypal_transaction_id, $paypal_total_amount]);
    $payment_id = $pdo->lastInsertId();

    $confirmed_booking_ids = [];
    $booking_items = array_filter($cart, fn($item) => $item['type'] === 'campsite');
    if (!empty($booking_items)) {
        $confirmed_booking_ids = array_column($booking_items, 'booking_id');
        $in_clause_bookings = implode(',', array_fill(0, count($confirmed_booking_ids), '?'));
        $sql_update_booking = "UPDATE bookings SET status = 'Confirmed' WHERE booking_id IN ($in_clause_bookings) AND user_id = ?";
        $stmt_update_booking = $pdo->prepare($sql_update_booking);
        $stmt_update_booking->execute(array_merge($confirmed_booking_ids, [$user_id]));
    }
    
    // 11. Finalize the database transaction and clean up the session
    $pdo->commit();

    $_SESSION['last_payment_id'] = $payment_id;
    $_SESSION['last_booking_ids'] = $confirmed_booking_ids;
    unset($_SESSION['cart'], $_SESSION['shipping_address']);

    // 12. Send a success response back to the browser
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'redirect_url' => 'order_confirmation.php'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    header('Content-Type: application/json');
    error_log($e->getMessage());
    echo json_encode(['error' => 'Could not process payment. Please try again.']);
}