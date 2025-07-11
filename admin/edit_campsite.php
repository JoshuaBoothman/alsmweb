<?php
// admin/edit_campsite.php

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
$campsite = null;
$campground_name = 'Unknown Campground';
// This page requires both the campsite ID to edit and the parent campground ID for context and redirection.
$campsite_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$campground_id = filter_input(INPUT_GET, 'campground_id', FILTER_VALIDATE_INT);

// --- VALIDATE IDs ---
if (!$campsite_id || !$campground_id) {
    header("Location: manage_campgrounds.php?error=invalidids");
    exit();
}

// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    // Re-validate IDs from hidden form fields
    $campsite_id_post = filter_input(INPUT_POST, 'campsite_id', FILTER_VALIDATE_INT);
    $campground_id_post = filter_input(INPUT_POST, 'campground_id', FILTER_VALIDATE_INT);
    
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price_per_night = filter_input(INPUT_POST, 'price_per_night', FILTER_VALIDATE_FLOAT);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $errors = [];

    // Validation
    if ($campsite_id_post !== $campsite_id || $campground_id_post !== $campground_id) $errors[] = "ID mismatch.";
    if (empty($name)) $errors[] = "Campsite Name/Number is required.";
    if ($price_per_night === false || $price_per_night < 0) {
        $errors[] = "Price per night must be a valid, non-negative number.";
    }

    // If validation passes, update the record in the database.
    if (empty($errors)) {
        try {
            $sql = "UPDATE campsites 
                    SET name = :name, description = :description, price_per_night = :price_per_night, is_active = :is_active 
                    WHERE campsite_id = :campsite_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':price_per_night' => $price_per_night,
                ':is_active' => $is_active,
                ':campsite_id' => $campsite_id
            ]);

            $_SESSION['success_message'] = "Campsite '".htmlspecialchars($name)."' was updated successfully!";
            header("Location: manage_campsites.php?campground_id=" . $campground_id);
            exit();

        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                 $error_message = "Error: Another campsite with this name/number already exists in this campground.";
            } else {
                 $error_message = "Database Error: Could not update the campsite. " . $e->getMessage();
            }
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
    // If there's an error, repopulate the $campsite variable to refill the form with the user's attempted values.
    $campsite = $_POST;
    // We must manually add the ID back into the array for the form's hidden field.
    $campsite['campsite_id'] = $campsite_id;

}

// --- DATA FETCHING for page load ---
try {
    // Fetch parent campground name for context.
    $stmt_cg = $pdo->prepare("SELECT name FROM campgrounds WHERE campground_id = :id");
    $stmt_cg->execute([':id' => $campground_id]);
    $campground = $stmt_cg->fetch(PDO::FETCH_ASSOC);
    if ($campground) $campground_name = $campground['name'];

    // Only fetch from DB if the form hasn't been posted with an error.
    if (!$campsite) {
        $stmt_site = $pdo->prepare("SELECT * FROM campsites WHERE campsite_id = :id");
        $stmt_site->execute([':id' => $campsite_id]);
        $campsite = $stmt_site->fetch(PDO::FETCH_ASSOC);
        if (!$campsite) {
            $error_message = "Campsite not found.";
        }
    }
} catch (Exception $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

// Generate a CSRF token for the form to be displayed.
generate_csrf_token();

// --- HEADER ---
$page_title = 'Edit Campsite';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Edit Site in: <strong><?= htmlspecialchars($campground_name) ?></strong></h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <?php if ($campsite): ?>
    <form action="edit_campsite.php?id=<?= $campsite_id ?>&campground_id=<?= $campground_id ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="campsite_id" value="<?= htmlspecialchars($campsite['campsite_id']) ?>">
        <input type="hidden" name="campground_id" value="<?= htmlspecialchars($campground_id) ?>">

        <div class="mb-3">
            <label for="name" class="form-label">Campsite Name / Number</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($campsite['name']) ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="price_per_night" class="form-label">Price Per Night</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" id="price_per_night" name="price_per_night" value="<?= htmlspecialchars($campsite['price_per_night']) ?>" step="0.01" min="0" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description (Optional)</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($campsite['description']) ?></textarea>
        </div>
        
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" <?= !empty($campsite['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">
                Active (available for booking)
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Update Campsite</button>
        <a href="manage_campsites.php?campground_id=<?= $campground_id ?>" class="btn btn-secondary">Cancel</a>
    </form>
    <?php elseif(!$error_message): ?>
        <div class="alert alert-info">Loading campsite data...</div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
