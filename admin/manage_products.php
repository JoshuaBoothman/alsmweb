<?php
// admin/manage_products.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';

$products = [];
$error_message = '';

try {
    $sql = "SELECT p.product_id, p.product_name, p.base_price, p.stock_quantity, c.category_name 
            FROM products AS p 
            LEFT JOIN categories AS c ON p.category_id = c.category_id 
            WHERE p.is_deleted = 0
            ORDER BY p.product_name ASC";
                
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch products. " . $e->getMessage();
}

// --- HEADER ---
$page_title = 'Manage Products';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">Product Management</h1>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if ($error_message) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($error_message) . '</div>';
    }
    ?>

    <div class="d-flex justify-content-end mb-3">
        <a href="manage_attributes.php" class="btn btn-secondary me-2">Manage Attributes</a>
        <a href="manage_categories.php" class="btn btn-info me-2">Manage Categories</a>
        <a href="add_product.php" class="btn btn-success">Add New Product</a>
    </div>

    <table class="table table-striped">
        <thead class="table-dark">
            <tr>
                <th>Product Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= htmlspecialchars($product['product_name']) ?></td>
                        <td><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td>
                        <td>$<?= htmlspecialchars(number_format($product['base_price'], 2)) ?></td>
                        <td><?= htmlspecialchars($product['stock_quantity']) ?></td>
                        <td>
                            <a href="manage_variants.php?product_id=<?= $product['product_id'] ?>" class="btn btn-info btn-sm">Variants</a>
                            <a href="edit_product.php?id=<?= $product['product_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="delete_product.php?id=<?= $product['product_id'] ?>" class="btn btn-danger btn-sm">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center">No products found. Click "Add New Product" to create one.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
