<?php
require_once '../config/db_config.php';
session_start();

// --- SECURITY CHECK: Must have come from place_order.php ---
if (!isset($_SESSION['last_order_id'])) {
    // If there's no order ID in the session, they shouldn't be here.
    header('Location: index.html');
    exit();
}

// --- INITIALIZE VARIABLES ---
$order = null;
$order_items = [];
$error_message = '';
$order_id = $_SESSION['last_order_id'];

// --- DATA FETCHING ---
try {
    // 1. Fetch the main order details
    $stmt_order = $pdo->prepare("SELECT * FROM orders WHERE order_id = :order_id AND user_id = :user_id");
    $stmt_order->execute([':order_id' => $order_id, ':user_id' => $_SESSION['user_id']]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Could not find the specified order.");
    }

    // 2. Fetch the items for this order
    $sql_items = "
        SELECT 
            oi.quantity, oi.price_at_purchase,
            p.product_name,
            (SELECT GROUP_CONCAT(CONCAT(a.name, ': ', ao.value) SEPARATOR ', ') 
             FROM product_variant_options pvo
             JOIN attribute_options ao ON pvo.option_id = ao.option_id
             JOIN attributes a ON ao.attribute_id = a.attribute_id
             WHERE pvo.variant_id = oi.variant_id) AS options_string
        FROM orderitems oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = :order_id";
    
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([':order_id' => $order_id]);
    $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Error fetching order details: " . $e->getMessage();
}

// --- CLEANUP ---
// Unset the session variable so the user can't refresh and see an old order confirmation.
unset($_SESSION['last_order_id']);

$page_title = 'Order Confirmation';
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-4">
    <div class="py-5 text-center">
        <?php if ($error_message): ?>
            <h1 class="text-danger">Error</h1>
            <p class="lead"><?= htmlspecialchars($error_message) ?></p>
            <a href="index.html" class="btn btn-primary">Go to Homepage</a>
        <?php elseif ($order): ?>
            <h1 class="text-success">Thank You!</h1>
            <h2>Your Order is Confirmed</h2>
            <p class="lead">Your order number is <strong>#<?= htmlspecialchars($order['order_id']) ?></strong>. A confirmation email has been sent (or would be, if this was a live site!).</p>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($order_items)): ?>
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <h4 class="mb-3">Order Summary</h4>
                <ul class="list-group mb-3">
                    <?php foreach ($order_items as $item): ?>
                        <li class="list-group-item d-flex justify-content-between lh-sm">
                            <div>
                                <h6 class="my-0"><?= htmlspecialchars($item['product_name']) ?> (x<?= $item['quantity'] ?>)</h6>
                                <small class="text-muted"><?= htmlspecialchars($item['options_string']) ?></small>
                            </div>
                            <span class="text-muted">$<?= htmlspecialchars(number_format($item['price_at_purchase'] * $item['quantity'], 2)) ?></span>
                        </li>
                    <?php endforeach; ?>
                    
                    <li class="list-group-item d-flex justify-content-between bg-light">
                        <span class="fw-bold">Total (AUD)</span>
                        <strong class="fw-bold">$<?= htmlspecialchars(number_format($order['total_amount'], 2)) ?></strong>
                    </li>
                </ul>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php 
require_once __DIR__ . '/../templates/footer.php'; 
?>
