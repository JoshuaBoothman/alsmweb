<?php
// lib/functions/security_helpers.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generates a CSRF token and stores it in the session.
 * Call this function on the page that displays the form.
 */
function generate_csrf_token() {
    // Check if a token already exists for this session to prevent overwriting
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Validates the submitted CSRF token against the one in the session.
 * Call this function at the beginning of the script that processes the form submission.
 * It will terminate the script with an error if validation fails.
 */
function validate_csrf_token() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Token is invalid or missing, so we stop execution.
        die('CSRF validation failed. Invalid or missing token.');
    }
    // Unset the token after use so it can't be used again
    unset($_SESSION['csrf_token']);
}
