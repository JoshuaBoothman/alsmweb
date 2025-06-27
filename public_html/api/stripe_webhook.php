<?php
// /public_html/api/stripe_webhook.php

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../vendor/autoload.php'; 
require_once __DIR__ . '/../../lib/functions/stripe_helpers.php'; 

// --- SECURE SECRET HANDLING ---
// The secret is no longer hard-coded.
// We try to get it from a server environment variable.
// This is the standard for live production servers.
$endpoint_secret = $_SERVER['STRIPE_WEBHOOK_SECRET'] ?? null;

// For local development, we can create a .env file (which is ignored by Git)
// and load it. We'll do this as a fallback if the server variable isn't set.
if (!$endpoint_secret) {
    // This part is for your XAMPP environment only.
    if (file_exists(__DIR__ . '/../../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
        $dotenv->load();
        $endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;
    }
}

if (!$endpoint_secret) {
    // If the secret is still not found, we cannot proceed.
    http_response_code(500);
    die('Webhook secret not configured.');
}
// --- END SECURE SECRET HANDLING ---


\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

// Handle the event
switch ($event->type) {
    case 'invoice.payment_succeeded':
    case 'customer.subscription.updated':
    case 'customer.subscription.deleted':
        $session = $event->data->object;
        $stripe_customer_id = $session->customer;

        if ($stripe_customer_id) {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            syncStripeDataForCustomer($pdo, $stripe_customer_id);
        }
        break;
    default:
        // Unexpected event type
}

http_response_code(200);
