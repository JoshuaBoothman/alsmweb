<?php
/**
 * stripe_helpers.php
 *
 * This file contains helper functions for interacting with the Stripe API.
 */

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks the database for a user's Stripe Customer ID. If it doesn't exist,
 * it creates a new customer in Stripe and saves the new ID to the database.
 * This ensures each user has only one Stripe customer record.
 *
 * @param PDO $pdo The database connection object.
 * @param int $user_id The user's ID from our local database.
 * @param string $user_email The user's email address.
 * @return string|null The Stripe Customer ID (e.g., 'cus_...') or null on failure.
 */
function getOrCreateStripeCustomer($pdo, $user_id, $user_email) {
    if (!$user_id || !$user_email) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT stripe_customer_id FROM Users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && !empty($result['stripe_customer_id'])) {
        return $result['stripe_customer_id'];
    }

    try {
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        $customer = \Stripe\Customer::create([
            'email' => $user_email,
            'name' => 'User ' . $user_id,
            'metadata' => ['alsm_user_id' => $user_id]
        ]);

        $new_stripe_id = $customer->id;

        $update_stmt = $pdo->prepare("UPDATE Users SET stripe_customer_id = :stripe_id WHERE user_id = :user_id");
        $update_stmt->execute([':stripe_id' => $new_stripe_id, ':user_id' => $user_id]);

        return $new_stripe_id;

    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log("Stripe API error creating customer: " . $e->getMessage());
        return null;
    } catch (PDOException $e) {
        error_log("Database error updating Stripe customer ID: " . $e->getMessage());
        return null;
    }
}


/**
 * Fetches the latest subscription data from Stripe for a given customer
 * and updates a simplified version in our local database (or a cache).
 * For this project, we will just log it. A full implementation would
 * save this to a 'subscriptions' table.
 *
 * @param PDO $pdo The database connection object.
 * @param string $stripe_customer_id The Stripe Customer ID.
 * @return bool True on success, false on failure.
 */
function syncStripeDataForCustomer($pdo, $stripe_customer_id) {
    if (!$stripe_customer_id) {
        return false;
    }

    try {
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        
        $subscriptions = \Stripe\Subscription::all([
            'customer' => $stripe_customer_id,
            'status' => 'active',
            'limit' => 1
        ]);

        if (!empty($subscriptions->data)) {
            $sub = $subscriptions->data[0];
            $status = $sub->status;
            $current_period_end = date('Y-m-d H:i:s', $sub->current_period_end);
            error_log("Synced for " . $stripe_customer_id . ": Status is " . $status . ", ends at " . $current_period_end);
        } else {
            error_log("Synced for " . $stripe_customer_id . ": No active subscription found.");
        }

        return true;

    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log("Stripe API error during sync: " . $e->getMessage());
        return false;
    }
}
