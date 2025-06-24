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
$category = null; // This will hold the category data we are editing.
$category_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); // Get category ID from the URL.

// --- FORM PROCESSING LOGIC (HANDLE POST REQUEST) ---
// This block runs ONLY when the admin submits the edit form.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // We get the category_id from a hidden field in the form.
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);

    // 1. Retrieve and sanitize the submitted form data.
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $errors = [];

    // 2. Server-Side Validation.
    if (empty($category_name)) {
        $errors[] = "Category Name is required.";
    } elseif (!$category_id) {
        $errors[] = "Invalid Category ID. Cannot update.";
    }

    // 3. Check for duplicates (but exclude the current category being edited).
    if (empty($errors)) {
        try {
            $sql_check = "SELECT category_id FROM categories WHERE category_name = :category_name AND category_id != :category_id";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':category_name' => $category_name, ':category_id' => $category_id]);
            if ($stmt_check->fetch()) {
                $errors[] = "Another category with this name already exists.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error during duplicate check: " . $e->getMessage();
        }
    }

    // 4. If validation passes, proceed with the database UPDATE.
    if (empty($errors)) {
        try {
            $sql = "UPDATE categories SET 
                        category_name = :category_name, 
                        description = :description 
                    WHERE category_id = :category_id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':category_name' => $category_name,
                ':description' => $description,
                ':category_id' => $category_id
            ]);

            // Set a success message and redirect back to the management page.
            $_SESSION['success_message'] = "Category '".htmlspecialchars($category_name)."' was updated successfully!";
            header("Location: manage_categories.php");
            exit();

        } catch (PDOException $e) {
            $error_message = "Database Error: Could not update the category. " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
    // If there was a validation error, we need to repopulate the $category variable to refill the form with the attempted values.
    $category = $_POST;
    // We need to ensure category_id is still in the array for the form's hidden field.
    $category['category_id'] = $category_id;


} elseif ($category_id) {
    // --- DATA FETCHING LOGIC (HANDLE GET REQUEST) ---
    // This block runs when the page is first loaded to get the category data from the DB.
    try {
        $sql = "SELECT * FROM categories WHERE category_id = :category_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':category_id' => $category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            $error_message = "No category found with this ID.";
            $category = null; // Ensure the form doesn't try to render.
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: Could not fetch category details. " . $e->getMessage();
    }
} else {
    // This runs if no ID was provided in the URL at all.
    $error_message = "No Category ID provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Edit Category</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>

        <?php if ($category): // Only show the form if we successfully found a category to edit ?>
        <form action="edit_category.php?id=<?= htmlspecialchars($category_id) ?>" method="POST">
            <input type="hidden" name="category_id" value="<?= htmlspecialchars($category['category_id']) ?>">
            
            <div class="mb-3">
                <label for="category_name" class="form-label">Category Name</label>
                <input type="text" class="form-control" id="category_name" name="category_name" value="<?= htmlspecialchars($category['category_name']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($category['description']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Update Category</button>
            <a href="manage_categories.php" class="btn btn-secondary">Cancel</a>
        </form>
        <?php else: ?>
            <p>The requested category could not be found.</p>
            <a href="manage_categories.php" class="btn btn-primary">Back to Category Management</a>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>