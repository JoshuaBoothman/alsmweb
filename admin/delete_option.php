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
$option_value = '';
$option_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$attribute_id = filter_input(INPUT_GET, 'attribute_id', FILTER_VALIDATE_INT); // For redirection

// --- VALIDATE IDs ---
if (!$option_id || !$attribute_id) {
    header("Location: manage_attributes.php?error=invalidids");
    exit();
}

// Helper function to check if the option is in use
function isOptionInUse($pdo, $option_id) {
    $sql = "SELECT COUNT(*) FROM product_variant_options WHERE option_id = :option_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':option_id' => $option_id]);
    return $stmt->fetchColumn() > 0;
}

// --- FORM PROCESSING LOGIC (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    $option_id_post = filter_input(INPUT_POST, 'option_id', FILTER_VALIDATE_INT);

    if ($option_id_post === $option_id) {
        if (isOptionInUse($pdo, $option_id)) {
            $error_message = "Cannot delete this option because it is currently in use by a product variant.";
        } else {
            try {
                $sql = "DELETE FROM attribute_options WHERE option_id = :option_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':option_id' => $option_id]);

                $_SESSION['success_message'] = "The option was successfully deleted.";
                header("Location: manage_options.php?attribute_id=" . $attribute_id);
                exit();
            } catch (PDOException $e) {
                $error_message = "Database Error: Could not delete the option. " . $e->getMessage();
            }
        }
    } else {
        $error_message = "ID mismatch. Deletion failed.";
    }

// --- INITIAL PAGE LOAD (GET REQUEST) ---
} else {
    if (isOptionInUse($pdo, $option_id)) {
        $error_message = "Cannot delete this option because it is in use by at least one product variant. You must edit or remove the variant(s) using this option first.";
    } else {
        try {
            // Fetch option value for the confirmation message
            $sql = "SELECT value FROM attribute_options WHERE option_id = :option_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':option_id' => $option_id]);
            $option = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($option) {
                $option_value = $option['value'];
            } else {
                $error_message = "No option found with this ID.";
            }
        } catch (PDOException $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    }
}

// Generate a CSRF token for the confirmation form.
generate_csrf_token();

// --- HEADER ---
$page_title = 'Delete Option';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Delete Option</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <a href="manage_options.php?attribute_id=<?= $attribute_id ?>" class="btn btn-secondary">Back to Options</a>
    <?php else: ?>
        <div class="alert alert-warning">
            <h4 class="alert-heading">Are you sure?</h4>
            <p>You are about to permanently delete the option: <strong><?= htmlspecialchars($option_value) ?></strong>.</p>
            <hr>
            <p class="mb-0">This action cannot be undone.</p>
        </div>

        <form action="delete_option.php?id=<?= $option_id ?>&attribute_id=<?= $attribute_id ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="option_id" value="<?= htmlspecialchars($option_id) ?>">
            <button type="submit" class="btn btn-danger">Confirm Delete</button>
            <a href="manage_options.php?attribute_id=<?= $attribute_id ?>" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>