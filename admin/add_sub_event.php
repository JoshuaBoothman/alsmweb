<?php
// admin/add_sub_event.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$event_name = 'Unknown Event';
// This page requires a parent event_id from the URL to know where to add the new sub-event.
$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

if (!$event_id) {
    header("Location: manage_events.php?error=noeventid");
    exit();
}

// --- DATA FETCHING for context ---
try {
    // Fetch the parent event's name to display in the title.
    $stmt = $pdo->prepare("SELECT event_name FROM events WHERE event_id = :id");
    $stmt->execute([':id' => $event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($event) {
        $event_name = $event['event_name'];
    } else {
        throw new Exception("The specified parent event could not be found.");
    }
} catch (Exception $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

// --- FORM PROCESSING LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate the CSRF token to prevent cross-site request forgery attacks.
    validate_csrf_token();

    $event_id_post = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $sub_event_name = trim($_POST['sub_event_name']);
    $description = trim($_POST['description']);
    $date_time = trim($_POST['date_time']);
    $cost = filter_input(INPUT_POST, 'cost', FILTER_VALIDATE_FLOAT);
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
    $errors = [];

    // Validation
    if ($event_id_post !== $event_id) $errors[] = "Event ID mismatch.";
    if (empty($sub_event_name)) $errors[] = "Sub-Event Name is required.";
    if (empty($date_time)) $errors[] = "Date & Time is required.";
    if ($cost === false || $cost < 0) $errors[] = "Cost must be a valid, non-negative number.";
    if ($capacity === false || $capacity < 0) $errors[] = "Capacity must be a valid, non-negative integer.";
    
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO subevents (main_event_id, sub_event_name, description, date_time, cost, capacity) 
                    VALUES (:main_event_id, :sub_event_name, :description, :date_time, :cost, :capacity)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':main_event_id' => $event_id,
                ':sub_event_name' => $sub_event_name,
                ':description' => $description,
                ':date_time' => $date_time,
                ':cost' => $cost,
                ':capacity' => $capacity
            ]);

            $_SESSION['success_message'] = "Sub-Event '".htmlspecialchars($sub_event_name)."' was created successfully!";
            header("Location: manage_sub_events.php?event_id=" . $event_id);
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not create the sub-event. " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Generate a CSRF token to be included in the form.
generate_csrf_token();

// --- HEADER ---
$page_title = 'Add New Sub-Event';
require_once __DIR__ . '/../templates/header.php';
?>
<div class="container mt-5">
    <h1 class="mb-4">Add New Sub-Event to: <strong><?= htmlspecialchars($event_name) ?></strong></h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <form action="add_sub_event.php?event_id=<?= $event_id ?>" method="POST">
        <!-- CSRF Token for security -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="event_id" value="<?= $event_id ?>">

        <div class="mb-3">
            <label for="sub_event_name" class="form-label">Sub-Event Name</label>
            <input type="text" class="form-control" id="sub_event_name" name="sub_event_name" required>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="date_time" class="form-label">Date & Time</label>
                <input type="datetime-local" class="form-control" id="date_time" name="date_time" required>
            </div>
            <div class="col-md-4 mb-3">
                <label for="cost" class="form-label">Cost</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="cost" name="cost" step="0.01" min="0" value="0.00" required>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <label for="capacity" class="form-label">Capacity</label>
                <input type="number" class="form-control" id="capacity" name="capacity" min="0" value="0" required>
                <div class="form-text">Enter 0 for unlimited capacity.</div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save Sub-Event</button>
        <a href="manage_sub_events.php?event_id=<?= $event_id ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
