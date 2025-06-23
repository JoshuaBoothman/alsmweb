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
$event = null; // This will hold the event data we fetch.
$event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); // Get event ID from URL

// --- FORM PROCESSING LOGIC (HANDLE POST REQUEST) ---
// This block runs ONLY when the admin submits the form.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // We get the event_id from a hidden field in the form
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

    // 1. Retrieve and trim the submitted form data
    $event_name = trim($_POST['event_name']);
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $location = trim($_POST['location']);
    $description = $_POST['description'];

    // 2. Server-Side Validation
    if (empty($event_name) || empty($start_date) || empty($end_date) || empty($location)) {
        $error_message = "All fields except description are required.";
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $error_message = "The end date cannot be before the start date.";
    } elseif (!$event_id) {
        $error_message = "Invalid Event ID. Cannot update.";
    } else {
        // 3. If validation passes, proceed with the database UPDATE.
        try {
            $sql = "UPDATE Events SET 
                event_name = :event_name, 
                description = :description, 
                start_date = :start_date, 
                end_date = :end_date, 
                location = :location, 
                event_UpdatedByUser_Id = :admin_id 
            WHERE event_id = :event_id";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':event_name' => $event_name,
                ':description' => $description,
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':location' => $location,
                ':admin_id' => $_SESSION['user_id'],
                ':event_id' => $event_id
            ]);

            // Set a success message and redirect back to the management page.
            $_SESSION['success_message'] = "Event '".htmlspecialchars($event_name)."' was updated successfully!";
            header("Location: manage_events.php");
            exit();

        } catch (PDOException $e) {
            $error_message = "Database Error: Could not update the event. " . $e->getMessage();
        }
    }
    // If there was a validation error, we need to repopulate the $event variable to refill the form
    $event = $_POST;

} elseif ($event_id) {
    // --- DATA FETCHING LOGIC (HANDLE GET REQUEST) ---
    // This block runs when the page is first loaded, to get the data from the DB.
    try {
        $sql = "SELECT * FROM Events WHERE event_id = :event_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':event_id' => $event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            $error_message = "No event found with this ID.";
            $event = null; // Ensure the form doesn't try to render
        }
    } catch (PDOException $e) {
        $error_message = "Database Error: Could not fetch event details. " . $e->getMessage();
    }
} else {
    // This runs if no ID was provided in the URL at all.
    $error_message = "No Event ID provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Edit Event</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <?php if ($event): // Only show the form if we successfully found an event to edit ?>
        <form action="edit_event.php?id=<?= htmlspecialchars($event_id) ?>" method="POST">
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

            <button type="submit" class="btn btn-primary">Update Event</button>
            <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
        </form>
        <?php else: ?>
            <a href="manage_events.php" class="btn btn-primary">Back to Event Management</a>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>