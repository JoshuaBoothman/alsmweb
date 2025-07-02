<?php 
require_once '../config/db_config.php';

$event = null;
$sub_events = [];
$error_message = '';
$event_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$event_id) {
    $error_message = "No event ID provided.";
} else {
    try {
        // 1. Fetch the main event details
        $sql_event = "SELECT * FROM events WHERE event_id = :event_id AND event_IsDeleted = 0";
        $stmt_event = $pdo->prepare($sql_event);
        $stmt_event->execute([':event_id' => $event_id]);
        $event = $stmt_event->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            throw new Exception("The requested event could not be found or is no longer available.");
        }

        // 2. Fetch associated sub-events
        $sql_sub_events = "SELECT * FROM subevents WHERE main_event_id = :event_id ORDER BY date_time ASC";
        $stmt_sub_events = $pdo->prepare($sql_sub_events);
        $stmt_sub_events->execute([':event_id' => $event_id]);
        $sub_events = $stmt_sub_events->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Define the page title
$page_title = $event ? htmlspecialchars($event['event_name']) : 'Event Not Found';

// Include the header template
require_once __DIR__ . '/../templates/header.php'; 
?>

<main class="container mt-4">
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <a href="events.php" class="btn btn-primary">Back to Events List</a>
    <?php elseif ($event): ?>
        <div class="p-5 mb-4 bg-light rounded-3">
            <div class="container-fluid py-5">
                <h1 class="display-5 fw-bold"><?= htmlspecialchars($event['event_name']) ?></h1>
                <p class="fs-5">
                    <strong class="text-muted">When:</strong> <?= date('l, F j, Y', strtotime($event['start_date'])) ?> to <?= date('l, F j, Y', strtotime($event['end_date'])) ?>
                    <br>
                    <strong class="text-muted">Where:</strong> <?= htmlspecialchars($event['location']) ?>
                </p>
                <hr>
                <p class="col-md-12 fs-4"><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                
                <!-- New Registration Button -->
                <a href="register_for_event.php?event_id=<?= $event['event_id'] ?>" class="btn btn-success btn-lg mt-3">
                    Register for this Event
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <h3>Sub-Events & Competitions</h3>
                <?php if (empty($sub_events)): ?>
                    <p>There are no specific sub-events scheduled for this event yet.</p>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sub-Event</th>
                                <th>Date & Time</th>
                                <th>Cost</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sub_events as $sub): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($sub['sub_event_name']) ?></strong>
                                        <p><small><?= htmlspecialchars($sub['description']) ?></small></p>
                                    </td>
                                    <td><?= date('g:i A, l, F j', strtotime($sub['date_time'])) ?></td>
                                    <td>$<?= htmlspecialchars(number_format($sub['cost'], 2)) ?></td>
                                    <td>
                                        <button class="btn btn-outline-secondary disabled">Register (During Main Registration)</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php 
// Include the footer template
require_once __DIR__ . '/../templates/footer.php'; 
?>
