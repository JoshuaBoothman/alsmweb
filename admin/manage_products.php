<?php
// --- SECURITY AND INITIALIZATION ---
session_start();

// Ensure user is logged in and is an admin.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- DATABASE LOGIC & PAGE SETUP ---
require_once '../config/db_config.php';

$products = []; // Initialize an empty array for products
$error_message = '';

try {
    // SQL query to get products and their corresponding category name.
    $sql = "SELECT 
                p.product_id, 
                p.product_name, 
                p.base_price, 
                p.stock_quantity, 
                c.category_name 
            FROM 
                products AS p 
            LEFT JOIN 
                categories AS c ON p.category_id = c.category_id 
            ORDER BY 
                p.product_name ASC";
                
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // If the query fails, set an error message to display.
    $error_message = "Database Error: Could not fetch products. " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Product Management</h1>

        <?php
        // Display a success message if one was set in the session
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        // Display an error message if the database query failed
        if ($error_message) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($error_message) . '</div>';
        }
        ?>

        <a href="add_product.php" class="btn btn-success mb-3">Add New Product</a>

        <table class="table table-striped">
            <thead>
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
                                <!-- === NEW BUTTON ADDED ON THIS LINE === -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>