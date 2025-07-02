<?php
// admin/manage_events.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';

// --- DATA FETCHING ---
$events = [];
$error_message = '';
try {
    $sql = "SELECT event_id, event_name, start_date, end_date, location FROM events WHERE event_IsDeleted = 0 ORDER BY start_date DESC";
    $stmt = $pdo->query($sql);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch events. " . $e->getMessage();
}

// --- HEADER ---
$page_title = 'Manage Events';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">Manage Events</h1>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if ($error_message) {
        echo '<div class="alert alert-danger">' . $error_message . '</div>';
    }
    ?>

    <div class="d-flex justify-content-end mb-3">
        <a href="add_event.php" class="btn btn-success">Add New Event</a>
    </div>

    <table class="table table-striped">
        <thead class="table-dark">
            <tr>
                <th>Event Name</th>
                <th>Dates</th>
                <th>Location</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($events)): ?>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= htmlspecialchars($event['event_name']) ?></td>
                        <td><?= date('d M Y', strtotime($event['start_date'])) ?> - <?= date('d M Y', strtotime($event['end_date'])) ?></td>
                        <td><?= htmlspecialchars($event['location']) ?></td>
                        <td>
                            <a href="manage_sub_events.php?event_id=<?= $event['event_id'] ?>" class="btn btn-info btn-sm">Manage Sub-Events</a>
                            <a href="edit_event.php?id=<?= $event['event_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                            <a href="delete_event.php?id=<?= $event['event_id'] ?>" class="btn btn-danger btn-sm">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center">No events found. <a href="add_event.php">Add one now</a>.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
