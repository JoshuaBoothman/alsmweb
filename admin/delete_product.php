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
$error_message = '';
$product = null;
$categories = [];
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// --- DATA FETCHING (for both categories and the specific product) ---
// Fetch all categories for the dropdown menu
try {
    $stmt_cat = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
    $categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch categories. " . $e->getMessage();
}

// --- FORM SUBMISSION LOGIC (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Retrieve and validate form data
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $product_name = trim($_POST['product_name']);
    $description = trim($_POST['description']);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $base_price = trim($_POST['base_price']);
    $stock_quantity = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT);
    $errors = [];

    // 2. Server-Side Validation
    if (!$product_id) {
        $errors[] = "Invalid Product ID. Update failed.";
    }
    if (empty($product_name)) {
        $errors[] = "Product Name is required.";
    }
    if (!$category_id) {
        $errors[] = "Please select a valid category.";
    }
    if (!is_numeric($base_price) || $base_price < 0) {
        $errors[] = "Base Price must be a valid, non-negative number.";
    }
    if ($stock_quantity === false || $stock_quantity < 0) {
        $errors[] = "Stock Quantity must be a valid, non-negative integer.";
    }

    // 3. If validation passes, update the database
    if (empty($errors)) {
        try {
            $sql = "UPDATE products SET 
                        product_name = :product_name, 
                        description = :description, 
                        category_id = :category_id, 
                        base_price = :base_price, 
                        stock_quantity = :stock_quantity 
                    WHERE product_id = :product_id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':product_name' => $product_name,
                ':description' => $description,
                ':category_id' => $category_id,
                ':base_price' => $base_price,
                ':stock_quantity' => $stock_quantity,
                ':product_id' => $product_id
            ]);

            $_SESSION['success_message'] = "Product '".htmlspecialchars($product_name)."' was updated successfully!";
            header("Location: manage_products.php");
            exit();

        } catch (PDOException $e) {
            $error_message = "Database Error: Could not update the product. " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
    
    // If validation fails, repopulate the product array to refill the form with submitted values
    $product = $_POST;
    $product['product_id'] = $product_id; // ensure ID is preserved

// --- INITIAL PAGE LOAD LOGIC (GET REQUEST) ---
} elseif ($product_id && empty($error_message)) {
    try {
        $sql_prod = "SELECT * FROM products WHERE product_id = :product_id";
        $stmt_prod = $pdo->prepare($sql_prod);
        $stmt_prod->execute([':product_id' => $product_id]);
        $product = $stmt_prod->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $error_message = "No product found with this ID.";
            $product = null;
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: Could not fetch product details. " . $e->getMessage();
    }
} elseif (!$product_id) {
    $error_message = "No Product ID provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Edit Product</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>

        <?php if ($product): ?>
        <form action="edit_product.php?id=<?= htmlspecialchars($product_id) ?>" method="POST">
            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['product_id']) ?>">

            <div class="mb-3">
                <label for="product_name" class="form-label">Product Name</label>
                <input type="text" class="form-control" id="product_name" name="product_name" value="<?= htmlspecialchars($product['product_name']) ?>" required>
            </div>

            <div class="mb-3">
                <label for="category_id" class="form-label">Category</label>
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value="">-- Select a Category --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['category_id'] ?>" <?= ($product['category_id'] == $category['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($product['description']) ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="base_price" class="form-label">Base Price</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="base_price" name="base_price" step="0.01" min="0" value="<?= htmlspecialchars($product['base_price']) ?>" required>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="stock_quantity" class="form-label">Stock Quantity</label>
                    <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" min="0" value="<?= htmlspecialchars($product['stock_quantity']) ?>" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Update Product</button>
            <a href="manage_products.php" class="btn btn-secondary">Cancel</a>
        </form>
        <?php elseif(empty($error_message)): ?>
            <div class="alert alert-warning">Loading product data...</div>
        <?php else: ?>
             <a href="manage_products.php" class="btn btn-primary">Back to Product Management</a>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.