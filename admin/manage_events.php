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
    // This advanced query joins events with a sub-query that counts and groups attendees.
    $sql = "
        SELECT
            e.event_id,
            e.event_name,
            e.start_date,
            e.end_date,
            e.location,
            e.is_archived,
            -- THE FIX IS HERE: Changed 'ac.type_count' to 'attendee_counts.type_count'
            GROUP_CONCAT(DISTINCT CONCAT(at.type_name, ': ', attendee_counts.type_count) ORDER BY at.type_name SEPARATOR '<br>') AS attendee_breakdown
        FROM
            events e
        LEFT JOIN (
            -- This sub-query pre-calculates the count of each attendee type for each event
            SELECT
                er.event_id,
                a.type_id,
                COUNT(a.attendee_id) AS type_count
            FROM
                eventregistrations er
            JOIN
                attendees a ON er.registration_id = a.eventreg_id
            GROUP BY
                er.event_id, a.type_id
        ) AS attendee_counts ON e.event_id = attendee_counts.event_id
        LEFT JOIN
            attendee_types at ON attendee_counts.type_id = at.type_id
        WHERE
            e.event_IsDeleted = 0
        GROUP BY
            e.event_id
        ORDER BY
            e.start_date DESC
    ";
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
                <th class="no-wrap">Dates</th>
                <th class="no-wrap">Attendees</th>
                <th>Location</th>
                <th>Status</th>
                <th class="no-wrap">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($events)): ?>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= htmlspecialchars($event['event_name']) ?></td>
                        <td class="no-wrap"><?= date('d M Y', strtotime($event['start_date'])) ?> - <?= date('d M Y', strtotime($event['end_date'])) ?></td>
                        <td class="no-wrap">
                            <?= $event['attendee_breakdown'] ?? '0' ?>
                        </td>
                        <td><?= htmlspecialchars($event['location']) ?></td>
                        <td>
                            <span class="badge <?= $event['is_archived'] ? 'bg-secondary' : 'bg-success' ?>">
                                <?= $event['is_archived'] ? 'Archived' : 'Live' ?>
                            </span>
                        </td>
                        <td class="no-wrap">
                            <a href="view_event_attendees.php?event_id=<?= $event['event_id'] ?>" class="btn btn-success btn-sm">Attendees</a>
                            <a href="manage_sub_events.php?event_id=<?= $event['event_id'] ?>" class="btn btn-info btn-sm">Sub-Events</a>
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
