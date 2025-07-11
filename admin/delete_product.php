<?php
// admin/delete_product.php

// --- SECURITY AND INITIALIZATION ---
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND HELPERS ---
require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$product_name = '';
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// --- VALIDATE ID ---
if (!$product_id) {
    header("Location: manage_products.php");
    exit();
}

// --- DEPENDENCY CHECK FUNCTION ---
// Checks if a product is part of any order. If so, it shouldn't be deleted.
function isProductInUse($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orderitems WHERE product_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() > 0;
}

// --- FORM PROCESSING LOGIC (DELETION) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token(); // Validate the token first
    $product_id_post = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

    if ($product_id_post === $product_id) {
        // Final safety check before deleting.
        if (isProductInUse($pdo, $product_id)) {
            $_SESSION['error_message'] = "Cannot delete this product because it is part of one or more existing orders. Consider deactivating it instead.";
            header("Location: manage_products.php");
            exit();
        }
        try {
            // Perform a "soft delete" by updating a flag instead of permanently deleting.
            $sql = "UPDATE products SET is_deleted = 1, is_active = 0 WHERE product_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $product_id]);

            $_SESSION['success_message'] = "The product was successfully deleted.";
            header("Location: manage_products.php");
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not delete the product. " . $e->getMessage();
        }
    } else {
        $error_message = "ID mismatch. Deletion failed.";
    }

// --- DATA FETCHING for confirmation page ---
} else {
    try {
        if (isProductInUse($pdo, $product_id)) {
            $error_message = "This product cannot be deleted because it is part of existing order records. To make it unavailable, please edit the product and uncheck 'Active'.";
        } else {
            $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = :id");
            $stmt->execute([':id' => $product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($product) {
                $product_name = $product['product_name'];
            } else {
                $error_message = "Product not found.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}

// Generate a token for the confirmation form
generate_csrf_token();

// --- HEADER ---
$page_title = 'Delete Product';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Delete Product</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <a href="manage_products.php" class="btn btn-secondary">&laquo; Back to Products</a>
    <?php else: ?>
        <div class="alert alert-warning">
            <h4 class="alert-heading">Are you sure?</h4>
            <p>You are about to delete the product: <strong><?= htmlspecialchars($product_name) ?></strong>.</p>
            <hr>
            <p class="mb-0">This will make it inactive and remove it from public view. This action cannot be undone.</p>
        </div>

        <form action="delete_product.php?id=<?= $product_id ?>" method="POST">
            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product_id) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <button type="submit" class="btn btn-danger">Confirm Delete</button>
            <a href="manage_products.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
