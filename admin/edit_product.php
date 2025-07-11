<?php
// admin/edit_product.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$product = null;
$categories = [];
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$product_id) {
    header("Location: manage_products.php");
    exit();
}

// --- FORM SUBMISSION LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Retrieve and validate form data
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $product_name = trim($_POST['product_name']);
    $description = trim($_POST['description']);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $base_price = trim($_POST['base_price']);
    $stock_quantity = filter_input(INPUT_POST, 'stock_quantity', FILTER_VALIDATE_INT);
    $current_image_path = $_POST['current_image_path']; // Get existing image path
    $errors = [];

    // 2. Server-Side Validation
    if (!$product_id) $errors[] = "Invalid Product ID.";
    if (empty($product_name)) $errors[] = "Product Name is required.";
    if (!$category_id) $errors[] = "Please select a valid category.";
    if (!is_numeric($base_price) || $base_price < 0) $errors[] = "Base Price must be a valid number.";
    if ($stock_quantity === false || $stock_quantity < 0) $errors[] = "Stock must be a valid integer.";

    // 3. Handle Image Upload
    $new_image_path = $current_image_path; // Assume the image is not changed
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $upload_dir = '../public_html/assets/images/products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = uniqid() . '-' . basename($_FILES['product_image']['name']);
        $target_file = $upload_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Basic validation
        $check = getimagesize($_FILES['product_image']['tmp_name']);
        if ($check === false) {
            $errors[] = "File is not an image.";
        }
        if ($_FILES['product_image']['size'] > 2000000) { // 2MB limit
            $errors[] = "Sorry, your file is too large.";
        }
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            $errors[] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        }

        if (empty($errors)) {
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                // File uploaded successfully, set the new path
                $new_image_path = '/alsmweb/public_html/assets/images/products/' . $file_name;
                // Optionally, delete the old image if it exists and is not a placeholder
                if ($current_image_path && file_exists('..' . $current_image_path)) {
                    unlink('..' . $current_image_path);
                }
            } else {
                $errors[] = "Sorry, there was an error uploading your file.";
            }
        }
    }

    // 4. If validation passes, update the database
    if (empty($errors)) {
        try {
            $sql = "UPDATE products SET 
                        product_name = :product_name, 
                        description = :description, 
                        category_id = :category_id, 
                        base_price = :base_price, 
                        stock_quantity = :stock_quantity,
                        image_path = :image_path
                    WHERE product_id = :product_id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':product_name' => $product_name,
                ':description' => $description,
                ':category_id' => $category_id,
                ':base_price' => $base_price,
                ':stock_quantity' => $stock_quantity,
                ':image_path' => $new_image_path,
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
    
    $product = $_POST;
    $product['product_id'] = $product_id;
} else {
    // --- INITIAL PAGE LOAD LOGIC ---
    try {
        $stmt_cat = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
        $categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

        $sql_prod = "SELECT * FROM products WHERE product_id = :product_id";
        $stmt_prod = $pdo->prepare($sql_prod);
        $stmt_prod->execute([':product_id' => $product_id]);
        $product = $stmt_prod->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $error_message = "No product found with this ID.";
            $product = null;
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}

// --- HEADER ---
$page_title = 'Edit Product';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">Edit Product</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <?php if ($product): ?>
    <form action="edit_product.php?id=<?= htmlspecialchars($product_id) ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['product_id']) ?>">
        <input type="hidden" name="current_image_path" value="<?= htmlspecialchars($product['image_path']) ?>">

        <div class="row">
            <div class="col-md-8">
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
            </div>
            <div class="col-md-4">
                <label class="form-label">Current Image</label>
                <img src="<?= htmlspecialchars($product['image_path'] ?? 'https://placehold.co/400x300?text=No+Image') ?>" class="img-fluid rounded border mb-2" alt="Current product image">
            </div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($product['description']) ?></textarea>
        </div>

        <div class="mb-3">
            <label for="product_image" class="form-label">Upload New Image (Optional)</label>
            <input class="form-control" type="file" id="product_image" name="product_image">
            <div class="form-text">Upload a new image to replace the current one. Leave blank to keep the existing image.</div>
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
    <?php else: ?>
         <a href="manage_products.php" class="btn btn-primary">Back to Product Management</a>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
