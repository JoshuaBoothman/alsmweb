<?php
// public_html/register_for_event.php

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php?error=loginrequired");
    exit();
}

require_once '../config/db_config.php';
require_once '../lib/functions/security_helpers.php'; // 1. Include CSRF helpers

// --- INITIALIZE VARIABLES ---
$event = null;
$attendee_types = [];
$error_message = '';
$event_id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

// If a registration is already in progress, use its event_id to ensure consistency
if (isset($_SESSION['event_registration_in_progress'])) {
    $event_id = $_SESSION['event_registration_in_progress']['event_id'];
}

if (!$event_id) {
    header("Location: events.php?error=noeventid");
    exit();
}

// --- DATA FETCHING ---
try {
    $stmt_event = $pdo->prepare("SELECT event_id, event_name FROM events WHERE event_id = :id AND event_IsDeleted = 0");
    $stmt_event->execute([':id' => $event_id]);
    $event = $stmt_event->fetch(PDO::FETCH_ASSOC);
    if (!$event) throw new Exception("The selected event could not be found.");

    $stmt_types = $pdo->query("SELECT type_id, type_name, price, requires_pilot_details, requires_junior_details FROM attendee_types WHERE is_active = 1 ORDER BY type_name");
    $attendee_types = $stmt_types->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}

// Get existing data if it's in the session to pass to JavaScript
$existing_attendees = $_SESSION['event_registration_in_progress']['attendees'] ?? [];

// 2. Generate a token for the form
generate_csrf_token();

