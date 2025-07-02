<?php
// admin/view_attendee_details.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';

// --- INITIALIZE VARIABLES ---
$attendee = null;
$planes = [];
$error_message = '';
$attendee_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$attendee_id) {
    header("Location: view_event_registrations.php?error=noattendeeid");
    exit();
}

// --- HANDLE CERTIFICATE SIGHTED TOGGLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_cert_sighted'])) {
    $plane_id_to_toggle = filter_input(INPUT_POST, 'plane_id', FILTER_VALIDATE_INT);
    if ($plane_id_to_toggle) {
        try {
            // This query cleverly toggles the boolean value (0 becomes 1, 1 becomes 0)
            $sql_toggle = "UPDATE attendee_planes SET cert_sighted = NOT cert_sighted WHERE plane_id = :plane_id";
            $stmt_toggle = $pdo->prepare($sql_toggle);
            $stmt_toggle->execute([':plane_id' => $plane_id_to_toggle]);
            $_SESSION['success_message'] = "Certificate status updated successfully.";
            // Redirect back to the same page to show the change
            header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $attendee_id);
            exit();
        } catch (PDOException $e) {
            $error_message = "Database Error: Could not update certificate status. " . $e->getMessage();
        }
    }
}


// --- DATA FETCHING ---
try {
    // 1. Fetch main attendee details
    $sql_attendee = "SELECT a.*, at.type_name, e.event_name 
                     FROM attendees a 
                     JOIN attendee_types at ON a.type_id = at.type_id
                     JOIN eventregistrations er ON a.eventreg_id = er.registration_id
                     JOIN events e ON er.event_id = e.event_id
                     WHERE a.attendee_id = :id";
    $stmt_attendee = $pdo->prepare($sql_attendee);
    $stmt_attendee->execute([':id' => $attendee_id]);
    $attendee = $stmt_attendee->fetch(PDO::FETCH_ASSOC);

    if (!$attendee) {
        throw new Exception("Attendee not found.");
    }

    // 2. If the attendee is a pilot, fetch their planes
    if ($attendee['type_name'] === 'Pilot') {
        $sql_planes = "SELECT * FROM attendee_planes WHERE attendee_id = :id ORDER BY plane_model ASC";
        $stmt_planes = $pdo->prepare($sql_planes);
        $stmt_planes->execute([':id' => $attendee_id]);
        $planes = $stmt_planes->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}


// --- HEADER ---
$page_title = 'View Attendee Details';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <?php if ($attendee): ?>
        <h1 class="mb-2">Attendee: <?= htmlspecialchars($attendee['first_name'] . ' ' . $attendee['surname']) ?></h1>
        <p class="lead">Viewing registration for event: <strong><?= htmlspecialchars($attendee['event_name']) ?></strong></p>
    <?php else: ?>
        <h1 class="mb-4">Attendee Details</h1>
    <?php endif; ?>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if ($error_message) {
        echo '<div class="alert alert-danger">' . $error_message . '</div>';
    }
    ?>

    <?php if ($attendee): ?>
        <div class="row">
            <!-- Left Column: Details -->
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h4>Personal & Contact Information</h4>
                    </div>
                    <div class="card-body">
                        <p><strong>Attendee Type:</strong> <?= htmlspecialchars($attendee['type_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($attendee['email']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($attendee['phone']) ?></p>
                        <hr>
                        <p><strong>Address:</strong><br>
                            <?= htmlspecialchars($attendee['address']) ?><br>
                            <?= htmlspecialchars($attendee['suburb']) ?>, <?= htmlspecialchars($attendee['state']) ?> <?= htmlspecialchars($attendee['postcode']) ?>
                        </p>
                        <hr>
                        <p><strong>Emergency Contact:</strong> <?= htmlspecialchars($attendee['emergency_contact_name']) ?></p>
                        <p><strong>Emergency Phone:</strong> <?= htmlspecialchars($attendee['emergency_contact_phone']) ?></p>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h4>Event Specifics</h4>
                    </div>
                    <div class="card-body">
                        <p><strong>Arrival Date:</strong> <?= $attendee['arrival_date'] ? date('d M Y', strtotime($attendee['arrival_date'])) : 'N/A' ?></p>
                        <p><strong>Departure Date:</strong> <?= $attendee['departure_date'] ? date('d M Y', strtotime($attendee['departure_date'])) : 'N/A' ?></p>
                        <p><strong>Dietary Requirements:</strong> <?= nl2br(htmlspecialchars($attendee['dietary_reqs'] ?? 'None')) ?></p>
                        <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($attendee['notes'] ?? 'None')) ?></p>
                         <?php if ($attendee['type_name'] === 'Junior'): ?>
                            <p><strong>Date of Birth:</strong> <?= $attendee['dob'] ? date('d M Y', strtotime($attendee['dob'])) : 'N/A' ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Pilot Info -->
            <div class="col-lg-5">
                <?php if ($attendee['type_name'] === 'Pilot'): ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4>Pilot Information</h4>
                        </div>
                        <div class="card-body">
                            <p><strong>AUS Number:</strong> <?= htmlspecialchars($attendee['aus_number']) ?></p>
                            <p><strong>Available for Flight Line Duty:</strong> <?= $attendee['flight_line_duty'] ? 'Yes' : 'No' ?></p>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header bg-info text-dark">
                            <h4>Registered Aircraft</h4>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($planes)): ?>
                                <ul class="list-group">
                                    <?php foreach ($planes as $plane): ?>
                                        <li class="list-group-item">
                                            <p class="mb-1"><strong>Model:</strong> <?= htmlspecialchars($plane['plane_model']) ?></p>
                                            <p class="mb-1"><small><strong>Cert Number:</strong> <?= htmlspecialchars($plane['cert_number'] ?? 'N/A') ?></small></p>
                                            <p class="mb-2"><small><strong>Cert Expiry:</strong> <?= $plane['cert_expiry'] ? date('d M Y', strtotime($plane['cert_expiry'])) : 'N/A' ?></small></p>
                                            <form action="view_attendee_details.php?id=<?= $attendee_id ?>" method="POST" class="d-flex justify-content-end">
                                                <input type="hidden" name="plane_id" value="<?= $plane['plane_id'] ?>">
                                                <button type="submit" name="toggle_cert_sighted" class="btn btn-sm <?= $plane['cert_sighted'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                                                    <?= $plane['cert_sighted'] ? '✓ Certificate Sighted' : 'Mark as Sighted' ?>
                                                </button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No aircraft registered for this attendee.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="mt-4">
        <a href="javascript:history.back()" class="btn btn-secondary">&laquo; Back to Attendee List</a>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
