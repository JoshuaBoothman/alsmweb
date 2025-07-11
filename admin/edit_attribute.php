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
$attribute = null;
$attribute_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// --- FORM PROCESSING LOGIC (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    $attribute_id = filter_input(INPUT_POST, 'attribute_id', FILTER_VALIDATE_INT);
    $attribute_name = trim($_POST['name']);
    $errors = [];

    // Validation
    if (!$attribute_id) {
        $errors[] = "Invalid Attribute ID.";
    }
    if (empty($attribute_name)) {
        $errors[] = "Attribute Name is required.";
    }

    // Check for duplicates (excluding the current attribute)
    if (empty($errors)) {
        try {
            $sql_check = "SELECT attribute_id FROM attributes WHERE name = :name AND attribute_id != :attribute_id";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':name' => $attribute_name, ':attribute_id' => $attribute_id]);
            if ($stmt_check->fetch()) {
                $errors[] = "Another attribute with this name already exists.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error during duplicate check: " . $e->getMessage();
        }
    }

    // If no errors, perform the update
    if (empty($errors)) {
        try {
            $sql = "UPDATE attributes SET name = :name WHERE attribute_id = :attribute_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':name' => $attribute_name, ':attribute_id' => $attribute_id]);

            $_SESSION['success_message'] = "Attribute name updated successfully!";
            header("Location: manage_attributes.php");
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not update attribute. " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
    // Repopulate attribute array for form redisplay on error
    $attribute = ['attribute_id' => $attribute_id, 'name' => $attribute_name];

// --- INITIAL PAGE LOAD (GET REQUEST) ---
} elseif ($attribute_id) {
    try {
        $sql = "SELECT * FROM attributes WHERE attribute_id = :attribute_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':attribute_id' => $attribute_id]);
        $attribute = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attribute) {
            $error_message = "Attribute not found.";
            $attribute = null;
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: Could not fetch attribute. " . $e->getMessage();
    }
} else {
    $error_message = "No Attribute ID provided.";
}

// Generate a CSRF token for the form to be displayed.
generate_csrf_token();

// --- HEADER ---
$page_title = 'Edit Attribute';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Edit Attribute</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <?php if ($attribute): ?>
    <form action="edit_attribute.php?id=<?= htmlspecialchars($attribute_id) ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="attribute_id" value="<?= htmlspecialchars($attribute['attribute_id']) ?>">
        
        <div class="mb-3">
            <label for="name" class="form-label">Attribute Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($attribute['name']) ?>" required>
        </div>

        <button type="submit" class="btn btn-primary">Update Attribute</button>
        <a href="manage_attributes.php" class="btn btn-secondary">Cancel</a>
    </form>
    <?php else: ?>
        <a href="manage_attributes.php" class="btn btn-primary">Back to Attribute Management</a>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>