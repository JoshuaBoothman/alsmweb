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
$attribute = null;
$error_message = '';
$attribute_id = filter_input(INPUT_GET, 'attribute_id', FILTER_VALIDATE_INT);

// --- VALIDATE ATTRIBUTE ID ---
if (!$attribute_id) {
    header("Location: manage_attributes.php?error=noattributeid");
    exit();
}

// --- DATA FETCHING (for page context) ---
try {
    $sql_attr = "SELECT name FROM attributes WHERE attribute_id = :attribute_id";
    $stmt_attr = $pdo->prepare($sql_attr);
    $stmt_attr->execute([':attribute_id' => $attribute_id]);
    $attribute = $stmt_attr->fetch(PDO::FETCH_ASSOC);

    if (!$attribute) {
        throw new Exception("Attribute not found.");
    }
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}

// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Re-validate attribute_id from the hidden form field
    $attribute_id_post = filter_input(INPUT_POST, 'attribute_id', FILTER_VALIDATE_INT);
    $option_value = trim($_POST['option_value']);
    $errors = [];

    // Validation
    if (!$attribute_id_post || $attribute_id_post != $attribute_id) {
        $errors[] = "Attribute ID mismatch.";
    }
    if (empty($option_value)) {
        $errors[] = "Option Value is required.";
    }

    // Check for duplicate option value for this specific attribute
    if (empty($errors)) {
        try {
            $sql_check = "SELECT COUNT(*) FROM attribute_options WHERE attribute_id = :attribute_id AND value = :value";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([
                ':attribute_id' => $attribute_id,
                ':value' => $option_value
            ]);
            if ($stmt_check->fetchColumn() > 0) {
                $errors[] = "This option value already exists for this attribute.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error during duplicate check: " . $e->getMessage();
        }
    }
    
    // If no errors, insert into the database
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO attribute_options (attribute_id, value) VALUES (:attribute_id, :value)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':attribute_id' => $attribute_id,
                ':value' => $option_value
            ]);

            $_SESSION['success_message'] = "Option '".htmlspecialchars($option_value)."' was added successfully!";
            header("Location: manage_options.php?attribute_id=" . $attribute_id);
            exit();

        } catch (PDOException $e) {
            $error_message = "Database Error: Could not create the option. " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Option - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <?php if (!$attribute && !$error_message): ?>
            <div class="alert alert-danger">Attribute not found.</div>
            <a href="manage_attributes.php" class="btn btn-secondary">Back to Attributes</a>
        <?php else: ?>
            <h1 class="mb-4">Add New Option for: <strong><?= htmlspecialchars($attribute['name']) ?></strong></h1>

            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php endif; ?>

            <form action="add_option.php?attribute_id=<?= $attribute_id ?>" method="POST">
                <input type="hidden" name="attribute_id" value="<?= $attribute_id ?>">
                <div class="mb-3">
                    <label for="option_value" class="form-label">Option Value</label>
                    <input type="text" class="form-control" id="option_value" name="option_value" placeholder="e.g., Red, Large, Cotton" value="<?= isset($_POST['option_value']) ? htmlspecialchars($_POST['option_value']) : '' ?>" required>
                </div>

                <button type="submit" class="btn btn-primary">Save Option</button>
                <a href="manage_options.php?attribute_id=<?= $attribute_id ?>" class="btn btn-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>