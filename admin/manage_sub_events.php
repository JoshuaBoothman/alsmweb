<?php
// admin/manage_sub_events.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';

// --- INITIALIZE VARIABLES ---
$sub_events = [];
$event_name = 'Unknown Event';
$error_message = '';
// This page depends on a parent event_id from the URL.
$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

if (!$event_id) {
    header("Location: manage_events.php?error=noeventid");
    exit();
}

// --- DATA FETCHING ---
try {
    // 1. Fetch the parent event's name for the page title.
    $stmt_event_name = $pdo->prepare("SELECT event_name FROM events WHERE event_id = :id");
    $stmt_event_name->execute([':id' => $event_id]);
    $event = $stmt_event_name->fetch(PDO::FETCH_ASSOC);

    if ($event) {
        $event_name = $event['event_name'];
    } else {
        throw new Exception("The specified event could not be found.");
    }

    // 2. Fetch all sub-events for this specific main event.
    $stmt_sub_events = $pdo->prepare("SELECT * FROM subevents WHERE main_event_id = :id ORDER BY date_time ASC");
    $stmt_sub_events->execute([':id' => $event_id]);
    $sub_events = $stmt_sub_events->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

// --- HEADER ---
$page_title = 'Manage Sub-Events';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">Manage Sub-Events for: <strong><?= htmlspecialchars($event_name) ?></strong></h1>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    }
    if ($error_message) {
        echo '<div class="alert alert-danger">' . $error_message . '</div>';
    }
    ?>
    
    <div class="d-flex justify-content-between mb-3">
        <a href="manage_events.php" class="btn btn-secondary">&laquo; Back to All Events</a>
        <a href="add_sub_event.php?event_id=<?= $event_id ?>" class="btn btn-success">Add New Sub-Event</a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Sub-Event Name</th>
                    <th>Date & Time</th>
                    <th>Cost</th>
                    <th>Capacity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sub_events)): ?>
                    <?php foreach ($sub_events as $sub_event): ?>
                        <tr>
                            <td><?= htmlspecialchars($sub_event['sub_event_name']) ?></td>
                            <td><?= date('d M Y, g:i A', strtotime($sub_event['date_time'])) ?></td>
                            <td>$<?= htmlspecialchars(number_format($sub_event['cost'], 2)) ?></td>
                            <td><?= htmlspecialchars($sub_event['capacity'] == 0 ? 'Unlimited' : $sub_event['capacity']) ?></td>
                            <td>
                                <a href="edit_sub_event.php?id=<?= $sub_event['sub_event_id'] ?>&event_id=<?= $event_id ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="delete_sub_event.php?id=<?= $sub_event['sub_event_id'] ?>&event_id=<?= $event_id ?>" class="btn btn-danger btn-sm">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No sub-events found for this event. <a href="add_sub_event.php?event_id=<?= $event_id ?>">Add one now</a>.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
