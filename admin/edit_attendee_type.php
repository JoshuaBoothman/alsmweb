<?php
// admin/edit_attendee_type.php

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
$attendee_type = null;
$type_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// --- VALIDATE ID ---
if (!$type_id) {
    header("Location: manage_attendee_types.php");
    exit();
}

// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    $type_id_post = filter_input(INPUT_POST, 'type_id', FILTER_VALIDATE_INT);
    $type_name = trim($_POST['type_name']);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    // Get the values from the new checkboxes
    $requires_pilot_details = isset($_POST['requires_pilot_details']) ? 1 : 0;
    $requires_junior_details = isset($_POST['requires_junior_details']) ? 1 : 0;
    
    $errors = [];

    // Validation
    if ($type_id_post !== $type_id) $errors[] = "ID mismatch.";
    if (empty($type_name)) $errors[] = "Type Name is required.";
    if ($price === false || $price < 0) $errors[] = "Price must be a valid, non-negative number.";

    // Check for duplicate name (excluding the current record)
    if (empty($errors)) {
        try {
            $sql_check = "SELECT type_id FROM attendee_types WHERE type_name = :type_name AND type_id != :type_id";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':type_name' => $type_name, ':type_id' => $type_id]);
            if ($stmt_check->fetch()) {
                $errors[] = "Another attendee type with this name already exists.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error during duplicate check: " . $e->getMessage();
        }
    }

    // If validation passes, update the database.
    if (empty($errors)) {
        try {
            $sql = "UPDATE attendee_types SET 
                        type_name = :type_name, 
                        price = :price, 
                        is_active = :is_active,
                        requires_pilot_details = :requires_pilot,
                        requires_junior_details = :requires_junior
                    WHERE type_id = :type_id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':type_name' => $type_name,
                ':price' => $price,
                ':is_active' => $is_active,
                ':requires_pilot' => $requires_pilot_details,
                ':requires_junior' => $requires_junior_details,
                ':type_id' => $type_id
            ]);

            $_SESSION['success_message'] = "Attendee Type '".htmlspecialchars($type_name)."' was updated successfully!";
            header("Location: manage_attendee_types.php");
            exit();

        } catch (PDOException $e) {
            $error_message = "Database Error: Could not update the attendee type. " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
    // If there's an error, repopulate the variable to refill the form.
    $attendee_type = $_POST;
    $attendee_type['type_id'] = $type_id;
}

// --- DATA FETCHING for page load ---
if (!$attendee_type) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM attendee_types WHERE type_id = :id");
        $stmt->execute([':id' => $type_id]);
        $attendee_type = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$attendee_type) {
            $error_message = "Attendee Type not found.";
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}

// Generate a CSRF token for the form to be displayed.
generate_csrf_token();

// --- HEADER ---
$page_title = 'Edit Attendee Type';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Edit Attendee Type</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <?php if ($attendee_type): ?>
    <form action="edit_attendee_type.php?id=<?= $type_id ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="type_id" value="<?= htmlspecialchars($attendee_type['type_id']) ?>">
        
        <div class="mb-3">
            <label for="type_name" class="form-label">Type Name</label>
            <input type="text" class="form-control" id="type_name" name="type_name" value="<?= htmlspecialchars($attendee_type['type_name']) ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="price" class="form-label">Registration Price</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" id="price" name="price" value="<?= htmlspecialchars($attendee_type['price']) ?>" step="0.01" min="0" required>
            </div>
        </div>
        
        <hr>

        <div class="mb-3">
            <label class="form-label">Required Details</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="requires_pilot_details" name="requires_pilot_details" <?= !empty($attendee_type['requires_pilot_details']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="requires_pilot_details">
                    Requires Pilot Details (AUS Number, Planes, etc.)
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="requires_junior_details" name="requires_junior_details" <?= !empty($attendee_type['requires_junior_details']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="requires_junior_details">
                    Requires Junior Details (Date of Birth)
                </label>
            </div>
        </div>

        <hr>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" <?= !empty($attendee_type['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">
                Active
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Update Attendee Type</button>
        <a href="manage_attendee_types.php" class="btn btn-secondary">Cancel</a>
    </form>
    <?php elseif (!$error_message): ?>
        <div class="alert alert-info">Loading data...</div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
