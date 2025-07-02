<?php
// admin/add_attendee_type.php

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

// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type_name = trim($_POST['type_name']);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    // Get the values from the new checkboxes
    $requires_pilot_details = isset($_POST['requires_pilot_details']) ? 1 : 0;
    $requires_junior_details = isset($_POST['requires_junior_details']) ? 1 : 0;
    
    $errors = [];

    // Validation
    if (empty($type_name)) {
        $errors[] = "Type Name is required.";
    }
    if ($price === false || $price < 0) {
        $errors[] = "Price must be a valid, non-negative number.";
    }

    // Check for duplicate type name before inserting
    if (empty($errors)) {
        try {
            $sql_check = "SELECT COUNT(*) FROM attendee_types WHERE type_name = :type_name";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':type_name' => $type_name]);
            if ($stmt_check->fetchColumn() > 0) {
                $errors[] = "An attendee type with this name already exists.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error during duplicate check: " . $e->getMessage();
        }
    }

    // If validation passes, insert into the database.
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO attendee_types (type_name, price, is_active, requires_pilot_details, requires_junior_details) 
                    VALUES (:type_name, :price, :is_active, :requires_pilot, :requires_junior)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':type_name' => $type_name,
                ':price' => $price,
                ':is_active' => $is_active,
                ':requires_pilot' => $requires_pilot_details,
                ':requires_junior' => $requires_junior_details
            ]);

            $_SESSION['success_message'] = "Attendee Type '".htmlspecialchars($type_name)."' was created successfully!";
            header("Location: manage_attendee_types.php");
            exit();

        } catch (PDOException $e) {
            $error_message = "Database Error: Could not create the attendee type. " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// --- HEADER ---
$page_title = 'Add Attendee Type';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Add New Attendee Type</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <form action="add_attendee_type.php" method="POST">
        <div class="mb-3">
            <label for="type_name" class="form-label">Type Name</label>
            <input type="text" class="form-control" id="type_name" name="type_name" placeholder="e.g., Pilot, Guest, Junior" required>
        </div>
        
        <div class="mb-3">
            <label for="price" class="form-label">Registration Price</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
            </div>
        </div>
        
        <hr>

        <div class="mb-3">
            <label class="form-label">Required Details</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="requires_pilot_details" name="requires_pilot_details">
                <label class="form-check-label" for="requires_pilot_details">
                    Requires Pilot Details (AUS Number, Planes, etc.)
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="requires_junior_details" name="requires_junior_details">
                <label class="form-check-label" for="requires_junior_details">
                    Requires Junior Details (Date of Birth)
                </label>
            </div>
        </div>

        <hr>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" checked>
            <label class="form-check-label" for="is_active">
                Active
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Save Attendee Type</button>
        <a href="manage_attendee_types.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
