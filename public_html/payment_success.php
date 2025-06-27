<?php
// /public_html/payment_success.php

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/functions/stripe_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: User must be logged in and have a cart.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit();
}

$error_message = '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch the user's stripe_customer_id from our database
    $stmt = $pdo->prepare("SELECT stripe_customer_id FROM Users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['stripe_customer_id']) {
        // This is the crucial step: Before we do anything else, we sync with Stripe
        // to ensure our local data is up-to-date with the successful payment.
        syncStripeDataForCustomer($pdo, $user['stripe_customer_id']);
    }

    // Now that the sync is likely complete, we can proceed to finalize the order
    // by redirecting to the script that handles the database transaction.
    // We'll use a meta refresh as a simple way to redirect after showing a message.
    header("Refresh: 2; url=place_order.php");

} catch (Exception $e) {
    $error_message = "An error occurred while finalizing your payment. Please contact support. Error: " . $e->getMessage();
}

$page_title = 'Payment Processing...';
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-5 text-center">
    <div class="py-5">
        <?php if ($error_message): ?>
            <h1 class="text-danger">Error</h1>
            <p class="lead"><?= htmlspecialchars($error_message) ?></p>
        <?php else: ?>
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h1 class="mt-3">Payment Successful!</h1>
            <p class="lead">Thank you. We are now finalizing your order. Please wait...</p>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
