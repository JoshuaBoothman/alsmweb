<?php

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

// --- DATABASE LOGIC & PAGE SETUP BLOCK ---
// This part connects to the DB and gets the events.
require_once '../config/db_config.php';

try {
    $sql = "SELECT event_id, event_name, start_date, end_date, location FROM Events ORDER BY start_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If the database connection or query fails, we'll see this error.
    die("Database Error: Could not fetch events. " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php
    // Check if a success message is set in the session, display it, then unset it.
    if (isset($_SESSION['success_message'])) {
        echo '<div class="container mt-3"><div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div></div>';
        unset($_SESSION['success_message']); // Clear the message so it doesn't show again on refresh
    }
    ?>
    <div class="container mt-5">
        <h1 class="mb-4">Event Management</h1>

        <a href="add_event.php" class="btn btn-success mb-3">Add New Event</a>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Event Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Location</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($events): ?>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= htmlspecialchars($event['event_name']) ?></td>
                            <td><?= htmlspecialchars($event['start_date']) ?></td>
                            <td><?= htmlspecialchars($event['end_date']) ?></td>
                            <td><?= htmlspecialchars($event['location']) ?></td>
                            <td>
                                <a href="edit_event.php?id=<?= $event['event_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="delete_event.php?id=<?= $event['event_id'] ?>" class="btn btn-danger btn-sm">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No events found. Click "Add New Event" to create one.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>