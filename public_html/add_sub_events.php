<?php
// public_html/add_sub_events.php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=loginrequired");
    exit();
}

require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// --- INITIALIZE VARIABLES ---
$registration_id = filter_input(INPUT_GET, 'registration_id', FILTER_VALIDATE_INT);
if (!$registration_id) {
    header("Location: profile.php?error=invalidrego");
    exit();
}

$event = null;
$sub_events = [];
$attendees = [];
$already_registered = []; // To track who is already in which sub-event
$error_message = '';

// --- DATA FETCHING ---
try {
    // 1. Fetch event details via the registration ID
    $sql_event = "SELECT e.event_id, e.event_name FROM events e JOIN eventregistrations er ON e.event_id = er.event_id WHERE er.registration_id = :reg_id AND er.user_id = :user_id";
    $stmt_event = $pdo->prepare($sql_event);
    $stmt_event->execute([':reg_id' => $registration_id, ':user_id' => $_SESSION['user_id']]);
    $event = $stmt_event->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        throw new Exception("Registration not found or you do not have permission to view it.");
    }

    // 2. Fetch all attendees for this registration
    $sql_attendees = "SELECT attendee_id, first_name, surname FROM attendees WHERE eventreg_id = :reg_id ORDER BY surname, first_name";
    $stmt_attendees = $pdo->prepare($sql_attendees);
    $stmt_attendees->execute([':reg_id' => $registration_id]);
    $attendees = $stmt_attendees->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch all sub-events for this main event
    $sql_sub_events = "SELECT * FROM subevents WHERE main_event_id = :event_id ORDER BY date_time ASC";
    $stmt_sub_events = $pdo->prepare($sql_sub_events);
    $stmt_sub_events->execute([':event_id' => $event['event_id']]);
    $sub_events = $stmt_sub_events->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch which attendees are ALREADY registered for any sub-events
    $sql_existing = "SELECT attendee_id, sub_event_id FROM attendee_subevent_registrations WHERE attendee_id IN (SELECT attendee_id FROM attendees WHERE eventreg_id = :reg_id)";
    $stmt_existing = $pdo->prepare($sql_existing);
    $stmt_existing->execute([':reg_id' => $registration_id]);
    while ($row = $stmt_existing->fetch(PDO::FETCH_ASSOC)) {
        $already_registered[$row['sub_event_id']][] = $row['attendee_id'];
    }

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}

generate_csrf_token();
$page_title = 'Add Sub-Events';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-2">Add Sub-Events for: <strong><?= htmlspecialchars($event['event_name'] ?? 'Event') ?></strong></h1>
    <p class="lead">Select which of your registered attendees you would like to add to the sub-events below.</p>
    <hr>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php else: ?>
        <form action="cart_actions.php" method="POST">
            <input type="hidden" name="action" value="add_sub_event_addon">
            <input type="hidden" name="registration_id" value="<?= $registration_id ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <?php if (empty($sub_events)): ?>
                <div class="alert alert-info">There are no sub-events available for this event.</div>
            <?php else: ?>
                <?php foreach ($sub_events as $sub_event): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><?= htmlspecialchars($sub_event['sub_event_name']) ?></h5>
                            <small class="text-muted">Cost: $<?= number_format($sub_event['cost'], 2) ?> per person</small>
                        </div>
                        <div class="card-body">
                            <p><?= htmlspecialchars($sub_event['description']) ?></p>
                            <h6>Select attendees for this sub-event:</h6>
                            <?php foreach ($attendees as $attendee): ?>
                                <?php
                                    // Check if this attendee is already registered for this sub-event
                                    $is_already_in = isset($already_registered[$sub_event['sub_event_id']]) && in_array($attendee['attendee_id'], $already_registered[$sub_event['sub_event_id']]);
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="sub_event_addons[<?= $sub_event['sub_event_id'] ?>][]" 
                                           value="<?= $attendee['attendee_id'] ?>" 
                                           id="sub_<?= $sub_event['sub_event_id'] ?>_att_<?= $attendee['attendee_id'] ?>"
                                           <?= $is_already_in ? 'checked disabled' : '' ?>>
                                    <label class="form-check-label" for="sub_<?= $sub_event['sub_event_id'] ?>_att_<?= $attendee['attendee_id'] ?>">
                                        <?= htmlspecialchars($attendee['first_name'] . ' ' . $attendee['surname']) ?>
                                        <?= $is_already_in ? '<span class="badge bg-secondary ms-2">Already Registered</span>' : '' ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <a href="profile.php" class="btn btn-secondary">&laquo; Back to My Profile</a>
                <button type="submit" class="btn btn-success btn-lg">Add Selections to Cart</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>