<?php
// /public_html/api/create_payment_intent.php

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../lib/functions/stripe_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    $user_email = $_SESSION['user_email'] ?? 'emailnotfound@example.com';
    $cart = $_SESSION['cart'];
    $total_amount_cents = 0;

    $variant_ids = array_keys($cart);
    if (!empty($variant_ids)) {
        $in_clause = implode(',', array_fill(0, count($variant_ids), '?'));
        
        $sql_prices = "SELECT pv.variant_id, COALESCE(pv.price, p.base_price) AS final_price
                       FROM product_variants pv 
                       JOIN products p ON pv.product_id = p.product_id 
                       WHERE pv.variant_id IN ($in_clause)";
        $stmt_prices = $pdo->prepare($sql_prices);
        $stmt_prices->execute($variant_ids);
        $price_data = $stmt_prices->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach($cart as $variant_id => $item) {
            if (isset($price_data[$variant_id])) {
                $price = $price_data[$variant_id];
                $total_amount_cents += ($price * $item['quantity']) * 100;
            }
        }
    }

    if ($total_amount_cents <= 0) {
        throw new Exception("Calculated total is zero or less.");
    }

    $stripe_customer_id = getOrCreateStripeCustomer($pdo, $user_id, $user_email);

    if (!$stripe_customer_id) {
        throw new Exception("Could not retrieve or create Stripe customer.");
    }

    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => $total_amount_cents,
        'currency' => 'aud',
        'customer' => $stripe_customer_id,
        'automatic_payment_methods' => ['enabled' => true],
    ]);

    echo json_encode(['clientSecret' => $paymentIntent->client_secret]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
