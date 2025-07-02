<?php
// admin/delete_sub_event.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$sub_event_name = '';
$sub_event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT); // For redirection

if (!$sub_event_id || !$event_id) {
    header("Location: manage_events.php?error=invalidids");
    exit();
}

// --- DEPENDENCY CHECK FUNCTION ---
// Checks if the sub-event has any registrations.
function hasRegistrations($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendee_subevent_registrations WHERE sub_event_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() > 0;
}

// --- FORM PROCESSING (DELETION) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sub_event_id_post = filter_input(INPUT_POST, 'sub_event_id', FILTER_VALIDATE_INT);

    if ($sub_event_id_post === $sub_event_id) {
        if (hasRegistrations($pdo, $sub_event_id)) {
            $_SESSION['error_message'] = "Cannot delete: This sub-event has active registrations.";
            header("Location: manage_sub_events.php?event_id=" . $event_id);
            exit();
        }
        try {
            $sql = "DELETE FROM subevents WHERE sub_event_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $sub_event_id]);
            $_SESSION['success_message'] = "The sub-event was successfully deleted.";
            header("Location: manage_sub_events.php?event_id=" . $event_id);
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not delete the sub-event. " . $e->getMessage();
        }
    }
}

// --- DATA FETCHING for confirmation page ---
if (empty($error_message)) {
    try {
        if (hasRegistrations($pdo, $sub_event_id)) {
            $error_message = "This sub-event cannot be deleted because one or more attendees are registered for it.";
        } else {
            $stmt = $pdo->prepare("SELECT sub_event_name FROM subevents WHERE sub_event_id = :id");
            $stmt->execute([':id' => $sub_event_id]);
            $sub_event = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($sub_event) {
                $sub_event_name = $sub_event['sub_event_name'];
            } else {
                $error_message = "Sub-Event not found.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}

// --- HEADER ---
$page_title = 'Delete Sub-Event';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Delete Sub-Event</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <a href="manage_sub_events.php?event_id=<?= htmlspecialchars($event_id) ?>" class="btn btn-secondary">&laquo; Back to Sub-Events</a>
    <?php else: ?>
        <div class="alert alert-warning">
            <h4 class="alert-heading">Are you sure?</h4>
            <p>You are about to permanently delete the sub-event: <strong><?= htmlspecialchars($sub_event_name) ?></strong>.</p>
            <hr>
            <p class="mb-0">This action cannot be undone.</p>
        </div>

        <form action="delete_sub_event.php?id=<?= $sub_event_id ?>&event_id=<?= $event_id ?>" method="POST">
            <input type="hidden" name="sub_event_id" value="<?= htmlspecialchars($sub_event_id) ?>">
            <button type="submit" class="btn btn-danger">Confirm Delete</button>
            <a href="manage_sub_events.php?event_id=<?= $event_id ?>" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