// --- HEADER ---
$page_title = 'Register for ' . htmlspecialchars($event['event_name'] ?? 'Event');
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container mt-5">
    <h1 class="mb-2">Register for: <strong><?= htmlspecialchars($event['event_name']) ?></strong></h1>
    <p class="lead">Step 1 of 2: Add Attendee Information</p>
    <hr>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php else: ?>
        <form id="registration-form" action="process_registration.php" method="POST">
            <input type="hidden" name="event_id" value="<?= $event_id ?>">
            <!-- 3. Add the hidden CSRF token field -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div id="attendees-container">
                <!-- Attendee blocks will be dynamically inserted here by JavaScript -->
            </div>

            <button type="button" id="add-attendee-btn" class="btn btn-secondary"><i class="fas fa-plus"></i> Add Another Attendee</button>
            <hr>

            <!-- Totals and Submit Button -->
            <div class="row align-items-center">
                <div class="col-md-6">
                    <!-- This space can be used for messages or other elements later -->
                </div>
                <div class="col-md-6 text-end">
                    <h3 class="mb-3">Total: <span id="total-price" class="text-success fw-bold">$0.00</span></h3>
                    <button type="submit" class="btn btn-primary btn-lg">Proceed to Select Sub-Events &raquo;</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<template id="attendee-template">
    <div class="attendee-card card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Attendee</h5>
            <div class="d-flex align-items-center">
                <span class="attendee-price fs-5 fw-bold text-success me-3">$0.00</span>
                <button type="button" class="btn-close remove-attendee-btn" aria-label="Close"></button>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Attendee Type</label>
                    <select name="attendees[__INDEX__][type_id]" class="form-select attendee-type-select" required>
                        <option value="">-- Select Type --</option>
                        <?php foreach ($attendee_types as $type): ?>
                            <option value="<?= $type['type_id'] ?>" 
                                    data-price="<?= $type['price'] ?>" 
                                    data-requires-pilot="<?= $type['requires_pilot_details'] ?>"
                                    data-requires-junior="<?= $type['requires_junior_details'] ?>">
                                <?= htmlspecialchars($type['type_name']) ?> ($<?= number_format($type['price'], 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">First Name</label>
                    <input type="text" name="attendees[__INDEX__][first_name]" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Surname</label>
                    <input type="text" name="attendees[__INDEX__][surname]" class="form-control" required>
                </div>
            </div>

            <hr>
            <h6>Contact & Address</h6>
            <div class="row">
                 <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="attendees[__INDEX__][email]" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="attendees[__INDEX__][phone]" class="form-control">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" name="attendees[__INDEX__][address]" class="form-control">
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">Suburb</label>
                    <input type="text" name="attendees[__INDEX__][suburb]" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                <label class="form-label">State</label>
                <select name="attendees[__INDEX__][state]" class="form-select">
                    <option value="">-- Select State --</option>
                    <option value="ACT">ACT</option>
                    <option value="NSW">NSW</option>
                    <option value="NT">NT</option>
                    <option value="QLD">QLD</option>
                    <option value="SA">SA</option>
                    <option value="TAS">TAS</option>
                    <option value="VIC">VIC</option>
                    <option value="WA">WA</option>
                    <option value="Other">Other</option>
                </select>
            </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Postcode</label>
                    <input type="text" name="attendees[__INDEX__][postcode]" class="form-control">
                </div>
            </div>
            <hr>
            <h6>Logistics & Additional Information</h6>
             <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Arrival Date</label>
                    <input type="date" name="attendees[__INDEX__][arrival_date]" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Departure Date</label>
                    <input type="date" name="attendees[__INDEX__][departure_date]" class="form-control">
                </div>
            </div>
             <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Emergency Contact Name</label>
                    <input type="text" name="attendees[__INDEX__][emergency_contact_name]" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Emergency Contact Phone</label>
                    <input type="tel" name="attendees[__INDEX__][emergency_contact_phone]" class="form-control">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Dietary Requirements</label>
                    <textarea name="attendees[__INDEX__][dietary_reqs]" class="form-control" rows="2"></textarea>
                </div>
                 <div class="col-md-6 mb-3">
                    <label class="form-label">Other Notes</label>
                    <textarea name="attendees[__INDEX__][notes]" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="conditional-fields"></div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const attendeesContainer = document.getElementById('attendees-container');
    const addAttendeeBtn = document.getElementById('add-attendee-btn');
    const template = document.getElementById('attendee-template');
    const totalPriceSpan = document.getElementById('total-price');
    let attendeeCounter = 0;
    
    const existingAttendees = <?= json_encode($existing_attendees) ?>;

    function addAttendee(data = {}) {
        const newAttendeeFragment = template.content.cloneNode(true);
        const card = newAttendeeFragment.querySelector('.attendee-card');
        card.id = 'attendee-' + attendeeCounter;

        card.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace('__INDEX__', attendeeCounter);
        });

        // --- START: Update this section to pre-fill all fields ---
        if (data.first_name) card.querySelector('[name*="[first_name]"]').value = data.first_name;
        if (data.surname) card.querySelector('[name*="[surname]"]').value = data.surname;
        if (data.email) card.querySelector('[name*="[email]"]').value = data.email;
        if (data.phone) card.querySelector('[name*="[phone]"]').value = data.phone;
        if (data.address) card.querySelector('[name*="[address]"]').value = data.address;
        if (data.suburb) card.querySelector('[name*="[suburb]"]').value = data.suburb;
        if (data.state) card.querySelector('[name*="[state]"]').value = data.state;
        if (data.postcode) card.querySelector('[name*="[postcode]"]').value = data.postcode;
        if (data.arrival_date) card.querySelector('[name*="[arrival_date]"]').value = data.arrival_date;
        if (data.departure_date) card.querySelector('[name*="[departure_date]"]').value = data.departure_date;
        if (data.emergency_contact_name) card.querySelector('[name*="[emergency_contact_name]"]').value = data.emergency_contact_name;
        if (data.emergency_contact_phone) card.querySelector('[name*="[emergency_contact_phone]"]').value = data.emergency_contact_phone;
        if (data.dietary_reqs) card.querySelector('[name*="[dietary_reqs]"]').value = data.dietary_reqs;
        if (data.notes) card.querySelector('[name*="[notes]"]').value = data.notes;
        // --- END: Update section ---
        
        attendeesContainer.appendChild(newAttendeeFragment);
        
        const currentCard = document.getElementById('attendee-' + attendeeCounter);
        const typeSelect = currentCard.querySelector('.attendee-type-select');

        if (data.type_id) {
            typeSelect.value = data.type_id;
            typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        setTimeout(() => {
            const currentCard = document.getElementById('attendee-' + attendeeCounter);
            if (!currentCard) return;

            if (data.dob && currentCard.querySelector('[name*="[dob]"]')) {
                currentCard.querySelector('[name*="[dob]"]').value = data.dob;
            }
            if (data.aus_number && currentCard.querySelector('[name*="[aus_number]"]')) {
                 currentCard.querySelector('[name*="[aus_number]"]').value = data.aus_number;
            }
            if (data.flight_line_duty && currentCard.querySelector('[name*="[flight_line_duty]"]')) {
                currentCard.querySelector('[name*="[flight_line_duty]"]').checked = true;
            }
            
            if(data.planes && Array.isArray(data.planes)) {
                const planesContainer = currentCard.querySelector('.planes-container');
                if (planesContainer) {
                    data.planes.forEach((plane) => {
                        addPlane(planesContainer, attendeeCounter, plane);
                    });
                }
            }
            attendeeCounter++;
            updateUI();
        }, 0);
    }

    function addPlane(container, attendeeIndex, data = {}) {
        const planeCount = container.children.length;
        const planeHtml = `
            <div class="plane-card card bg-light p-3 mb-2">
                <div class="row">
                    <div class="col-12 mb-2"><label class="form-label">Plane Type/Model</label><input type="text" name="attendees[${attendeeIndex}][planes][${planeCount}][plane_model]" class="form-control" required value="${data.plane_model || ''}"></div>
                    <div class="col-md-6 mb-2"><label class="form-label">Heavy Model Cert #</label><input type="text" name="attendees[${attendeeIndex}][planes][${planeCount}][cert_number]" class="form-control" value="${data.cert_number || ''}"></div>
                    <div class="col-md-6 mb-2"><label class="form-label">Cert Expiry</label><input type="date" name="attendees[${attendeeIndex}][planes][${planeCount}][cert_expiry]" class="form-control" value="${data.cert_expiry || ''}"></div>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', planeHtml);
    }

    function updateUI() {
        let total = 0;
        attendeesContainer.querySelectorAll('.attendee-card').forEach((card, index) => {
            // Alternating colors for separation
            card.classList.remove('bg-light');
            if (index % 2 !== 0) {
                card.classList.add('bg-light');
            }
            // Update headers
            card.querySelector('h5').textContent = 'Attendee ' + (index + 1);

            // Calculate total
            const typeSelect = card.querySelector('.attendee-type-select');
            if (typeSelect.value) {
                const price = parseFloat(typeSelect.options[typeSelect.selectedIndex].dataset.price) || 0;
                total += price;
            }
        });
        totalPriceSpan.textContent = '$' + total.toFixed(2);
    }

    attendeesContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-attendee-btn')) {
            e.target.closest('.attendee-card').remove();
            updateUI();
        }
        if (e.target.classList.contains('add-plane-btn')) {
            const planesContainer = e.target.previousElementSibling;
            const attendeeIndex = e.target.closest('.attendee-card').id.split('-')[1];
            addPlane(planesContainer, attendeeIndex);
        }
    });

    attendeesContainer.addEventListener('change', function(e) {
        if (e.target.classList.contains('attendee-type-select')) {
            const selectedOption = e.target.options[e.target.selectedIndex];
            const requiresPilot = selectedOption.dataset.requiresPilot === '1';
            const requiresJunior = selectedOption.dataset.requiresJunior === '1';
            const price = parseFloat(selectedOption.dataset.price) || 0;
            const card = e.target.closest('.attendee-card');
            const conditionalFieldsContainer = card.querySelector('.conditional-fields');
            
            card.querySelector('.attendee-price').textContent = '$' + price.toFixed(2);
            
            conditionalFieldsContainer.innerHTML = '';
            let html = '';

            if (requiresJunior) {
                html += `<div class="mb-3"><label class="form-label">Date of Birth</label><input type="date" name="${e.target.name.replace('type_id', 'dob')}" class="form-control" required></div>`;
            }
            if (requiresPilot) {
                html += `<div class="row"><div class="col-md-6 mb-3"><label class="form-label">AUS Number</label><input type="text" name="${e.target.name.replace('type_id', 'aus_number')}" class="form-control" required></div><div class="col-md-6 mb-3 d-flex align-items-center"><div class="form-check mt-3"><input type="checkbox" name="${e.target.name.replace('type_id', 'flight_line_duty')}" class="form-check-input" value="1"><label class="form-check-label">Available for Flight Line Duty?</label></div></div></div><div class="planes-section border-top pt-3 mt-3"><h6>Registered Aircraft</h6><div class="planes-container"></div><button type="button" class="btn btn-sm btn-outline-info add-plane-btn mt-2">Add Plane</button></div>`;
            }
            conditionalFieldsContainer.innerHTML = html;
            updateUI();
        }
    });

    // --- INITIAL PAGE LOAD LOGIC ---
    if (existingAttendees.length > 0) {
        existingAttendees.forEach(attendeeData => addAttendee(attendeeData));
    } else {
        addAttendee();
    }
    
    addAttendeeBtn.addEventListener('click', () => addAttendee());
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
