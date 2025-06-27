<?php
// /public_html/api/stripe_webhook.php

// This script handles incoming webhook events from Stripe.

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // Use Composer's autoload
require_once __DIR__ . '/../../lib/functions/stripe_helpers.php'; // Our custom helper functions

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// You need to set this up in your Stripe Dashboard's webhook settings
// For local testing, you will use the Stripe CLI to get a temporary signing secret.
$endpoint_secret = ' whsec_803ff6653cd0818ddedcf39c33605ae91eb0e849f6b867b4662cc284947d0652'; // Replace with your actual webhook signing secret

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
} catch(\UnexpectedValueException $e) {
    // Invalid payload
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    // Invalid signature
    http_response_code(400);
    exit();
}

// --- Handle the event ---
// We only care about a few key events to trigger a sync.
switch ($event->type) {
    case 'invoice.payment_succeeded':
    case 'customer.subscription.updated':
    case 'customer.subscription.deleted':
        $session = $event->data->object;
        $stripe_customer_id = $session->customer;

        if ($stripe_customer_id) {
             // Connect to the database
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // This is the core logic: Use the event as a TRIGGER to sync data.
            // We do not trust the data within the event payload itself.
            syncStripeDataForCustomer($pdo, $stripe_customer_id);
        }
        break;
    
    // ... handle other event types
    default:
        // Unexpected event type
}

// Respond to Stripe to acknowledge receipt of the event
http_response_code(200);
