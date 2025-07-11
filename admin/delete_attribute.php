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
$attribute_name = '';
$attribute_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// This is the most important part of the script. Before we can delete an attribute,
// we must check if any of its options are being used by any product variants.
function isAttributeInUse($pdo, $attribute_id) {
    $sql = "SELECT COUNT(*) 
            FROM product_variant_options pvo
            JOIN attribute_options ao ON pvo.option_id = ao.option_id
            WHERE ao.attribute_id = :attribute_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':attribute_id' => $attribute_id]);
    return $stmt->fetchColumn() > 0;
}

// --- FORM PROCESSING LOGIC (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    $attribute_id = filter_input(INPUT_POST, 'attribute_id', FILTER_VALIDATE_INT);

    if ($attribute_id) {
        if (isAttributeInUse($pdo, $attribute_id)) {
            $error_message = "Cannot delete this attribute because its options are currently in use by one or more product variants. Please remove those variants first.";
        } else {
            try {
                // Because the foreign key has ON DELETE CASCADE, deleting the attribute
                // will automatically delete all its associated options.
                $sql = "DELETE FROM attributes WHERE attribute_id = :attribute_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':attribute_id' => $attribute_id]);

                $_SESSION['success_message'] = "The attribute and all its options were successfully deleted.";
                header("Location: manage_attributes.php");
                exit();
            } catch (PDOException $e) {
                $error_message = "Database Error: Could not delete the attribute. " . $e->getMessage();
            }
        }
    } else {
        $error_message = "Invalid Attribute ID. Deletion failed.";
    }

// --- INITIAL PAGE LOAD (GET REQUEST) ---
} elseif ($attribute_id) {
    if (isAttributeInUse($pdo, $attribute_id)) {
        $error_message = "Cannot delete this attribute because its options are currently in use by one or more product variants. Please remove those variants first.";
    } else {
        try {
            $sql = "SELECT name FROM attributes WHERE attribute_id = :attribute_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':attribute_id' => $attribute_id]);
            $attribute = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($attribute) {
                $attribute_name = $attribute['name'];
            } else {
                $error_message = "No attribute found with this ID.";
            }
        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    }
} else {
    $error_message = "No Attribute ID provided.";
}

// Generate a CSRF token for the confirmation form.
generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Attribute - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Delete Attribute</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <a href="manage_attributes.php" class="btn btn-secondary">Back to Attributes</a>
        <?php else: ?>
            <div class="alert alert-warning">
                <h4 class="alert-heading">Are you sure?</h4>
                <p>You are about to permanently delete the attribute: <strong><?= htmlspecialchars($attribute_name) ?></strong>.</p>
                <hr>
                <p class="mb-0">This will also delete all of its associated options (e.g., deleting 'Color' will also delete 'Red', 'Blue', etc.). This action cannot be undone.</p>
            </div>

            <form action="delete_attribute.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="attribute_id" value="<?= htmlspecialchars($attribute_id) ?>">
                <button type="submit" class="btn btn-danger">Confirm Delete</button>
                <a href="manage_attributes.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>