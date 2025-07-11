<?php
// admin/edit_sub_event.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$sub_event = null;
$event_name = 'Unknown Event';
// This page requires both the sub_event ID to edit and the parent event ID for context.
$sub_event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

if (!$sub_event_id || !$event_id) {
    header("Location: manage_events.php?error=invalidids");
    exit();
}

// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    $sub_event_id_post = filter_input(INPUT_POST, 'sub_event_id', FILTER_VALIDATE_INT);
    $sub_event_name = trim($_POST['sub_event_name']);
    $description = trim($_POST['description']);
    $date_time = trim($_POST['date_time']);
    $cost = filter_input(INPUT_POST, 'cost', FILTER_VALIDATE_FLOAT);
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
    $errors = [];

    // Validation
    if ($sub_event_id_post !== $sub_event_id) $errors[] = "Sub-Event ID mismatch.";
    if (empty($sub_event_name)) $errors[] = "Sub-Event Name is required.";
    if (empty($date_time)) $errors[] = "Date & Time is required.";
    if ($cost === false || $cost < 0) $errors[] = "Cost must be a valid, non-negative number.";
    if ($capacity === false || $capacity < 0) $errors[] = "Capacity must be a valid, non-negative integer.";

    if (empty($errors)) {
        try {
            $sql = "UPDATE subevents SET 
                        sub_event_name = :sub_event_name, 
                        description = :description, 
                        date_time = :date_time, 
                        cost = :cost, 
                        capacity = :capacity 
                    WHERE sub_event_id = :sub_event_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':sub_event_name' => $sub_event_name,
                ':description' => $description,
                ':date_time' => $date_time,
                ':cost' => $cost,
                ':capacity' => $capacity,
                ':sub_event_id' => $sub_event_id
            ]);

            $_SESSION['success_message'] = "Sub-Event '".htmlspecialchars($sub_event_name)."' was updated successfully!";
            header("Location: manage_sub_events.php?event_id=" . $event_id);
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not update the sub-event. " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
    // Repopulate form with attempted values on error
    $sub_event = $_POST;
}

// --- DATA FETCHING for page load ---
if (!$sub_event) {
    try {
        // Fetch parent event name
        $stmt_event = $pdo->prepare("SELECT event_name FROM events WHERE event_id = :id");
        $stmt_event->execute([':id' => $event_id]);
        $event = $stmt_event->fetch(PDO::FETCH_ASSOC);
        if ($event) $event_name = $event['event_name'];

        // Fetch sub-event details
        $stmt_sub = $pdo->prepare("SELECT * FROM subevents WHERE sub_event_id = :id");
        $stmt_sub->execute([':id' => $sub_event_id]);
        $sub_event = $stmt_sub->fetch(PDO::FETCH_ASSOC);
        if (!$sub_event) {
            $error_message = "Sub-Event not found.";
        }
    } catch (Exception $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}

// Generate a CSRF token for the form to be displayed.
generate_csrf_token();

// --- HEADER ---
$page_title = 'Edit Sub-Event';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Edit Sub-Event in: <strong><?= htmlspecialchars($event_name) ?></strong></h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <?php if ($sub_event): ?>
    <form action="edit_sub_event.php?id=<?= $sub_event_id ?>&event_id=<?= $event_id ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="sub_event_id" value="<?= $sub_event_id ?>">
        
        <div class="mb-3">
            <label for="sub_event_name" class="form-label">Sub-Event Name</label>
            <input type="text" class="form-control" id="sub_event_name" name="sub_event_name" value="<?= htmlspecialchars($sub_event['sub_event_name']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($sub_event['description']) ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="date_time" class="form-label">Date & Time</label>
                <input type="datetime-local" class="form-control" id="date_time" name="date_time" value="<?= date('Y-m-d\TH:i', strtotime($sub_event['date_time'])) ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label for="cost" class="form-label">Cost</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="cost" name="cost" step="0.01" min="0" value="<?= htmlspecialchars($sub_event['cost']) ?>" required>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <label for="capacity" class="form-label">Capacity</label>
                <input type="number" class="form-control" id="capacity" name="capacity" min="0" value="<?= htmlspecialchars($sub_event['capacity']) ?>" required>
                <div class="form-text">Enter 0 for unlimited capacity.</div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Update Sub-Event</button>
        <a href="manage_sub_events.php?event_id=<?= $event_id ?>" class="btn btn-secondary">Cancel</a>
    </form>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../templates/header.php'; ?>
