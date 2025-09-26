<?php
// admin/edit_event.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$event = null;
$event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$event_id) {
    header("Location: manage_events.php");
    exit();
}

// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $event_name = trim($_POST['event_name']);
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $location = trim($_POST['location']);
    $description = $_POST['description'];
    $is_archived = isset($_POST['is_archived']) ? 1 : 0;

    if (empty($event_name) || empty($start_date) || empty($end_date) || empty($location)) {
        $error_message = "All fields except description are required.";
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $error_message = "The end date cannot be before the start date.";
    } else {
        try {
            $sql = "UPDATE Events SET 
                event_name = :event_name, 
                description = :description, 
                start_date = :start_date, 
                end_date = :end_date, 
                location = :location, 
                event_UpdatedByUser_Id = :admin_id,
                is_archived = :is_archived 
            WHERE event_id = :event_id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':event_name' => $event_name,
                ':description' => $description,
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':location' => $location,
                ':admin_id' => $_SESSION['user_id'],
                ':is_archived' => $is_archived,
                ':event_id' => $event_id
            ]);

            $_SESSION['success_message'] = "Event '".htmlspecialchars($event_name)."' was updated successfully!";
            header("Location: manage_events.php");
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not update the event. " . $e->getMessage();
        }
    }
    $event = $_POST;
    // Ensure is_archived is also retained on error
    $event['is_archived'] = $is_archived;
} else {
    // --- DATA FETCHING for page load ---
    try {
        $sql = "SELECT * FROM Events WHERE event_id = :event_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':event_id' => $event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            $error_message = "No event found with this ID.";
            $event = null;
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: Could not fetch event details. " . $e->getMessage();
    }
}

// Generate a CSRF token for the form to be displayed.
generate_csrf_token();

// --- HEADER ---
$page_title = 'Edit Event';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">Edit Event</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <?php if ($event): ?>
    <form action="edit_event.php?id=<?= htmlspecialchars($event_id) ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="event_id" value="<?= htmlspecialchars($event['event_id']) ?>">
        
        <div class="mb-3">
            <label for="event_name" class="form-label">Event Name</label>
            <input type="text" class="form-control" id="event_name" name="event_name" value="<?= htmlspecialchars($event['event_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($event['description']) ?></textarea>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($event['start_date']) ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($event['end_date']) ?>" required>
            </div>
        </div>
        <div class="mb-3">
            <label for="location" class="form-label">Location</label>
            <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($event['location']) ?>" required>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_archived" name="is_archived" value="1" <?= !empty($event['is_archived']) && $event['is_archived'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_archived">Archive this event</label>
            <small class="form-text text-muted d-block">Archived events will be hidden from public view but retained in the system.</small>
        </div>
        
        <button type="submit" class="btn btn-primary">Update Event</button>
        <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
    </form>
    <?php else: ?>
        <a href="manage_events.php" class="btn btn-primary">Back to Event Management</a>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
