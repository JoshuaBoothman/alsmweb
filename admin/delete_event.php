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
$event_name = '';
$event_id = null;

// --- DEPENDENCY CHECK FUNCTION ---
// Checks if an event has sub-events or registrations linked to it.
function hasDependencies($pdo, $id) {
    $stmt_sub = $pdo->prepare("SELECT COUNT(*) FROM subevents WHERE main_event_id = :id");
    $stmt_sub->execute([':id' => $id]);
    if ($stmt_sub->fetchColumn() > 0) return true;

    $stmt_reg = $pdo->prepare("SELECT COUNT(*) FROM eventregistrations WHERE event_id = :id");
    $stmt_reg->execute([':id' => $id]);
    if ($stmt_reg->fetchColumn() > 0) return true;

    return false;
}

// --- FORM PROCESSING LOGIC (HANDLE POST REQUEST FOR DELETION) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

    if ($event_id) {
        if (hasDependencies($pdo, $event_id)) {
            $_SESSION['error_message'] = "Cannot delete this event because it has associated sub-events or registrations. Please remove them first.";
            header("Location: manage_events.php");
            exit();
        }
        try {
            $sql = "DELETE FROM Events WHERE event_id = :event_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':event_id' => $event_id]);

            // Set a success message and redirect back to the management page.
            $_SESSION['success_message'] = "The event was successfully deleted.";
            header("Location: manage_events.php");
            exit();

        } catch (PDOException $e) {
            $error_message = "Database Error: Could not delete the event. " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid Event ID. Deletion failed.";
    }
} else {
    // --- DATA FETCHING FOR CONFIRMATION (HANDLE GET REQUEST) ---
    $event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($event_id) {
        if (hasDependencies($pdo, $event_id)) {
            $error_message = "Cannot delete this event because it has associated sub-events or registrations. Please remove them first.";
        } else {
            try {
                // We only need the name for the confirmation message.
                $sql = "SELECT event_name FROM Events WHERE event_id = :event_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':event_id' => $event_id]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($event) {
                    $event_name = $event['event_name'];
                } else {
                    $error_message = "No event found with this ID.";
                }
            } catch (PDOException $e) {
                $error_message = "Database Error: Could not fetch event details. " . $e->getMessage();
            }
        }
    } else {
        $error_message = "No Event ID provided.";
    }
}

generate_csrf_token();

// --- HEADER ---
$page_title = 'Delete Event';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Delete Event</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <a href="manage_events.php" class="btn btn-secondary">Back to Event Management</a>
    <?php else: ?>
        <div class="alert alert-warning">
            <h4 class="alert-heading">Are you sure?</h4>
            <p>You are about to permanently delete the event: <strong><?= htmlspecialchars($event_name) ?></strong>.</p>
            <hr>
            <p class="mb-0">This action cannot be undone. This will also delete associated campgrounds and campsites.</p>
        </div>

        <form action="delete_event.php?id=<?= htmlspecialchars($event_id) ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="event_id" value="<?= htmlspecialchars($event_id) ?>">
            <button type="submit" class="btn btn-danger">Confirm Delete</button>
            <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>