<?php
// public_html/process_registration.php

// This is a logic-only file. It processes the attendee data from the previous step,
// saves it to the session, and redirects to the sub-event selection page.

session_start();

// Security check: ensure user is logged in and the form was submitted via POST.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: events.php");
    exit();
}

// --- DATA VALIDATION AND PROCESSING ---

// Get the event_id from the hidden form field.
$event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
if (!$event_id) {
    // Redirect with an error if the event ID is missing.
    header("Location: events.php?error=missingeventid");
    exit();
}

// Get the array of attendees submitted by the form.
$attendees_data = $_POST['attendees'] ?? [];

if (empty($attendees_data)) {
    // Redirect back if no attendees were submitted.
    header("Location: register_for_event.php?event_id=$event_id&error=noattendees");
    exit();
}

// **THE FIX**: Use array_values() to re-index the array from 0.
// This prevents issues when attendees are removed from the middle of the list.
$clean_attendees_data = array_values($attendees_data);


// Store the processed attendee data in a structured session variable.
$_SESSION['event_registration_in_progress'] = [
    'event_id' => $event_id,
    'attendees' => $clean_attendees_data, // Use the cleaned array
    'sub_events' => [] // This will be populated in the next step.
];

// Redirect the user to the next step: selecting sub-events.
header("Location: select_sub_events.php");
exit();

?>
