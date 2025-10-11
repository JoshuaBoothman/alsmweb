<?php
// admin/edit_attendee.php

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /alsmweb/public_html/login.php?error=unauthorized");
    exit();
}

require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php';

// --- INITIALIZE VARIABLES ---
$error_message = '';
$attendee = null;
$planes = [];
$attendee_types = [];
$attendee_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$attendee_id) {
    header("Location: manage_events.php?error=noattendeeid");
    exit();
}

// --- FORM PROCESSING LOGIC (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();
    
    // Sanitize and retrieve all POST data
    $attendee_data = $_POST['attendee'];
    $planes_data = $_POST['planes'] ?? [];

    $pdo->beginTransaction();
    try {
        // Step 1: Update the main 'attendees' table
        $sql_update_attendee = "UPDATE attendees SET
            type_id = :type_id, first_name = :first_name, surname = :surname, email = :email, phone = :phone,
            address = :address, suburb = :suburb, state = :state, postcode = :postcode, dob = :dob,
            arrival_date = :arrival_date, departure_date = :departure_date,
            emergency_contact_name = :emergency_contact_name, emergency_contact_phone = :emergency_contact_phone,
            dietary_reqs = :dietary_reqs, notes = :notes, aus_number = :aus_number, flight_line_duty = :flight_line_duty
            WHERE attendee_id = :attendee_id";
        
        $stmt_update = $pdo->prepare($sql_update_attendee);
        $stmt_update->execute([
            ':type_id' => $attendee_data['type_id'],
            ':first_name' => trim($attendee_data['first_name']),
            ':surname' => trim($attendee_data['surname']),
            ':email' => trim($attendee_data['email']),
            ':phone' => trim($attendee_data['phone']),
            ':address' => trim($attendee_data['address']),
            ':suburb' => trim($attendee_data['suburb']),
            ':state' => $attendee_data['state'],
            ':postcode' => trim($attendee_data['postcode']),
            ':dob' => empty($attendee_data['dob']) ? null : $attendee_data['dob'],
            ':arrival_date' => empty($attendee_data['arrival_date']) ? null : $attendee_data['arrival_date'],
            ':departure_date' => empty($attendee_data['departure_date']) ? null : $attendee_data['departure_date'],
            ':emergency_contact_name' => trim($attendee_data['emergency_contact_name']),
            ':emergency_contact_phone' => trim($attendee_data['emergency_contact_phone']),
            ':dietary_reqs' => trim($attendee_data['dietary_reqs']),
            ':notes' => trim($attendee_data['notes']),
            ':aus_number' => isset($attendee_data['aus_number']) ? trim($attendee_data['aus_number']) : null,
            ':flight_line_duty' => isset($attendee_data['flight_line_duty']) ? 1 : 0,
            ':attendee_id' => $attendee_id
        ]);

        // Step 2: Clear old plane data for this attendee
        $stmt_delete_planes = $pdo->prepare("DELETE FROM attendee_planes WHERE attendee_id = :attendee_id");
        $stmt_delete_planes->execute([':attendee_id' => $attendee_id]);

        // Step 3: Insert the new set of plane data
        if (!empty($planes_data)) {
            $sql_insert_plane = "INSERT INTO attendee_planes (attendee_id, plane_model, cert_number, cert_expiry) VALUES (:attendee_id, :model, :cert, :expiry)";
            $stmt_insert_plane = $pdo->prepare($sql_insert_plane);
            foreach ($planes_data as $plane) {
                if (!empty(trim($plane['plane_model']))) { // Only insert if a model name is provided
                    $stmt_insert_plane->execute([
                        ':attendee_id' => $attendee_id,
                        ':model' => trim($plane['plane_model']),
                        ':cert' => empty(trim($plane['cert_number'])) ? null : trim($plane['cert_number']),
                        ':expiry' => empty($plane['cert_expiry']) ? null : $plane['cert_expiry']
                    ]);
                }
            }
        }
        
        $pdo->commit();
        $_SESSION['success_message'] = "Attendee details updated successfully.";
        
        // Fetch event_id for redirection
        $stmt_event = $pdo->prepare("SELECT er.event_id FROM attendees a JOIN eventregistrations er ON a.eventreg_id = er.registration_id WHERE a.attendee_id = ?");
        $stmt_event->execute([$attendee_id]);
        $event_id = $stmt_event->fetchColumn();

        header("Location: view_event_attendees.php?event_id=" . $event_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Database Error: Could not update attendee. " . $e->getMessage();
    }
}

