<?php
// public_html/api/create_payment_intent.php

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../lib/functions/stripe_helpers.php';
// Note: security_helpers.php is not needed here as we do custom validation

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- NEW: CSRF Validation for JSON request ---
// Read the JSON data sent from the JavaScript fetch() call
$json_data = json_decode(file_get_contents('php://input'), true);

// Check if the token sent from JS matches the one in the session.
if (
    !isset($json_data['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $json_data['csrf_token'])
) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit();
}
// We do not unset the token here, allowing the user to retry payment if needed.
// The token will be validated and unset in place_order.php after a successful payment.


// --- Security Checks ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated.']);
    exit();
}
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Shopping cart is empty.']);
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = $_SESSION['user_id'];
    $stmt_user_email = $pdo->prepare("SELECT email FROM Users WHERE user_id = :user_id");
    $stmt_user_email->execute([':user_id' => $user_id]);
    $user_email_result = $stmt_user_email->fetch(PDO::FETCH_ASSOC);
    $user_email = $user_email_result['email'] ?? 'emailnotfound@example.com';

    $cart = $_SESSION['cart'];
    $total_amount_cents = 0;

    // --- CALCULATE TOTAL (Now includes all item types) ---
    $merch_variant_ids = [];
    $booking_ids = [];
    $registration_packages = [];

    foreach ($cart as $key => $item) {
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
            $total_amount_cents += ($price * $_SESSION['cart']['merch_' . $variant_id]['quantity']) * 100;
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
            $total_amount_cents += $price * 100;
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
                $total_amount_cents += ($attendee_type_prices[$attendee['type_id']] ?? 0) * 100;
            }
            foreach ($package['details']['sub_events'] as $sub_event_id => $attendee_indices) {
                $total_amount_cents += (count($attendee_indices) * ($sub_event_costs[$sub_event_id] ?? 0)) * 100;
            }
        }
    }
    // --- END OF CALCULATION LOGIC ---

    if ($total_amount_cents <= 0) {
        throw new Exception("Calculated total is zero or less.");
    }

    $stripe_customer_id = getOrCreateStripeCustomer($pdo, $user_id, $user_email);
    if (!$stripe_customer_id) {
        throw new Exception("Could not retrieve or create Stripe customer.");
    }

    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => round($total_amount_cents),
        'currency' => 'aud',
        'customer' => $stripe_customer_id,
        'automatic_payment_methods' => ['enabled' => true],
    ]);

    $_SESSION['stripe_payment_intent_id'] = $paymentIntent->id;

    echo json_encode(['clientSecret' => $paymentIntent->client_secret]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
