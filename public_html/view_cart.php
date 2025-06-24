<?php
require_once '../config/db_config.php';
session_start();

$cart_items = [];
$cart_total = 0;
$error_message = '';

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $variant_ids = array_keys($_SESSION['cart']);
    $in_clause = implode(',', array_fill(0, count($variant_ids), '?'));

    try {
        $sql = "
            SELECT 
                p.product_id, p.product_name, p.image_path,
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
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($variant_ids);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $item) {
            $variant_id = $item['variant_id'];
            $quantity = $_SESSION['cart'][$variant_id]['quantity'];
            $price = $item['variant_price'] ?? $item['base_price'];
            $subtotal = $price * $quantity;
            
            $cart_items[] = [
                'product_id' => $item['product_id'],
                'variant_id' => $variant_id,
                'name' => $item['product_name'],
                'image' => $item['image_path'],
                'options' => $item['options_string'],
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $subtotal
            ];
            $cart_total += $subtotal;
        }

    } catch (PDOException $e) {
        $error_message = "Error fetching cart details: " . $e->getMessage();
    }
}

$page_title = 'Your Shopping Cart';
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-4">
    <h1 class="mb-4">Your Shopping Cart</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php elseif (empty($cart_items)): ?>
        <div class="alert alert-info">
            <p>Your shopping cart is empty.</p>
            <a href="merchandise.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th colspan="2">Product</th>
                            <th>Price</th>
                            <th class="text-center">Quantity</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td style="width: 100px;">
                                    <img src="<?= htmlspecialchars($item['image'] ?? 'https://placehold.co/100x100?text=No+Image') ?>" class="img-fluid rounded" alt="<?= htmlspecialchars($item['name']) ?>">
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($item['options']) ?></small>
                                </td>
                                <td>$<?= htmlspecialchars(number_format($item['price'], 2)) ?></td>
                                <td class="text-center">
                                    <form action="cart_actions.php" method="POST" class="d-flex justify-content-center">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="variant_id" value="<?= $item['variant_id'] ?>">
                                        <input type="number" name="quantity" class="form-control" value="<?= $item['quantity'] ?>" min="1" style="width: 70px;">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary ms-2">Update</button>
                                    </form>
                                </td>
                                <td>$<?= htmlspecialchars(number_format($item['subtotal'], 2)) ?></td>
                                <td>
                                    <a href="cart_actions.php?action=remove&variant_id=<?= $item['variant_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this item?')">&times;</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Order Summary</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Subtotal
                                <span>$<?= htmlspecialchars(number_format($cart_total, 2)) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Shipping
                                <span>(Calculated at checkout)</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center fw-bold">
                                Total
                                <span>$<?= htmlspecialchars(number_format($cart_total, 2)) ?></span>
                            </li>
                        </ul>
                        <a href="checkout.php" class="btn btn-primary w-100 mt-3">Proceed to Checkout</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php 
require_once __DIR__ . '/../templates/footer.php'; 
?>
