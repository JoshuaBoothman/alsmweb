<?php
require_once '../config/db_config.php';
session_start();

// --- SECURITY CHECK: User must be logged in to check out ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'checkout.php'; // Remember where to send them back after login
    $_SESSION['error_message'] = 'You must be logged in to proceed to checkout.';
    header('Location: login.php');
    exit();
}

// --- SECURITY CHECK: Cart cannot be empty ---
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: merchandise.php?error=cartempty');
    exit();
}

// --- DATA FETCHING ---
$user = null;
$cart_items = [];
$cart_total = 0;
$error_message = '';

try {
    // 1. Fetch logged-in user's data to pre-fill the form
    $stmt_user = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
    $stmt_user->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch cart item details to display summary
    $variant_ids = array_keys($_SESSION['cart']);
    $in_clause = implode(',', array_fill(0, count($variant_ids), '?'));
    
    $sql_cart = "
        SELECT 
            p.product_name,
            pv.variant_id, pv.price AS variant_price, p.base_price,
            (SELECT GROUP_CONCAT(CONCAT(a.name, ': ', ao.value) SEPARATOR ', ') 
             FROM product_variant_options pvo
             JOIN attribute_options ao ON pvo.option_id = ao.option_id
             JOIN attributes a ON ao.attribute_id = a.attribute_id
             WHERE pvo.variant_id = pv.variant_id) AS options_string
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.product_id
        WHERE pv.variant_id IN ($in_clause)
    ";
    $stmt_cart = $pdo->prepare($sql_cart);
    $stmt_cart->execute($variant_ids);
    $results = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);

    // 3. Process cart data and calculate total
    foreach ($results as $item) {
        $variant_id = $item['variant_id'];
        $quantity = $_SESSION['cart'][$variant_id]['quantity'];
        $price = $item['variant_price'] ?? $item['base_price'];
        $subtotal = $price * $quantity;
        
        $cart_items[] = [
            'name' => $item['product_name'],
            'options' => $item['options_string'],
            'quantity' => $quantity,
            'price' => $price
        ];
        $cart_total += $subtotal;
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

$page_title = 'Checkout';
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-4">
    <h1 class="mb-4">Checkout</h1>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php else: ?>
        <div class="row g-5">
            <!-- Shipping Information Form -->
            <div class="col-md-7 col-lg-8">
                <h4 class="mb-3">Shipping Address</h4>
                <form action="place_order.php" method="POST" class="needs-validation" novalidate>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="firstName" class="form-label">First name</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" placeholder="" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                        </div>

                        <div class="col-sm-6">
                            <label for="lastName" class="form-label">Last name</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" placeholder="" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="address" name="address" placeholder="1234 Main St" required>
                        </div>
                        
                        <!-- Add more address fields as needed: City, State, Postcode etc. -->
                    </div>

                    <hr class="my-4">
                    
                    <!-- Payment section placeholder -->
                    <h4 class="mb-3">Payment</h4>
                    <p class="text-muted">Payment gateway integration will be handled in a future phase. Clicking "Place Order" will finalize the order for now.</p>

                    <hr class="my-4">

                    <button class="w-100 btn btn-primary btn-lg" type="submit">Place Order</button>
                </form>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="col-md-5 col-lg-4 order-md-last">
                <h4 class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-primary">Your cart</span>
                    <span class="badge bg-primary rounded-pill"><?= count($cart_items) ?></span>
                </h4>
                <ul class="list-group mb-3">
                    <?php foreach ($cart_items as $item): ?>
                        <li class="list-group-item d-flex justify-content-between lh-sm">
                            <div>
                                <h6 class="my-0"><?= htmlspecialchars($item['name']) ?> (x<?= $item['quantity'] ?>)</h6>
                                <small class="text-muted"><?= htmlspecialchars($item['options']) ?></small>
                            </div>
                            <span class="text-muted">$<?= htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)) ?></span>
                        </li>
                    <?php endforeach; ?>
                    
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Total (AUD)</span>
                        <strong>$<?= htmlspecialchars(number_format($cart_total, 2)) ?></strong>
                    </li>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php 
require_once __DIR__ . '/../templates/footer.php'; 
?>
