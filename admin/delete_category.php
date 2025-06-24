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
$category_name = '';
$category_id = null;

// --- FORM PROCESSING LOGIC (HANDLE POST REQUEST FOR DELETION) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);

    if ($category_id) {
        try {
            // First, check if any products are using this category.
            // This prevents orphaning products and maintains data integrity.
            $sql_check = "SELECT COUNT(*) FROM products WHERE category_id = :category_id";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':category_id' => $category_id]);
            
            if ($stmt_check->fetchColumn() > 0) {
                // If products are using this category, prevent deletion.
                $error_message = "Cannot delete this category because it is currently assigned to one or more products. Please reassign the products to another category before deleting this one.";
            } else {
                // If no products are using it, proceed with deletion.
                $sql = "DELETE FROM categories WHERE category_id = :category_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':category_id' => $category_id]);

                // Set a success message and redirect back to the management page.
                $_SESSION['success_message'] = "The category was successfully deleted.";
                header("Location: manage_categories.php");
                exit();
            }

        } catch (PDOException $e) {
            $error_message = "Database Error: Could not delete the category. " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid Category ID. Deletion failed.";
    }

// --- DATA FETCHING FOR CONFIRMATION PAGE (HANDLE GET REQUEST) ---
} else {
    $category_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($category_id) {
        try {
            // We only need the name for the confirmation message.
            $sql = "SELECT category_name FROM categories WHERE category_id = :category_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':category_id' => $category_id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($category) {
                $category_name = $category['category_name'];
            } else {
                $error_message = "No category found with this ID.";
            }
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not fetch category details. " . $e->getMessage();
        }
    } else {
        $error_message = "No Category ID provided.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Category - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Delete Category</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <a href="manage_categories.php" class="btn btn-secondary">Back to Category Management</a>
        <?php else: ?>
            <div class="alert alert-warning">
                <h4 class="alert-heading">Are you sure?</h4>
                <p>You are about to permanently delete the category: <strong><?= htmlspecialchars($category_name) ?></strong>.</p>
                <hr>
                <p class="mb-0">This action cannot be undone. Any products in this category will need to be reassigned.</p>
            </div>

            <form action="delete_category.php" method="POST">
                <input type="hidden" name="category_id" value="<?= htmlspecialchars($category_id) ?>">
                
                <button type="submit" class="btn btn-danger">Confirm Delete</button>
                <a href="manage_categories.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>