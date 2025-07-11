<?php
// public_html/process_contact.php

// This script processes the contact form submission.

// Include the security helper functions and start the session.
require_once '../lib/functions/security_helpers.php';
session_start();

// 1. Security Check: Only allow POST requests.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: contact.php");
    exit();
}

// 2. Security Check: Validate the CSRF token.
validate_csrf_token();

// 3. Get and validate the form data.
$name = trim($_POST['contactName']);
$email = trim($_POST['contactEmail']);
$message_body = trim($_POST['contactMessage']);
$errors = [];

if (empty($name)) {
    $errors[] = "Name is required.";
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "A valid email address is required.";
}
if (empty($message_body)) {
    $errors[] = "A message is required.";
}

// 4. Process the form submission.
if (empty($errors)) {
    // --- EMAIL SENDING LOGIC WOULD GO HERE ---
    // This is where you would integrate a library like PHPMailer to send the actual email.
    // For now, we will simulate a success message and store it in the session.
    $_SESSION['success_message'] = "Thank you for your message! We will get back to you shortly.";
} else {
    // If there are validation errors, store them and the user's input in the session.
    $_SESSION['error_message'] = implode('<br>', $errors);
    $_SESSION['form_input'] = $_POST;
}

// 5. Redirect the user back to the contact page to see the message.
header("Location: contact.php");
exit();
