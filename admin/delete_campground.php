<?php
// admin/delete_campground.php (Final Version)

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
$campground_name = '';
$campground_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// --- DEPENDENCY CHECK FUNCTION ---
// Before we can delete a campground, we must check if it has any campsites linked to it.
function hasDependencies($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM campsites WHERE campground_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() > 0;
}

// --- FORM PROCESSING (DELETION on POST request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $campground_id_post = filter_input(INPUT_POST, 'campground_id', FILTER_VALIDATE_INT);

    if ($campground_id_post) {
        // Final safety check before deleting
        if (hasDependencies($pdo, $campground_id_post)) {
            $_SESSION['error_message'] = "Cannot delete: This campground now has campsites. Please delete them first.";
            header("Location: manage_campgrounds.php");
            exit();
        }
        try {
            $sql = "DELETE FROM campgrounds WHERE campground_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $campground_id_post]);

            $_SESSION['success_message'] = "The campground was successfully deleted.";
            header("Location: manage_campgrounds.php");
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not delete the campground. " . $e->getMessage();
        }
    }

// --- INITIAL PAGE LOAD (Display Confirmation on GET request) ---
} elseif ($campground_id) {
    try {
        // Check for dependencies before showing the confirmation form.
        if (hasDependencies($pdo, $campground_id)) {
            $error_message = "Cannot delete this campground because it has campsites linked to it. You must delete the individual campsites from within this campground first.";
        } else {
            // If no dependencies, get the name to show in the confirmation message.
            $stmt = $pdo->prepare("SELECT name FROM campgrounds WHERE campground_id = :id");
            $stmt->execute([':id' => $campground_id]);
            $campground = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($campground) {
                $campground_name = $campground['name'];
            } else {
                $error_message = "Campground not found.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
} else {
    // This runs if no ID was passed in the URL at all.
    $error_message = "No Campground ID specified.";
}

// --- HEADER ---
$page_title = 'Delete Campground';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">Delete Campground</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <a href="manage_campgrounds.php" class="btn btn-secondary">&laquo; Back to Campgrounds</a>
    <?php else: ?>
        <div class="alert alert-warning">
            <h4 class="alert-heading">Are you sure?</h4>
            <p>You are about to permanently delete the campground: <strong><?= htmlspecialchars($campground_name) ?></strong>.</p>
            <hr>
            <p class="mb-0">This action cannot be undone.</p>
        </div>

        <form action="delete_campground.php?id=<?= htmlspecialchars($campground_id) ?>" method="POST">
            <input type="hidden" name="campground_id" value="<?= htmlspecialchars($campground_id) ?>">
            <button type="submit" class="btn btn-danger">Confirm Delete</button>
            <a href="manage_campgrounds.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
