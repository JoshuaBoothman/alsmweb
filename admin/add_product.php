<?php
// --- SECURITY AND INITIALIZATION ---
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND DATABASE CONNECTION ---
require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$categories = [];

// --- DATA FETCHING for Category Dropdown ---
// This part runs on every page load to populate the category dropdown.
try {
    $stmt = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch categories. " . $e->getMessage();
}

// --- FORM PROCESSING LOGIC ---
// This block only runs when the form is submitted via POST.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    // 1. Retrieve and sanitize form data
    $product_name = trim($_POST['product_name']);
    $description = trim($_POST['description']);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $base_price = trim($_POST['base_price']);
    $stock_quantity = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT);
    $errors = [];

    // 2. Server-Side Validation
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
    
    // 3. If validation passes, proceed with database insertion.
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO products (product_name, description, category_id, base_price, stock_quantity) VALUES (:product_name, :description, :category_id, :base_price, :stock_quantity)";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                ':product_name' => $product_name,
                ':description' => $description,
                ':category_id' => $category_id,
                ':base_price' => $base_price,
                ':stock_quantity' => $stock_quantity
            ]);

            // Set success message and redirect
            $_SESSION['success_message'] = "Product '".htmlspecialchars($product_name)."' was created successfully!";
            header("Location: manage_products.php");
            exit();

        } catch (PDOException $e) {
            $error_message = "Database Error: Could not create the product. " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Generate a CSRF token to be included in the form.
generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Add New Product</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>

        <?php if (empty($categories) && $_SERVER["REQUEST_METHOD"] != "POST"): ?>
            <div class="alert alert-warning">
                You must <a href="add_category.php">create a category</a> before you can add a product.
            </div>
        <?php else: ?>
        <form action="add_product.php" method="POST">
            <!-- CSRF Token for security -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="mb-3">
                <label for="product_name" class="form-label">Product Name</label>
                <input type="text" class="form-control" id="product_name" name="product_name" value="<?= isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : '' ?>" required>
            </div>

            <div class="mb-3">
                <label for="category_id" class="form-label">Category</label>
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value="">-- Select a Category --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['category_id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="base_price" class="form-label">Base Price</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="base_price" name="base_price" step="0.01" min="0" value="<?= isset($_POST['base_price']) ? htmlspecialchars($_POST['base_price']) : '' ?>" required>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="stock_quantity" class="form-label">Stock Quantity</label>
                    <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" min="0" value="<?= isset($_POST['stock_quantity']) ? htmlspecialchars($_POST['stock_quantity']) : '' ?>" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Product</button>
            <a href="manage_products.php" class="btn btn-secondary">Cancel</a>
        </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>