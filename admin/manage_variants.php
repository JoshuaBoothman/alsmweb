<?php
// --- SECURITY AND INITIALIZATION ---
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND DATABASE CONNECTION ---
require_once '../config/db_config.php';

// --- INITIALIZE VARIABLES ---
$product = null;
$variants = [];
$error_message = '';
$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);

// --- VALIDATE PRODUCT ID ---
if (!$product_id) {
    // If no product_id is provided, redirect back to the main products page.
    header("Location: manage_products.php?error=noproductid");
    exit();
}

// --- DATA FETCHING ---
try {
    // 1. Fetch the parent product's name for the page title and context.
    $sql_product = "SELECT product_name FROM products WHERE product_id = :product_id";
    $stmt_product = $pdo->prepare($sql_product);
    $stmt_product->execute([':product_id' => $product_id]);
    $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        // If the product ID is invalid, stop and show an error.
        throw new Exception("Product not found.");
    }

    // 2. Fetch all variants for this specific product.
    // This is a more advanced query that joins all the new variant tables together.
    // GROUP_CONCAT is a powerful MySQL function that aggregates multiple rows into a single string,
    // which is perfect for displaying the combined options like "Color: Red, Size: Large".
    $sql_variants = "
        SELECT 
            pv.variant_id,
            pv.sku,
            pv.price,
            pv.stock_quantity,
            GROUP_CONCAT(CONCAT(a.name, ': ', ao.value) ORDER BY a.name SEPARATOR ', ') AS options_string
        FROM 
            product_variants AS pv
        LEFT JOIN 
            product_variant_options AS pvo ON pv.variant_id = pvo.variant_id
        LEFT JOIN 
            attribute_options AS ao ON pvo.option_id = ao.option_id
        LEFT JOIN 
            attributes AS a ON ao.attribute_id = a.attribute_id
        WHERE 
            pv.product_id = :product_id
        GROUP BY 
            pv.variant_id
        ORDER BY 
            options_string";
            
    $stmt_variants = $pdo->prepare($sql_variants);
    $stmt_variants->execute([':product_id' => $product_id]);
    $variants = $stmt_variants->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Variants - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <a href="manage_products.php" class="btn btn-secondary">Back to Products</a>
        <?php else: ?>
            <h1 class="mb-4">
                Manage Variants for: <strong><?= htmlspecialchars($product['product_name']) ?></strong>
            </h1>

            <?php
            // Display success messages from session after redirects
            if (isset($_SESSION['success_message'])) {
                echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                unset($_SESSION['success_message']);
            }
            ?>

            <div class="d-flex justify-content-between mb-3">
                <a href="manage_products.php" class="btn btn-secondary">&laquo; Back to Products</a>
                <div>
                    <!-- This button will eventually link to a page to manage attributes like "Color", "Size" globally -->
                    <a href="manage_attributes.php" class="btn btn-outline-secondary">Manage Attributes</a>
                    <a href="add_variant.php?product_id=<?= $product_id ?>" class="btn btn-success">Add New Variant</a>
                </div>
            </div>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Variant (Options)</th>
                        <th>SKU</th>
                        <th>Specific Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($variants)): ?>
                        <?php foreach ($variants as $variant): ?>
                            <tr>
                                <td><?= htmlspecialchars($variant['options_string'] ?? 'Base Product') ?></td>
                                <td><?= htmlspecialchars($variant['sku'] ?? 'N/A') ?></td>
                                <td><?= $variant['price'] !== null ? '$' . htmlspecialchars(number_format($variant['price'], 2)) : '(Uses Base Price)' ?></td>
                                <td><?= htmlspecialchars($variant['stock_quantity']) ?></td>
                                <td>
                                    <a href="edit_variant.php?id=<?= $variant['variant_id'] ?>&product_id=<?= $product_id ?>" class="btn btn-primary btn-sm">Edit</a>
                                    <a href="delete_variant.php?id=<?= $variant['variant_id'] ?>&product_id=<?= $product_id ?>" class="btn btn-danger btn-sm">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No variants found for this product. Click "Add New Variant" to create one.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>