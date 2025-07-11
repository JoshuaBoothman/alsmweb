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

// --- FORM PROCESSING LOGIC ---
$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Validate the CSRF token on submission
    validate_csrf_token();

    $attribute_name = trim($_POST['attribute_name']);
    $errors = [];

    // Validation
    if (empty($attribute_name)) {
        $errors[] = "Attribute Name is required.";
    }

    // Check for duplicate attribute name
    if (empty($errors)) {
        try {
            $sql_check = "SELECT COUNT(*) FROM attributes WHERE name = :name";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':name' => $attribute_name]);
            if ($stmt_check->fetchColumn() > 0) {
                $errors[] = "An attribute with this name already exists.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error during duplicate check: " . $e->getMessage();
        }
    }

    // If no errors, insert into the database
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO attributes (name) VALUES (:name)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':name' => $attribute_name]);

            $_SESSION['success_message'] = "Attribute '".htmlspecialchars($attribute_name)."' was created successfully!";
            header("Location: manage_attributes.php");
            exit();

        } catch (PDOException $e) {
            $error_message = "Database Error: Could not create the attribute. " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// 2. Generate a CSRF token for the form to be displayed
generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Attribute - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Add New Attribute</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>

        <form action="add_attribute.php" method="POST">
            <!-- 3. Add the hidden CSRF token field to the form -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="mb-3">
                <label for="attribute_name" class="form-label">Attribute Name</label>
                <input type="text" class="form-control" id="attribute_name" name="attribute_name" placeholder="e.g., Color, Size, Material" value="<?= isset($_POST['attribute_name']) ? htmlspecialchars($_POST['attribute_name']) : '' ?>" required>
                <div class="form-text">This is the type of variation (e.g., "Color"). You will add the specific options (e.g., "Red", "Blue") in the next step.</div>
            </div>

            <button type="submit" class="btn btn-primary">Save Attribute</button>
            <a href="manage_attributes.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>