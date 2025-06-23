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
$error_message = '';
$event_name = '';
$event_id = null;

// --- FORM PROCESSING LOGIC (HANDLE POST REQUEST FOR DELETION) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

    if ($event_id) {
        try {
            // NOTE: For a more advanced system, you would first check if this event has
            // associated sub-events or registrations and prevent deletion if it does.
            // For now, we will proceed with a direct delete.
            $sql = "DELETE FROM Events WHERE event_id = :event_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':event_id' => $event_id]);

            // Set a success message and redirect back to the management page.
            $_SESSION['success_message'] = "The event was successfully deleted.";
            header("Location: manage_events.php");
            exit();

        } catch (PDOException $e) {
            // Handle potential foreign key constraint errors if sub-events exist
            if ($e->getCode() == '23000') {
                 $error_message = "Cannot delete this event because it has associated data (like sub-events or registrations). Please remove the associated data first.";
            } else {
                $error_message = "Database Error: Could not delete the event. " . $e->getMessage();
            }
        }
    } else {
        $error_message = "Invalid Event ID. Deletion failed.";
    }
} else {
    // --- DATA FETCHING FOR CONFIRMATION (HANDLE GET REQUEST) ---
    $event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($event_id) {
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
    } else {
        $error_message = "No Event ID provided.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Event - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
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
                <p class="mb-0">This action cannot be undone.</p>
            </div>

            <form action="delete_event.php" method="POST">
                <input type="hidden" name="event_id" value="<?= htmlspecialchars($event_id) ?>">
                
                <button type="submit" class="btn btn-danger">Confirm Delete</button>
                <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>