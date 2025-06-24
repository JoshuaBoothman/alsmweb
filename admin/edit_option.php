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
$option = null;
$attribute = null;
$option_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$attribute_id = filter_input(INPUT_GET, 'attribute_id', FILTER_VALIDATE_INT);

// --- VALIDATE IDs ---
if (!$option_id || !$attribute_id) {
    header("Location: manage_attributes.php?error=invalidids");
    exit();
}

// --- FORM PROCESSING LOGIC (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Re-validate IDs from hidden form fields
    $option_id_post = filter_input(INPUT_POST, 'option_id', FILTER_VALIDATE_INT);
    $attribute_id_post = filter_input(INPUT_POST, 'attribute_id', FILTER_VALIDATE_INT);
    $option_value = trim($_POST['value']);
    $errors = [];

    // Validation
    if ($option_id_post !== $option_id || $attribute_id_post !== $attribute_id) {
        $errors[] = "ID mismatch. Update failed.";
    }
    if (empty($option_value)) {
        $errors[] = "Option Value cannot be empty.";
    }

    // Check for duplicate option value for this attribute, excluding the current option
    if (empty($errors)) {
        try {
            $sql_check = "SELECT option_id FROM attribute_options WHERE attribute_id = :attribute_id AND value = :value AND option_id != :option_id";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([
                ':attribute_id' => $attribute_id,
                ':value' => $option_value,
                ':option_id' => $option_id
            ]);
            if ($stmt_check->fetch()) {
                $errors[] = "This option value already exists for this attribute.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error during duplicate check: " . $e->getMessage();
        }
    }

    // If no errors, update the database
    if (empty($errors)) {
        try {
            $sql = "UPDATE attribute_options SET value = :value WHERE option_id = :option_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':value' => $option_value, ':option_id' => $option_id]);

            $_SESSION['success_message'] = "Option updated successfully!";
            header("Location: manage_options.php?attribute_id=" . $attribute_id);
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not update the option. " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
     // Repopulate arrays for form redisplay on error
    $option = ['option_id' => $option_id, 'value' => $option_value, 'attribute_id' => $attribute_id];
    $attribute = ['attribute_id' => $attribute_id, 'name' => $_POST['attribute_name']];


// --- INITIAL PAGE LOAD (GET REQUEST) ---
} else {
    try {
        // Fetch the attribute details for context
        $sql_attr = "SELECT name FROM attributes WHERE attribute_id = :attribute_id";
        $stmt_attr = $pdo->prepare($sql_attr);
        $stmt_attr->execute([':attribute_id' => $attribute_id]);
        $attribute = $stmt_attr->fetch(PDO::FETCH_ASSOC);

        // Fetch the specific option to be edited
        $sql_opt = "SELECT * FROM attribute_options WHERE option_id = :option_id";
        $stmt_opt = $pdo->prepare($sql_opt);
        $stmt_opt->execute([':option_id' => $option_id]);
        $option = $stmt_opt->fetch(PDO::FETCH_ASSOC);

        if (!$attribute || !$option) {
            $error_message = "Attribute or Option not found.";
            $option = null; // Prevent form rendering
        }

    } catch (PDOException $e) {
        $error_message = "Database Error: Could not fetch details. " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Option - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
            <a href="manage_attributes.php" class="btn btn-secondary">Back to Attributes</a>
        <?php elseif ($option && $attribute): ?>
            <h1 class="mb-4">Edit Option for: <strong><?= htmlspecialchars($attribute['name']) ?></strong></h1>
            
            <form action="edit_option.php?id=<?= $option_id ?>&attribute_id=<?= $attribute_id ?>" method="POST">
                <input type="hidden" name="option_id" value="<?= htmlspecialchars($option['option_id']) ?>">
                <input type="hidden" name="attribute_id" value="<?= htmlspecialchars($attribute_id) ?>">
                <input type="hidden" name="attribute_name" value="<?= htmlspecialchars($attribute['name']) ?>">


                <div class="mb-3">
                    <label for="value" class="form-label">Option Value</label>
                    <input type="text" class="form-control" id="value" name="value" value="<?= htmlspecialchars($option['value']) ?>" required>
                </div>

                <button type="submit" class="btn btn-primary">Update Option</button>
                <a href="manage_options.php?attribute_id=<?= $attribute_id ?>" class="btn btn-secondary">Cancel</a>
            </form>
        <?php else: ?>
            <div class="alert alert-info">Loading...</div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>