// --- DATA FETCHING (GET REQUEST) ---
try {
    $stmt_attendee = $pdo->prepare("SELECT * FROM attendees WHERE attendee_id = :id");
    $stmt_attendee->execute([':id' => $attendee_id]);
    $attendee = $stmt_attendee->fetch(PDO::FETCH_ASSOC);

    if (!$attendee) {
        throw new Exception("Attendee not found.");
    }
    
    $stmt_planes = $pdo->prepare("SELECT * FROM attendee_planes WHERE attendee_id = :id");
    $stmt_planes->execute([':id' => $attendee_id]);
    $planes = $stmt_planes->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_types = $pdo->query("SELECT type_id, type_name FROM attendee_types WHERE is_active = 1 ORDER BY type_name");
    $attendee_types = $stmt_types->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
}

generate_csrf_token();
$page_title = 'Edit Attendee';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-4">Edit Attendee: <?= htmlspecialchars($attendee['first_name'] . ' ' . $attendee['surname']) ?></h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <?php if ($attendee): ?>
    <form action="edit_attendee.php?id=<?= $attendee_id ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        
        <div class="card mb-4">
            <div class="card-header"><h4>Core Details</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Attendee Type</label>
                        <select name="attendee[type_id]" class="form-select" required>
                            <?php foreach($attendee_types as $type): ?>
                            <option value="<?= $type['type_id'] ?>" <?= $type['type_id'] == $attendee['type_id'] ? 'selected' : '' ?>><?= htmlspecialchars($type['type_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" name="attendee[first_name]" class="form-control" value="<?= htmlspecialchars($attendee['first_name']) ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Surname</label>
                        <input type="text" name="attendee[surname]" class="form-control" value="<?= htmlspecialchars($attendee['surname']) ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h4>Contact & Address</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="attendee[email]" class="form-control" value="<?= htmlspecialchars($attendee['email']) ?>"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="tel" name="attendee[phone]" class="form-control" value="<?= htmlspecialchars($attendee['phone']) ?>"></div>
                    <div class="col-12 mb-3"><label class="form-label">Address</label><input type="text" name="attendee[address]" class="form-control" value="<?= htmlspecialchars($attendee['address']) ?>"></div>
                    <div class="col-md-5 mb-3"><label class="form-label">Suburb</label><input type="text" name="attendee[suburb]" class="form-control" value="<?= htmlspecialchars($attendee['suburb']) ?>"></div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">State</label>
                        <select name="attendee[state]" class="form-select">
                            <option value="">-- Select --</option>
                            <?php $states = ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA', 'Other']; ?>
                            <?php foreach($states as $state): ?>
                            <option value="<?= $state ?>" <?= $state == $attendee['state'] ? 'selected' : '' ?>><?= $state ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3"><label class="form-label">Postcode</label><input type="text" name="attendee[postcode]" class="form-control" value="<?= htmlspecialchars($attendee['postcode']) ?>"></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
             <div class="card-header"><h4>Event & Emergency Details</h4></div>
             <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Arrival Date</label><input type="date" name="attendee[arrival_date]" class="form-control" value="<?= htmlspecialchars($attendee['arrival_date']) ?>"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Departure Date</label><input type="date" name="attendee[departure_date]" class="form-control" value="<?= htmlspecialchars($attendee['departure_date']) ?>"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Emergency Contact Name</label><input type="text" name="attendee[emergency_contact_name]" class="form-control" value="<?= htmlspecialchars($attendee['emergency_contact_name']) ?>"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Emergency Contact Phone</label><input type="tel" name="attendee[emergency_contact_phone]" class="form-control" value="<?= htmlspecialchars($attendee['emergency_contact_phone']) ?>"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Dietary Requirements</label><textarea name="attendee[dietary_reqs]" class="form-control" rows="2"><?= htmlspecialchars($attendee['dietary_reqs']) ?></textarea></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Other Notes</label><textarea name="attendee[notes]" class="form-control" rows="2"><?= htmlspecialchars($attendee['notes']) ?></textarea></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
             <div class="card-header"><h4>Conditional Details (Pilot/Junior)</h4></div>
             <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Date of Birth (for Juniors)</label><input type="date" name="attendee[dob]" class="form-control" value="<?= htmlspecialchars($attendee['dob']) ?>"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">AUS Number (for Pilots)</label><input type="text" name="attendee[aus_number]" class="form-control" value="<?= htmlspecialchars($attendee['aus_number']) ?>"></div>
                </div>
                <div class="form-check mb-3"><input type="checkbox" name="attendee[flight_line_duty]" class="form-check-input" value="1" <?= $attendee['flight_line_duty'] ? 'checked' : '' ?>><label class="form-check-label">Available for Flight Line Duty?</label></div>

                <div id="planes-section" class="border-top pt-3 mt-3">
                    <h6>Registered Aircraft (for Pilots)</h6>
                    <div id="planes-container">
                        </div>
                    <button type="button" id="add-plane-btn" class="btn btn-sm btn-outline-info mt-2">Add Plane</button>
                </div>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="javascript:history.back()" class="btn btn-secondary">Cancel</a>
    </form>
    <?php endif; ?>
</div>

<template id="plane-template">
    <div class="plane-card card bg-light p-3 mb-2">
        <div class="row align-items-end">
            <div class="col-md-4 mb-2"><label class="form-label">Model</label><input type="text" name="planes[__INDEX__][plane_model]" class="form-control" required></div>
            <div class="col-md-3 mb-2"><label class="form-label">Cert #</label><input type="text" name="planes[__INDEX__][cert_number]" class="form-control"></div>
            <div class="col-md-3 mb-2"><label class="form-label">Expiry</label><input type="date" name="planes[__INDEX__][cert_expiry]" class="form-control"></div>
            <div class="col-md-2 mb-2"><button type="button" class="btn btn-danger btn-sm remove-plane-btn w-100">Remove</button></div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const planesContainer = document.getElementById('planes-container');
    const addPlaneBtn = document.getElementById('add-plane-btn');
    const planeTemplate = document.getElementById('plane-template');
    let planeCounter = 0;
    
    const existingPlanes = <?= json_encode($planes) ?>;

    function addPlane(data = {}) {
        const newPlaneFragment = planeTemplate.content.cloneNode(true);
        newPlaneFragment.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace('__INDEX__', planeCounter);
        });

        if (data.plane_model) newPlaneFragment.querySelector('[name*="[plane_model]"]').value = data.plane_model;
        if (data.cert_number) newPlaneFragment.querySelector('[name*="[cert_number]"]').value = data.cert_number;
        if (data.cert_expiry) newPlaneFragment.querySelector('[name*="[cert_expiry]"]').value = data.cert_expiry;

        planesContainer.appendChild(newPlaneFragment);
        planeCounter++;
    }

    planesContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-plane-btn')) {
            e.target.closest('.plane-card').remove();
        }
    });

    addPlaneBtn.addEventListener('click', () => addPlane());

    // Initial load
    if(existingPlanes.length > 0) {
        existingPlanes.forEach(planeData => addPlane(planeData));
    } else {
        addPlane(); // Add one empty plane row to start with
    }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>