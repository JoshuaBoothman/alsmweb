<?php
// admin/delete_campsite.php

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
$campsite_name = '';
$campsite_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$campground_id = filter_input(INPUT_GET, 'campground_id', FILTER_VALIDATE_INT); // For redirection

// --- VALIDATE IDs ---
if (!$campsite_id || !$campground_id) {
    header("Location: manage_campgrounds.php?error=invalidids");
    exit();
}

// --- DEPENDENCY CHECK FUNCTION ---
// Checks if a campsite has ever been booked. If so, it shouldn't be deleted.
function hasBookings($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE campsite_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() > 0;
}

// --- FORM PROCESSING (DELETION on POST request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    $campsite_id_post = filter_input(INPUT_POST, 'campsite_id', FILTER_VALIDATE_INT);
    $campground_id_post = filter_input(INPUT_POST, 'campground_id', FILTER_VALIDATE_INT);

    if ($campsite_id_post === $campsite_id) {
        // Final safety check before deleting.
        if (hasBookings($pdo, $campsite_id)) {
            $_SESSION['error_message'] = "Cannot delete this campsite because it has past or current bookings. You can make it unavailable by editing it and setting its status to 'Inactive'.";
            header("Location: manage_campsites.php?campground_id=" . $campground_id_post);
            exit();
        }
        try {
            $sql = "DELETE FROM campsites WHERE campsite_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $campsite_id]);

            $_SESSION['success_message'] = "The campsite was successfully deleted.";
            header("Location: manage_campsites.php?campground_id=" . $campground_id_post);
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not delete the campsite. " . $e->getMessage();
        }
    } else {
        $error_message = "ID mismatch. Deletion failed.";
    }

// --- INITIAL PAGE LOAD (Display Confirmation on GET request) ---
} else {
    try {
        // Check for dependencies before showing the confirmation form.
        if (hasBookings($pdo, $campsite_id)) {
            $error_message = "This campsite cannot be deleted because it is associated with one or more bookings. Deleting it would corrupt historical booking records. <br><br>If you want to make it unavailable for future bookings, please go back and edit the campsite to set its status to 'Inactive'.";
        } else {
            // If no dependencies, get the name to show in the confirmation message.
            $stmt = $pdo->prepare("SELECT name FROM campsites WHERE campsite_id = :id");
            $stmt->execute([':id' => $campsite_id]);
            $campsite = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($campsite) {
                $campsite_name = $campsite['name'];
            } else {
                $error_message = "Campsite not found.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}

// Generate a CSRF token for the confirmation form.
generate_csrf_token();

// --- HEADER ---
$page_title = 'Delete Campsite';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Delete Campsite</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
        <a href="manage_campsites.php?campground_id=<?= htmlspecialchars($campground_id) ?>" class="btn btn-secondary">&laquo; Back to Campsites</a>
    <?php else: ?>
        <div class="alert alert-warning">
            <h4 class="alert-heading">Are you sure?</h4>
            <p>You are about to permanently delete the campsite: <strong><?= htmlspecialchars($campsite_name) ?></strong>.</p>
            <hr>
            <p class="mb-0">This action cannot be undone. If this site has been booked in the past, consider setting it to 'Inactive' instead to preserve records.</p>
        </div>

        <form action="delete_campsite.php?id=<?= $campsite_id ?>&campground_id=<?= $campground_id ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="campsite_id" value="<?= htmlspecialchars($campsite_id) ?>">
            <input type="hidden" name="campground_id" value="<?= htmlspecialchars($campground_id) ?>">
            <button type="submit" class="btn btn-danger">Confirm Delete</button>
            <a href="manage_campsites.php?campground_id=<?= $campground_id ?>" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
