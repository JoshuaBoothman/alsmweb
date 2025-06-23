<?php
// --- SECURITY AND INITIALIZATION ---
session_start();
// This entire block is for security and setup. It ensures only logged-in admins can access the page.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // If the user is not an admin or not logged in, redirect them to the login page with an error.
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- CONFIGURATION AND DATABASE CONNECTION ---
// We need to connect to the database to insert the new event data.
require_once '../config/db_config.php';

// --- FORM PROCESSING LOGIC ---
// These variables will hold any success or error messages to display to the admin.
$error_message = '';
$success_message = '';

// Check if the request method is POST, which means the form has been submitted.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Retrieve and trim the form data
    $event_name = trim($_POST['event_name']);
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $location = trim($_POST['location']);
    // Description is not trimmed to allow for intentional whitespace.
    $description = $_POST['description'];

    // 2. Server-Side Validation
    if (empty($event_name) || empty($start_date) || empty($end_date) || empty($location)) {
        $error_message = "All fields except description are required.";
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $error_message = "The end date cannot be before the start date.";
    } else {
        // 3. If validation passes, proceed with database insertion.
        try {
            $sql = "INSERT INTO Events (event_name, description, start_date, end_date, location, created_by_user_id) VALUES (:event_name, :description, :start_date, :end_date, :location, :admin_id)";
            $stmt = $pdo->prepare($sql);

            // Bind the values to the placeholders in the SQL query for security.
            $stmt->execute([
                ':event_name' => $event_name,
                ':description' => $description,
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':location' => $location,
                ':admin_id' => $_SESSION['user_id'] // Log which admin created the event
            ]);

            // Set a success message and redirect back to the main management page.
            $_SESSION['success_message'] = "Event '".htmlspecialchars($event_name)."' was created successfully!";
            header("Location: manage_events.php");
            exit();

        } catch (PDOException $e) {
            // If the database query fails, show an error.
            $error_message = "Database Error: Could not create the event. " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Event - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Add New Event</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <form action="add_event.php" method="POST">
            <div class="mb-3">
                <label for="event_name" class="form-label">Event Name</label>
                <input type="text" class="form-control" id="event_name" name="event_name" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                </div>
            </div>
            <div class="mb-3">
                <label for="location" class="form-label">Location</label>
                <input type="text" class="form-control" id="location" name="location" required>
            </div>

            <button type="submit" class="btn btn-primary">Save Event</button>
            <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>