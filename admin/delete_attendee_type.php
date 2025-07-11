<?php
// admin/delete_attendee_type.php

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
$type_name = '';
$type_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// --- VALIDATE ID ---
if (!$type_id) {
    header("Location: manage_attendee_types.php");
    exit();
}

// --- DEPENDENCY CHECK FUNCTION ---
// Checks if an attendee type is currently linked to any attendees.
function hasDependencies($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendees WHERE type_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() > 0;
}

// --- FORM PROCESSING LOGIC (DELETION) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    $type_id_post = filter_input(INPUT_POST, 'type_id', FILTER_VALIDATE_INT);

    if ($type_id_post === $type_id) {
        // Final safety check before deleting.
        if (hasDependencies($pdo, $type_id)) {
            $_SESSION['error_message'] = "Cannot delete this type because it is in use by one or more registered attendees.";
            header("Location: manage_attendee_types.php");
            exit();
        }
        try {
            $sql = "DELETE FROM attendee_types WHERE type_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $type_id]);

            $_SESSION['success_message'] = "The attendee type was successfully deleted.";
            header("Location: manage_attendee_types.php");
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not delete the attendee type. " . $e->getMessage();
        }
    } else {
        $error_message = "ID mismatch. Deletion failed.";
    }

// --- DATA FETCHING for confirmation page ---
} else {
    try {
        if (hasDependencies($pdo, $type_id)) {
            $error_message = "This attendee type cannot be deleted because it is currently assigned to one or more attendees. Please reassign or remove those attendees first.";
        } else {
            $stmt = $pdo->prepare("SELECT type_name FROM attendee_types WHERE type_id = :id");
            $stmt->execute([':id' => $type_id]);
            $attendee_type = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($attendee_type) {
                $type_name = $attendee_type['type_name'];
            } else {
                $error_message = "Attendee Type not found.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}

// Generate a CSRF token for the confirmation form.
generate_csrf_token();

// --- HEADER ---
$page_title = 'Delete Attendee Type';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Delete Attendee Type</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <a href="manage_attendee_types.php" class="btn btn-secondary">&laquo; Back to Attendee Types</a>
    <?php else: ?>
        <div class="alert alert-warning">
            <h4 class="alert-heading">Are you sure?</h4>
            <p>You are about to permanently delete the attendee type: <strong><?= htmlspecialchars($type_name) ?></strong>.</p>
            <hr>
            <p class="mb-0">This action cannot be undone.</p>
        </div>

        <form action="delete_attendee_type.php?id=<?= $type_id ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="type_id" value="<?= htmlspecialchars($type_id) ?>">
            <button type="submit" class="btn btn-danger">Confirm Delete</button>
            <a href="manage_attendee_types.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
