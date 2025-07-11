<?php
// public_html/select_sub_events.php

session_start();

// Security check: A registration must be in progress to access this page.
if (!isset($_SESSION['event_registration_in_progress'])) {
    header("Location: events.php?error=noreginprogress");
    exit();
}

require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php'; // 1. Include CSRF helpers

// --- INITIALIZE VARIABLES ---
$registration_data = $_SESSION['event_registration_in_progress'];
$event_id = $registration_data['event_id'];
$attendees = $registration_data['attendees'];

$event = null;
$sub_events = [];
$error_message = '';

// --- DATA FETCHING ---
try {
    // 1. Fetch the main event details for context
    $stmt_event = $pdo->prepare("SELECT event_name FROM events WHERE event_id = :id");
    $stmt_event->execute([':id' => $event_id]);
    $event = $stmt_event->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        throw new Exception("The event could not be found.");
    }

    // 2. Fetch all available sub-events for this main event
    $stmt_sub_events = $pdo->prepare("SELECT sub_event_id, sub_event_name, description, cost, capacity FROM subevents WHERE main_event_id = :id");
    $stmt_sub_events->execute([':id' => $event_id]);
    $sub_events = $stmt_sub_events->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}

// 2. Generate a token for the form
generate_csrf_token();

// --- HEADER ---
$page_title = 'Select Sub-Events';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-2">Register for: <strong><?= htmlspecialchars($event['event_name']) ?></strong></h1>
    <p class="lead">Step 2 of 2: Select Sub-Events for Your Attendees</p>
    <hr>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php else: ?>
        <form id="sub-event-form" action="cart_actions.php" method="POST">
            <input type="hidden" name="action" value="add_registration_to_cart">
            <!-- 3. Add the hidden CSRF token field -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <?php if (empty($sub_events)): ?>
                <div class="alert alert-info">There are no sub-events available for this event.</div>
            <?php else: ?>
                <?php foreach ($sub_events as $sub_event): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><?= htmlspecialchars($sub_event['sub_event_name']) ?></h5>
                            <small class="text-muted">Cost: $<?= number_format($sub_event['cost'], 2) ?></small>
                        </div>
                        <div class="card-body">
                            <p><?= htmlspecialchars($sub_event['description']) ?></p>
                            <h6>Select attendees for this sub-event:</h6>
                            <?php foreach ($attendees as $index => $attendee): ?>
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="sub_event_registrations[<?= $sub_event['sub_event_id'] ?>][]" 
                                           value="<?= $index ?>" 
                                           id="sub_event_<?= $sub_event['sub_event_id'] ?>_attendee_<?= $index ?>">
                                    <label class="form-check-label" for="sub_event_<?= $sub_event['sub_event_id'] ?>_attendee_<?= $index ?>">
                                        <?= htmlspecialchars($attendee['first_name'] . ' ' . $attendee['surname']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <a href="register_for_event.php?event_id=<?= $event_id ?>" class="btn btn-secondary">&laquo; Back to Edit Attendees</a>
                <button type="submit" class="btn btn-success btn-lg">Complete Registration & Add to Cart</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
