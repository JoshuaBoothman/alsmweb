<?php
// --- SECURITY AND INITIALIZATION ---
session_start();
// This entire block is for security and setup. It ensures only logged-in admins can access this page.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // If the user is not an admin or not logged in, redirect them to the login page with an error.
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND DATABASE CONNECTION ---
// We need to connect to the database to insert the new category data.
require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// --- FORM PROCESSING LOGIC ---
// These variables will hold any messages to display to the admin.
$error_message = '';
$success_message = '';

// Check if the request method is POST, which means the "Add Category" form has been submitted.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    // 1. Retrieve and sanitize the form data
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $errors = [];

    // 2. Server-Side Validation
    if (empty($category_name)) {
        $errors[] = "Category Name is required.";
    }

    // 3. Check for duplicate category name before inserting
    if (empty($errors)) {
        try {
            $sql_check = "SELECT COUNT(*) FROM categories WHERE category_name = :category_name";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':category_name' => $category_name]);
            if ($stmt_check->fetchColumn() > 0) {
                $errors[] = "A category with this name already exists.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error during duplicate check: " . $e->getMessage();
        }
    }

    // 4. If validation passes and no duplicates are found, proceed with database insertion.
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO categories (category_name, description) VALUES (:category_name, :description)";
            $stmt = $pdo->prepare($sql);

            // Bind the values to the placeholders in the SQL query for security.
            $stmt->execute([
                ':category_name' => $category_name,
                ':description' => $description
            ]);

            // Set a success message in the session and redirect back to the main management page.
            // This is a good pattern, as it prevents form resubmission on refresh.
            $_SESSION['success_message'] = "Category '".htmlspecialchars($category_name)."' was created successfully!";
            header("Location: manage_categories.php");
            exit();

        } catch (PDOException $e) {
            // If the database query fails, set a generic error message.
            $error_message = "Database Error: Could not create the category. " . $e->getMessage();
        }
    } else {
        // If there were validation errors, combine them into a single message.
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
    <title>Add New Category - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Add New Category</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <form action="add_category.php" method="POST">
            <!-- CSRF Token for security -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="mb-3">
                <label for="category_name" class="form-label">Category Name</label>
                <input type="text" class="form-control" id="category_name" name="category_name" value="<?= isset($_POST['category_name']) ? htmlspecialchars($_POST['category_name']) : '' ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Save Category</button>
            <a href="manage_categories.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>