<?php
require_once '../config/db_config.php';

$products = [];
$error_message = '';

try {
    // Select all products that are active and not deleted
    $sql = "SELECT product_id, product_name, description, base_price, image_path 
            FROM products 
            WHERE is_active = 1 AND is_deleted = 0 
            ORDER BY product_name ASC";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching products: " . $e->getMessage();
}

// Define the title for the <title> tag in the header
$page_title = 'ALSM - Merchandise';
// We need to specify the path to the templates folder from the public_html directory.
// So we go up one level ('../') and then into 'templates/'.
require_once __DIR__ . '/../templates/header.php';
?>

<main class="container mt-4">
    <div class="row">
        <div class="col-12 text-center mb-4">
            <h2>Merchandise</h2>
        </div>
    </div>
    <div class="row">
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php elseif (empty($products)): ?>
            <div class="col-12">
                <p class="text-center">No merchandise is currently available. Please check back later!</p>
            </div>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100">
                        
                        <img src="<?= htmlspecialchars($product['image_path'] ?? 'https://placehold.co/400x300?text=No+Image') ?>" class="card-img-top product-card-img" alt="<?= htmlspecialchars($product['product_name']) ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($product['product_name']) ?></h5>
                            <p class="card-text"><?= htmlspecialchars(substr($product['description'], 0, 100)) . (strlen($product['description']) > 100 ? '...' : '') ?></p>
                            <p class="card-text"><strong>Price: $<?= htmlspecialchars(number_format($product['base_price'], 2)) ?></strong></p>
                            <a href="product_detail.php?id=<?= $product['product_id'] ?>" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php 
// Include the footer template
require_once __DIR__ . '/../templates/footer.php'; 
?>
